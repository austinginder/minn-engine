<?php
/**
 * PHPMailer and wp_mail on the wire, against tests/fixtures/mail/fake-smtp.php
 * listening on MINN_SMTP_PORT and recording sessions into MINN_SMTP_DIR:
 * the SMTP class driven directly (connect, EHLO, each AUTH mechanism, the
 * envelope, a refused recipient, DATA, RSET/NOOP/VRFY, QUIT), PHPMailer
 * sending over SMTP, and wp_mail configured through phpmailer_init (headers,
 * attachments, the mail filters, success and failure actions). Every
 * session's transcript is returned with dates, ids and boundaries
 * normalized. Same protocol as api-probe.php; run outside WP-CLI.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$port = (int) getenv('MINN_SMTP_PORT');
$dir = (string) getenv('MINN_SMTP_DIR');
$fixtures = dirname(__DIR__) . '/fixtures/mail';
$normalize = static fn (string $text): string => (string) preg_replace(
    ['/b([123])=_[A-Za-z0-9]+/', '/(PHPMailer )[0-9.]+/', '/^Date: .*$/m', '/<[A-Za-z0-9]{20,}@([^>]+)>/', '/(Message-ID: <)[^@>]+(@[^>]+>)/'],
    ['b$1=_ID', '$1V', 'Date: (now)', '<ID@$1>', '$1ID$2'],
    $text
);
$debug = [];
$collect = static function ($text, $level) use (&$debug, $normalize): void {
    $debug[] = [$level, $normalize(rtrim((string) $text))];
};

// 1. The SMTP class on its own.
$smtp = new SMTP();
$smtp->do_debug = SMTP::DEBUG_SERVER;
$smtp->Debugoutput = $collect;
$steps = [];
$steps['connect'] = $smtp->connect('127.0.0.1', $port, 5);
$steps['hello'] = $smtp->hello('client.test');
$steps['ext list'] = $smtp->getServerExtList();
$steps['ext'] = [$smtp->getServerExt('AUTH'), $smtp->getServerExt('8BITMIME'), $smtp->getServerExt('SIZE'), $smtp->getServerExt('HELO'), $smtp->getServerExt('STARTTLS')];
$steps['auth login'] = $smtp->authenticate('probe', 'secret', 'LOGIN');
$steps['mail'] = $smtp->mail('from@example.test');
$steps['rcpt'] = $smtp->recipient('to@example.test');
$steps['rcpt reject'] = [$smtp->recipient('reject@example.test'), $smtp->getError(), $smtp->getLastReply()];
$steps['data'] = $smtp->data("Subject: Direct\r\n\r\nLine one\r\n.leading dot\r\n" . str_repeat('x', 1100) . "\r\n");
$steps['transaction id'] = $smtp->getLastTransactionID();
$steps['reset noop verify turn'] = [$smtp->reset(), $smtp->noop(), $smtp->verify('someone'), $smtp->turn(), $smtp->getError()];
$steps['quit'] = $smtp->quit();
$smtp->close();
$steps['after close'] = [$smtp->connected(), $smtp->getError()];
$say('smtp direct', $steps);
$say('smtp direct debug', $debug);

$auths = [];
foreach (['PLAIN', 'CRAM-MD5', '', 'XOAUTH2'] as $type) {
    $s = new SMTP();
    $s->connect('127.0.0.1', $port, 5);
    $s->hello('client.test');
    $auths[$type === '' ? 'auto' : $type] = [$s->authenticate('probe', 'secret', $type), $s->getError()];
    $s->quit();
    $s->close();
}
$s = new SMTP();
$s->connect('127.0.0.1', $port, 5);
$s->hello('client.test');
$auths['wrong password'] = [$s->authenticate('probe', 'nope', 'PLAIN'), $s->getError(), $s->getLastReply()];
$auths['starttls'] = [$s->startTLS(), $s->getError()];
$s->quit();
$s->close();
$say('smtp auth', $auths);
$closed = new SMTP();
$say('smtp refused', [$closed->connect('127.0.0.1', 1, 2), $closed->getError(), $closed->connected()]);

// 2. PHPMailer sending over SMTP.
$send = static function (callable $setup) use ($port, $fixtures): array {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = '127.0.0.1';
    $mail->Port = $port;
    $mail->SMTPAuth = true;
    $mail->Username = 'probe';
    $mail->Password = 'secret';
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->Subject = 'Over SMTP';
    $mail->Body = 'Body text';
    $setup($mail, $fixtures);
    try {
        return [$mail->send(), $mail->ErrorInfo];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage(), $mail->ErrorInfo];
    }
};
$say('phpmailer smtp', $send(static function ($m) { $m->addAddress('to@example.test', 'To'); $m->addCC('cc@example.test'); $m->addBCC('bcc@example.test'); }));
$say('phpmailer smtp reject', $send(static function ($m) { $m->addAddress('to@example.test'); $m->addAddress('reject@example.test'); }));
$say('phpmailer smtp all rejected', $send(static function ($m) { $m->addAddress('reject@example.test'); }));
$say('phpmailer smtp bad auth', $send(static function ($m) { $m->addAddress('to@example.test'); $m->Password = 'nope'; }));
$say('phpmailer smtp no auth utf8', $send(static function ($m, $f) { $m->SMTPAuth = false; $m->CharSet = 'utf-8'; $m->addAddress('to@example.test'); $m->Subject = 'Ünïcödé over SMTP'; $m->isHTML(true); $m->Body = '<p>Ünï</p>'; $m->AltBody = 'Ünï'; $m->addAttachment($f . '/notes.txt'); }));
$say('phpmailer smtp refused', (static function () {
    $mail = new PHPMailer(false);
    $mail->isSMTP();
    $mail->Host = '127.0.0.1';
    $mail->Port = 1;
    $mail->Timeout = 2;
    $mail->setFrom('sender@example.test');
    $mail->addAddress('to@example.test');
    $mail->Subject = 'x';
    $mail->Body = 'y';
    return [$mail->send(), $mail->ErrorInfo];
})());
$say('phpmailer keepalive', (static function () use ($port) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = '127.0.0.1';
    $mail->Port = $port;
    $mail->SMTPKeepAlive = true;
    $mail->setFrom('sender@example.test');
    $results = [];
    foreach (['one@example.test', 'two@example.test'] as $to) {
        $mail->clearAddresses();
        $mail->addAddress($to);
        $mail->Subject = 'Keepalive ' . $to;
        $mail->Body = 'x';
        $results[] = $mail->send();
    }
    $mail->smtpClose();
    return $results;
})());

// The SMTP client's debug text at every level, and where each Debugoutput setting sends it.
$port4 = static fn (string $text): string => (string) preg_replace(['/127\.0\.0\.1:\d+/', '/\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/', '/\[\/[^\]]+ line \d+\]/', '/[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} \d\d:\d\d:\d\d [+-]\d{4}/'], ['127.0.0.1:PORT', 'TIME', '[FILE]', '(now)'], $text);
$say('smtp debug levels', (static function () use ($port, $port4) {
    $lines = [];
    $s = new SMTP();
    $s->do_debug = SMTP::DEBUG_LOWLEVEL;
    $s->Debugoutput = static function ($text, $level) use (&$lines, $port4) { $lines[] = [$level, $port4(rtrim((string) $text))]; };
    $s->connect('127.0.0.1', $port, 5);
    $s->hello('client.test');
    $s->noop();
    $s->quit();
    $s->close();
    return $lines;
})());
$say('debug output forms', array_map(static function ($how) use ($port, $port4) {
    ob_start();
    $s = new SMTP();
    $s->do_debug = SMTP::DEBUG_SERVER;
    $s->Debugoutput = $how;
    $s->connect('127.0.0.1', $port, 5);
    $s->hello('client.test');
    $s->quit();
    $s->close();
    $smtp = ob_get_clean();
    ob_start();
    $m = new PHPMailer();
    $m->isSMTP();
    $m->SMTPDebug = 1;
    $m->Debugoutput = $how;
    $m->Host = 'bad host!';
    $m->setFrom('a@example.test');
    $m->addAddress('b@example.test');
    $m->Body = 'x';
    $m->send();
    return [$port4((string) $smtp), $port4((string) ob_get_clean())];
}, ['echo', 'html']));
$say('smtp states', (static function () use ($port) {
    $s = new SMTP();
    $offline = [$s->getDebugOutput(), $s->setDebugOutput('html'), $s->getDebugOutput(), $s->setDebugLevel(3), $s->getDebugLevel(), $s->setTimeout(9), $s->getTimeout(), $s->setVerp(true), $s->getVerp(), $s->setSMTPUTF8(true), $s->getSMTPUTF8(), $s->data('x'), $s->getError(), $s->mail('a@b.c'), $s->getError(), $s->hello(), $s->getError(), $s->authenticate('a', 'b'), $s->getError(), $s->quit(), $s->getError(), $s->getLastTransactionID()];
    $s = new SMTP();
    $s->connect('127.0.0.1', $port, 5);
    $early = [$s->authenticate('probe', 'secret'), $s->getError(), $s->connect('127.0.0.1', $port), $s->getError(), $s->getServerExtList(), $s->getServerExt('AUTH'), $s->getError()];
    $s->hello('');
    $late = [$s->getServerExtList(), $s->getLastReply(), $s->authenticate('probe', 'secret', 'NTLM'), $s->getError(), $s->sendAndMail('a@b.c'), $s->getError(), $s->xclient(['ADDR' => '1.2.3.4', 'BAD' => 'x']), $s->getError(), $s->recipient('x@y.z', 'NEVER'), $s->recipient('x@y.z', 'BOGUS'), $s->getError()];
    $s->quit();
    $s->close();
    return [$offline, $early, $late];
})());
// SMTP DATA: long lines cut at a space or at 997, header continuations indented, dots doubled after the cut.
$say('smtp data lines', (static function () use ($port) {
    $cases = [
        "Subject: A\r\n\r\n" . str_repeat('word ', 250) . "\r\nend",
        "Subject: A\r\nX-Long: " . str_repeat('word ', 250) . "\r\n\r\nbody",
        "X-Long: " . str_repeat('h', 2100) . "\r\n\r\nbody",
        "S: x\r\n\r\n" . str_repeat('a', 997) . ' ' . str_repeat('b', 50),
        "S: x\r\n\r\n" . str_repeat('a', 998) . ' ' . str_repeat('b', 50),
        "S: x\n\nline1\rline2\r\nline3\n.dot\n..two\n",
        "plain first line\r\nSubject: not a header\r\n" . str_repeat('z', 1000),
        "S: x\r\n\r\n." . str_repeat('d', 1200),
        '',
    ];
    $results = [];
    foreach ($cases as $data) {
        $s = new SMTP();
        $s->connect('127.0.0.1', $port, 5);
        $s->hello('c.test');
        $s->mail('a@b.c');
        $s->recipient('d@e.f');
        $results[] = $s->data($data);
        $s->quit();
        $s->close();
    }
    return $results;
})());
// PHPMailer over SMTP: the failures, each with its debug text, and the send options.
$run = static function (bool $exceptions, callable $setup) use ($port, $normalize, $port4) {
    $m = new PHPMailer($exceptions);
    $m->isSMTP();
    $m->Host = '127.0.0.1';
    $m->Port = $port;
    $m->Timeout = 2;
    $m->setFrom('a@example.test');
    $m->addAddress('b@example.test');
    $m->Body = 'x';
    $lines = [];
    $m->SMTPDebug = 1;
    $m->Debugoutput = static function ($text, $level) use (&$lines, $normalize, $port4) { $lines[] = [$level, $port4($normalize(rtrim((string) $text)))]; };
    $setup($m);
    try {
        return [$m->send(), $m->ErrorInfo, $lines];
    } catch (Throwable $e) {
        return ['throws', $e->getMessage(), $e->getCode(), $m->ErrorInfo, $lines];
    }
};
$say('phpmailer smtp paths', [
    'starttls ex' => $run(true, static function ($m) { $m->SMTPSecure = 'tls'; }),
    'refused ex' => $run(true, static function ($m) { $m->Port = 1; }),
    'starttls' => $run(false, static function ($m) { $m->Host = '127.0.0.1:' . $m->Port; $m->SMTPSecure = 'tls'; }),
    'bad auth' => $run(false, static function ($m) { $m->SMTPAuth = true; $m->Username = 'probe'; $m->Password = 'nope'; }),
    'unknown authtype' => $run(false, static function ($m) { $m->SMTPAuth = true; $m->Username = 'probe'; $m->Password = 'secret'; $m->AuthType = 'NTLM'; }),
    'reject' => $run(false, static function ($m) { $m->addAddress('reject@example.test'); }),
    'all reject' => $run(false, static function ($m) { $m->clearAddresses(); $m->addAddress('reject1@example.test'); $m->addCC('reject2@example.test', 'R'); }),
    'sender' => $run(false, static function ($m) { $m->Sender = 'bounce@example.test'; }),
    'invalid host' => $run(false, static function ($m) { $m->Host = 'bad host!'; }),
    'tcp prefix' => $run(false, static function ($m) { $m->Host = 'tcp://127.0.0.1:' . $m->Port; $m->Port = 1; }),
    'host list' => $run(false, static function ($m) { $m->Host = 'bad host!;127.0.0.1:' . $m->Port . ';ignored.example'; $m->Port = 1; }),
    'helo' => $run(false, static function ($m) { $m->Helo = 'custom.helo'; }),
    'hostname' => $run(false, static function ($m) { $m->Hostname = 'host.name.test'; }),
    'verp' => $run(false, static function ($m) { $m->do_verp = true; }),
    'dsn' => $run(false, static function ($m) { $m->dsn = 'SUCCESS,FAILURE'; }),
    'single to' => $run(false, static function ($m) { $m->SingleTo = true; $m->addAddress('c@example.test'); }),
]);
$say('phpmailer action_function', (static function () use ($run) {
    $calls = [];
    $result = $run(false, static function ($m) use (&$calls) {
        $m->addCC('cc@example.test', 'C');
        $m->addBCC('bcc@example.test');
        $m->action_function = static function (...$args) use (&$calls) { $calls[] = array_map(static fn ($v) => is_array($v) && isset($v['smtp_transaction_id']) ? ['smtp_transaction_id' => preg_replace('/\d+/', 'N', (string) $v['smtp_transaction_id'])] : $v, $args); };
    });
    return [$result[0], $calls];
})());

// 3. wp_mail, routed to the server by a phpmailer_init hook.
$state = [];
add_action('phpmailer_init', static function ($phpmailer) use ($port, &$state): void {
    $phpmailer->isSMTP();
    $phpmailer->Host = '127.0.0.1';
    $phpmailer->Port = $port;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPAutoTLS = false;
    $state[] = [
        'class' => get_class($phpmailer),
        'global' => ($GLOBALS['phpmailer'] ?? null) === $phpmailer,
        'From' => $phpmailer->From, 'FromName' => $phpmailer->FromName, 'Sender' => $phpmailer->Sender,
        'CharSet' => $phpmailer->CharSet, 'ContentType' => $phpmailer->ContentType, 'Encoding' => $phpmailer->Encoding,
        'Subject' => $phpmailer->Subject, 'Body' => $phpmailer->Body, 'AltBody' => $phpmailer->AltBody, 'XMailer' => $phpmailer->XMailer,
        'to' => $phpmailer->getToAddresses(), 'cc' => $phpmailer->getCcAddresses(), 'bcc' => $phpmailer->getBccAddresses(), 'replyTo' => array_values($phpmailer->getReplyToAddresses()),
        'headers' => $phpmailer->getCustomHeaders(),
        'attachments' => array_map(static fn ($a) => [basename((string) $a[0]), $a[1], $a[2], $a[3], $a[4], $a[6], $a[7]], $phpmailer->getAttachments()),
        'Mailer' => $phpmailer->Mailer,
        'validator' => is_object(PHPMailer::$validator) ? get_debug_type(PHPMailer::$validator) : PHPMailer::$validator,
        'validates' => [$phpmailer->validateAddress('user@example.test'), $phpmailer->validateAddress('a@b'), $phpmailer->validateAddress('user@localhost')],
    ];
});
$events = [];
add_action('wp_mail_succeeded', static function ($data) use (&$events): void {
    $events[] = ['succeeded', array_keys($data), $data['to'], $data['subject'], $data['headers'], array_map('basename', (array) $data['attachments']), array_map('basename', (array) ($data['embeds'] ?? []))];
});
add_action('wp_mail_failed', static function ($error) use (&$events): void {
    $data = $error->get_error_data();
    $events[] = ['failed', $error->get_error_code(), $error->get_error_message(), array_keys((array) $data), $data['phpmailer_exception_code'] ?? null];
});
$say('wp_mail plain', [wp_mail('to@example.test', 'Plain wp_mail', 'Hello from wp_mail.'), array_pop($state), array_pop($events)]);
$say('wp_mail headers string', [wp_mail(['to@example.test', 'Second <second@example.test>'], 'Headers', '<p>HTML body</p>', "From: Custom Sender <custom@example.test>\r\nCc: cc@example.test, Cc Two <cc2@example.test>\r\nBcc: bcc@example.test\r\nReply-To: reply@example.test\r\nContent-Type: text/html; charset=UTF-8\r\nX-Custom: yes\r\n", [$fixtures . '/notes.txt', 'renamed.txt' => $fixtures . '/notes.txt']), array_pop($state), array_pop($events)]);
$say('wp_mail headers array', [wp_mail('to@example.test', 'Array headers', 'Body', ['From: Array Sender <array@example.test>', 'Content-Type: multipart/alternative; boundary="custom-boundary"', 'X-Two: 2']), array_pop($state), array_pop($events)]);
$filters = [
    'wp_mail_from' => static fn () => 'filtered@example.test',
    'wp_mail_from_name' => static fn () => 'Filtered Name',
    'wp_mail_content_type' => static fn () => 'text/html',
    'wp_mail_charset' => static fn () => 'ISO-8859-1',
];
foreach ($filters as $hook => $callback) {
    add_filter($hook, $callback);
}
$say('wp_mail filtered', [wp_mail('to@example.test', 'Filtered', 'Body'), array_pop($state), array_pop($events)]);
foreach ($filters as $hook => $callback) {
    remove_filter($hook, $callback);
}
$say('wp_mail rejected', [wp_mail('reject@example.test', 'Rejected', 'Body'), array_pop($state), array_pop($events)]);
$say('wp_mail invalid', [wp_mail('not-an-address', 'Invalid', 'Body'), array_pop($state), array_pop($events)]);
$say('wp_mail header shapes', [wp_mail('Name Only <named@example.test>', 'Shapes', 'Body', "from: plain@example.test\ncc: one@example.test\nCC: Two <two@example.test>\nReply-To: Replier <replier@example.test>, other@example.test\nContent-Type: text/html\nMIME-Version: 1.0\nX-Mailer: Something Else\nX-Empty:\nNoColonLine\nX-Multi: a\nX-Multi: b"), array_pop($state), array_pop($events)]);
$say('wp_mail from name only', [wp_mail('to@example.test', 'From shapes', 'Body', ['From: "Quoted, Name" <quoted@example.test>', 'Content-Type: text/plain; charset="iso-8859-1"']), array_pop($state), array_pop($events)]);
$say('wp_mail embeds', [wp_mail('to@example.test', 'Embeds', '<img src="cid:pix">', 'Content-Type: text/html', [], ['pix' => $fixtures . '/pixel.gif', $fixtures . '/pixel.gif']), array_pop($state), array_pop($events)]);
$say('wp_mail string attachments', [wp_mail('to@example.test', 'Attach string', 'Body', '', $fixtures . "/notes.txt\n" . $fixtures . '/pixel.gif'), array_pop($state), array_pop($events)]);
$say('wp_mail missing attachment', [wp_mail('to@example.test', 'Missing file', 'Body', '', ['/no/such/file.txt']), array_pop($state), array_pop($events)]);
$say('pre_wp_mail', (static function () {
    $short = static fn ($pre, $atts) => 'short-circuited:' . $atts['subject'];
    add_filter('pre_wp_mail', $short, 10, 2);
    $result = wp_mail('to@example.test', 'Never sent', 'Body');
    remove_filter('pre_wp_mail', $short, 10);
    return $result;
})());
$say('wp_mail args filter', (static function () use (&$state, &$events) {
    $filter = static fn ($atts) => array_merge($atts, ['subject' => 'Replaced subject', 'message' => 'Replaced body']);
    add_filter('wp_mail', $filter);
    $result = wp_mail('to@example.test', 'Original', 'Original body');
    remove_filter('wp_mail', $filter);
    return [$result, array_pop($state), array_pop($events)];
})());

// wp_mail's parsing at the edges: quoted names and commas, a boundary, folded and odd headers, and
// the content type filter, which is asked twice (once before the sender, once with any boundary).
$filterArgs = [];
$recordFilter = static function ($value) use (&$filterArgs) {
    $filterArgs[] = [current_filter(), $value];
    return $value;
};
foreach (['wp_mail_content_type', 'wp_mail_charset', 'wp_mail_from', 'wp_mail_from_name'] as $hook) {
    add_filter($hook, $recordFilter, 99);
}
$edge = static function (...$args) use (&$state, &$events, &$filterArgs) {
    $filterArgs = [];
    try {
        $result = wp_mail(...$args);
    } catch (Throwable $e) {
        $result = ['threw', get_class($e), $e->getMessage()];
    }
    $fields = ['From', 'FromName', 'ContentType', 'CharSet', 'to', 'cc', 'bcc', 'replyTo', 'headers', 'attachments', 'Subject', 'Body'];
    $last = array_pop($state);
    return [$result, $last === null ? null : array_intersect_key($last, array_flip($fields)), array_pop($events), $filterArgs];
};
$say('wp_mail edges', [
    'quoted names' => $edge('"Quoted Rcpt" <q@example.test>', 'S', 'B', "Cc: \"Q, C\" <qc@example.test>\nBcc: <bb@example.test>\nFrom: <noname@example.test>"),
    'boundary' => $edge('a@example.test', 'S', 'B', 'Content-Type: multipart/alternative; boundary="bnd"'),
    'charset params' => $edge('a@example.test', 'S', 'B', 'Content-Type: text/html; charset=utf-8; format=flowed'),
    'folded' => $edge('a@example.test', 'S', 'B', "X-Fold: a\r\n b\r\nX-After: c"),
    'to string list' => $edge('a@example.test, B <b@example.test>,c@example.test', 'S', 'B'),
    'attach string crlf' => $edge('a@example.test', 'S', 'B', '', "\r\n" . $fixtures . "/notes.txt\r\n\r\n" . $fixtures . '/pixel.gif'),
    'embeds odd' => $edge('a@example.test', 'S', '<p>x</p>', 'Content-Type: text/html', [], ['one' => $fixtures . '/pixel.gif', 5 => $fixtures . '/pixel.gif', 'gone' => '/no/such.gif']),
    'from bare' => $edge('a@example.test', 'S', 'B', ['From: bare@example.test', 'Reply-To: "R, S" <rs@example.test>']),
    'invalid from' => $edge('a@example.test', 'S', 'B', ['From: Bad <not-an-email>']),
    'empty body' => $edge('a@example.test', 'S', ''),
    'header value colon' => $edge('a@example.test', 'S', 'B', "X-Url: http://example.test/a:b\nSubject: ignored?\nTo: extra@example.test"),
]);
$throwing = static function () {
    throw new PHPMailer\PHPMailer\Exception('from init', 7);
};
add_action('phpmailer_init', $throwing, 100);
$say('wp_mail init throws', $edge('a@example.test', 'S', 'B'));
remove_action('phpmailer_init', $throwing, 100);
foreach (['wp_mail_content_type', 'wp_mail_charset', 'wp_mail_from', 'wp_mail_from_name'] as $hook) {
    remove_filter($hook, $recordFilter, 99);
}

// The server writes each session when it closes; give the last one a moment.
usleep(300000);
$sessions = [];
foreach (glob("{$dir}/session-*.json") ?: [] as $file) {
    $sessions[(int) preg_replace('/\D/', '', basename($file))] = array_map(static fn ($row) => [$row[0], $normalize((string) $row[1])], (array) json_decode((string) file_get_contents($file), true));
}
ksort($sessions);
$say('sessions', array_values($sessions));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
