<?php
/**
 * PHPMailer as plugins drive it, without sending: its defaults and
 * constants, address checks and parsing, the header and body encoders,
 * line wrapping, the helpers, the messages it composes for each kind of
 * mail (with a fixed Message-ID and date, boundaries normalized), and its
 * errors. Same protocol as api-probe.php; run outside WP-CLI on both
 * stacks (tests/tools/run-reference-probe.php, run-api-probe.php).
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$fixtures = dirname(__DIR__) . '/fixtures/mail';
$try = static function (callable $fn) {
    try {
        return ['ok', $fn()];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage(), $e instanceof MailerException ? $e->errorMessage() : null];
    }
};
// Boundaries and generated ids change per message; fixed placeholders keep the shape comparable.
$normalize = static fn (string $mime): string => (string) preg_replace(['/b([123])=_[A-Za-z0-9]+/', '/(PHPMailer )[0-9.]+/'], ['b$1=_ID', '$1V'], $mime);
$compose = static function (callable $setup) use ($normalize, $fixtures) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->MessageID = '<probe-id@example.test>';
    $mail->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->addAddress('to@example.test', 'First Recipient');
    $setup($mail, $fixtures);
    try {
        $ok = $mail->preSend();
        return [$ok, $mail->ErrorInfo, $normalize($mail->getSentMIMEMessage())];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage(), $mail->ErrorInfo];
    }
};

$fresh = new PHPMailer();
$say('defaults', array_map(static fn ($v) => is_object($v) ? get_class($v) : $v, array_diff_key(get_object_vars($fresh), ['XMailer' => 1])));
$say('constants', (new ReflectionClass(PHPMailer::class))->getConstants());
$say('static validator', PHPMailer::$validator);
$say('le', PHPMailer::getLE());

$addresses = ['user@example.com', 'User@Example.COM', 'first.last+tag@sub.example.co.uk', 'a@b', 'a@b.c', 'no-at.example.com', 'two@@example.com', '"quoted name"@example.com', 'user@[127.0.0.1]', 'user@localhost', 'ü@example.com', 'user@bücher.example', ' user@example.com', 'user@example.com.', 'user..dots@example.com', str_repeat('a', 65) . '@example.com', 'user@' . str_repeat('a', 64) . '.com', ''];
foreach (['php', 'pcre', 'pcre8', 'html5', 'noregex'] as $pattern) {
    $say("validateAddress {$pattern}", array_map(static fn ($a) => PHPMailer::validateAddress($a, $pattern), $addresses));
}
$say('validateAddress callable', array_map(static fn ($a) => PHPMailer::validateAddress($a, static fn ($x) => str_ends_with($x, '.com')), ['a@b.com', 'a@b.org']));
$say('idnSupported', PHPMailer::idnSupported());
$say('punyencodeAddress', array_map(static fn ($a) => (new PHPMailer())->punyencodeAddress($a), ['user@bücher.example', 'user@example.com', 'ü@example.com']));
$say('parseAddresses', array_map(static fn ($list) => PHPMailer::parseAddresses($list, false), ['a@example.com, "B Name" <b@example.com>, C <c@example.com>', 'Bad, d@example.com', '=?utf-8?Q?J=C3=BCrgen?= <j@example.com>', '', 'x@y.z; w@v.u']));
$say('parseAddresses imap', array_map(static fn ($list) => PHPMailer::parseAddresses($list, true), ['a@example.com, "B Name" <b@example.com>']));

$recipients = static function () use ($try) {
    $mail = new PHPMailer();
    $results = [];
    foreach ([['addAddress', 'one@example.com', 'One'], ['addAddress', 'one@example.com', 'Again'], ['addAddress', 'ONE@example.com', ''], ['addAddress', 'bad', 'Bad'], ['addCC', 'cc@example.com', 'C C'], ['addBCC', 'bcc@example.com', ''], ['addReplyTo', 'reply@example.com', 'Reply'], ['addReplyTo', 'reply@example.com', 'Reply2'], ['addAddress', "evil@example.com\r\nBcc: x@y.z", ''], ['addAddress', 'name@example.com', "Name\r\nInjected"]] as [$method, $address, $name]) {
        $results[] = [$method, $address, $mail->{$method}($address, $name), $mail->ErrorInfo];
    }
    return [$results, $mail->getToAddresses(), $mail->getCcAddresses(), $mail->getBccAddresses(), $mail->getReplyToAddresses(), $mail->getAllRecipientAddresses()];
};
$say('recipients', $recipients());
$say('recipients throwing', [
    $try(static fn () => (new PHPMailer(true))->addAddress('bad')),
    $try(static fn () => (new PHPMailer(true))->setFrom('bad', 'x')),
    $try(static fn () => (new PHPMailer(true))->addAttachment('/no/such/file')),
    $try(static fn () => (new PHPMailer(true))->addAddress('a@example.com', '', 'nonsense-kind')),
]);
$say('setFrom', (static function () {
    $mail = new PHPMailer();
    return [$mail->setFrom('from@example.com', 'From Name'), $mail->From, $mail->FromName, $mail->Sender, $mail->setFrom('other@example.com', 'Other', false), $mail->Sender, $mail->setFrom('nope', 'x'), $mail->ErrorInfo, $mail->From];
})());

$texts = ['Plain ascii', 'Ünïcödé text', 'A very long subject line that goes well past the seventy-six character limit for an encoded word so that it must fold', "Line\nbreaks", 'Quotes "and" =?equals?= _under_', ''];
$say('encodeHeader text', array_map(static fn ($t) => (static function () use ($t) { $m = new PHPMailer(); $m->CharSet = 'utf-8'; return $m->encodeHeader($t); })(), $texts));
$say('encodeHeader phrase', array_map(static fn ($t) => (static function () use ($t) { $m = new PHPMailer(); $m->CharSet = 'utf-8'; return $m->encodeHeader($t, 'phrase'); })(), ['Name, With Comma', 'Plain Name', 'Ünïcödé Name', 'Name (comment)']));
$say('encodeHeader latin1', (new PHPMailer())->encodeHeader('Ünïcödé'));
$say('encodeQP', array_map(static fn ($t) => (new PHPMailer())->encodeQP($t), ["Simple text", "Ünïcödé =equals= and tab\there", str_repeat('word ', 30), "trailing space \nnext", "\r\nCRLF"]));
$say('encodeQ', array_map(static fn ($t) => [(new PHPMailer())->encodeQ($t), (new PHPMailer())->encodeQ($t, 'phrase'), (new PHPMailer())->encodeQ($t, 'comment')], ['Ünïcödé text', 'a_b c=d?e(f)"g', 'plain']));
$say('encodeString', array_map(static fn ($enc) => $try(static fn () => (new PHPMailer())->encodeString("Ünïcödé line\nsecond line " . str_repeat('x', 80), $enc)), ['7bit', '8bit', 'binary', 'base64', 'quoted-printable', 'nonsense']));
$say('base64EncodeWrapMB', (static function () { $m = new PHPMailer(); $m->CharSet = 'utf-8'; return $m->base64EncodeWrapMB(str_repeat('Ünïcödé ', 12)); })());
$say('wrapText', array_map(static fn ($args) => (static function () use ($args) { $m = new PHPMailer(); $m->CharSet = $args[2]; return $m->wrapText($args[0], $args[1], $args[3]); })(), [[str_repeat('word ', 30), 40, 'utf-8', false], [str_repeat('Ünïcödé ', 12), 30, 'utf-8', false], ['short', 40, 'utf-8', false], [str_repeat('averyveryverylongwordwithoutspaces', 3), 40, 'utf-8', false], [str_repeat('quoted printable soft ', 6), 30, 'iso-8859-1', true], ["para one\n\npara two " . str_repeat('x ', 30), 30, 'utf-8', false]]));
$say('utf8CharBoundary', array_map(static fn ($n) => (new PHPMailer())->utf8CharBoundary('ab=C3=BCcd=E2=82=ACef', $n), [1, 3, 4, 6, 9, 12]));
$say('multibyte checks', [(new PHPMailer())->hasMultiBytes('ascii'), (static function () { $m = new PHPMailer(); $m->CharSet = 'utf-8'; return $m->hasMultiBytes('Ünï'); })(), (new PHPMailer())->has8bitChars('ascii'), (new PHPMailer())->has8bitChars('Ünï')]);
$say('helpers', [
    (new PHPMailer())->addrFormat(['a@example.com', 'Name, With "Quotes"']),
    (new PHPMailer())->addrFormat(['a@example.com', '']),
    (new PHPMailer())->addrFormat(['a@example.com', 'Ünï']),
    (new PHPMailer())->addrAppend('To', [['a@example.com', 'A'], ['b@example.com', '']]),
    (new PHPMailer())->secureHeader("Subject\r\nBcc: x"),
    PHPMailer::normalizeBreaks("a\r\nb\rc\nd"),
    PHPMailer::normalizeBreaks("a\nb", "\r\n"),
    PHPMailer::stripTrailingWSP("text \t \n"),
    PHPMailer::stripTrailingBreaks("text\r\n\r\n"),
    PHPMailer::quotedString('a "quoted" word'),
    PHPMailer::quotedString('plain'),
    preg_match('/^[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} \d\d:\d\d:\d\d [+-]\d{4}$/', PHPMailer::rfcDate()),
    array_map([PHPMailer::class, 'isValidHost'], ['example.com', 'localhost', '127.0.0.1', '[::1]', 'bad_host!', str_repeat('a', 256), '']),
    (new PHPMailer())->headerLine('X-Test', 'value'),
    (new PHPMailer())->textLine('text'),
    PHPMailer::filenameToType('photo.JPG'),
    PHPMailer::filenameToType('archive.tar.gz'),
    PHPMailer::filenameToType('noext'),
    PHPMailer::_mime_types('pdf'),
    PHPMailer::_mime_types('unknownext'),
    PHPMailer::mb_pathinfo('/path/to/fïle.name.txt'),
    PHPMailer::mb_pathinfo('/path/to/fïle.name.txt', 'filename'),
    PHPMailer::mb_pathinfo('/path/to/fïle.name.txt', PATHINFO_EXTENSION),
    PHPMailer::hasLineLongerThanMax(str_repeat('x', 999)),
    PHPMailer::hasLineLongerThanMax(str_repeat('x', 997)),
]);
$say('html2text', [(new PHPMailer())->html2text('<p>Hello <b>world</b> &amp; friends</p><style>p{}</style><script>x()</script><br>Line'), (new PHPMailer())->html2text('<p>A</p>', static fn ($h) => strtoupper(strip_tags($h)))]);
$say('msgHTML', (static function () use ($fixtures, $normalize) {
    $mail = new PHPMailer();
    $body = $mail->msgHTML('<html><head><title>T</title></head><body><p>Hi <img src="pixel.gif" alt="p"> and <img src="https://example.test/remote.png"> and <img src="data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=="></p></body></html>', $fixtures . '/');
    return [$normalize($body), $mail->AltBody, $mail->ContentType, array_map(static fn ($a) => [$a[1], $a[2], $a[4], $a[6], $normalize((string) $a[7])], $mail->getAttachments())];
})());
$say('custom headers', (static function () {
    $mail = new PHPMailer();
    $a = [$mail->addCustomHeader('X-One', 'one'), $mail->addCustomHeader('X-Two: two'), $mail->addCustomHeader("X-Bad\r\n: x", 'v'), $mail->addCustomHeader('X-One', 'again')];
    $b = $mail->getCustomHeaders();
    $mail->replaceCustomHeader('X-One', 'replaced');
    $c = $mail->getCustomHeaders();
    $mail->clearCustomHeader('X-Two');
    return [$a, $b, $c, $mail->getCustomHeaders()];
})());
$say('attachments api', (static function () use ($fixtures) {
    $mail = new PHPMailer();
    return [
        $mail->addAttachment($fixtures . '/notes.txt'),
        $mail->addAttachment($fixtures . '/notes.txt', 'renamed.txt', 'quoted-printable', 'text/plain'),
        $mail->addStringAttachment('raw data', 'data.bin'),
        $mail->addEmbeddedImage($fixtures . '/pixel.gif', 'pix1'),
        $mail->addStringEmbeddedImage('GIF89a', 'pix2', 'p2.gif'),
        $mail->addAttachment($fixtures . '/notes.txt', 'x.txt', 'bogus-encoding'),
        $mail->ErrorInfo,
        $mail->attachmentExists(), $mail->inlineImageExists(), $mail->alternativeExists(),
        array_map(static fn ($a) => [basename((string) $a[0]) === 'notes.txt' || basename((string) $a[0]) === 'pixel.gif' ? basename((string) $a[0]) : ($a[5] ? 'string' : $a[0]), $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7]], $mail->getAttachments()),
    ];
})());

$say('message plain', $compose(static function ($m) { $m->Subject = 'Plain subject'; $m->Body = "Hello there.\nSecond line."; }));
$say('message utf8', $compose(static function ($m) { $m->CharSet = PHPMailer::CHARSET_UTF8; $m->Subject = 'Ünïcödé subject that is fairly long so that the encoded word has to fold over'; $m->Body = "Ünïcödé body.\n" . str_repeat('long line ', 120); }));
$say('message html alt', $compose(static function ($m) { $m->isHTML(true); $m->Subject = 'HTML'; $m->Body = '<p>Hello <b>HTML</b></p>'; $m->AltBody = 'Hello text'; }));
$say('message attachments', $compose(static function ($m, $f) { $m->Subject = 'Files'; $m->Body = 'See attached.'; $m->addAttachment($f . '/notes.txt'); $m->addStringAttachment("a,b\n1,2\n", 'data.csv', PHPMailer::ENCODING_BASE64, 'text/csv'); }));
$say('message inline', $compose(static function ($m, $f) { $m->isHTML(true); $m->Subject = 'Inline'; $m->Body = '<img src="cid:pix">'; $m->AltBody = 'alt'; $m->addEmbeddedImage($f . '/pixel.gif', 'pix', 'pixel.gif'); $m->addAttachment($f . '/notes.txt'); }));
$say('message headers', $compose(static function ($m) {
    $m->Subject = 'Headers';
    $m->Body = 'Body';
    $m->addCC('cc@example.test', 'C C');
    $m->addBCC('bcc@example.test');
    $m->addReplyTo('reply@example.test', 'Reply Name');
    $m->addCustomHeader('X-Custom', 'yes');
    $m->Priority = 1;
    $m->ConfirmReadingTo = 'read@example.test';
    $m->Sender = 'bounce@example.test';
    $m->XMailer = 'Probe Mailer';
    $m->Hostname = 'mail.example.test';
}));
$say('message ical', $compose(static function ($m) { $m->Subject = 'Invite'; $m->Body = 'Invite body'; $m->Ical = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nEND:VCALENDAR"; }));
$say('message encodings', array_map(static fn ($enc) => $compose(static function ($m) use ($enc) { $m->CharSet = 'utf-8'; $m->Encoding = $enc; $m->Subject = 'Enc'; $m->Body = "Ünïcödé\n" . str_repeat('y', 120); }), ['7bit', '8bit', 'base64', 'quoted-printable']));
$say('message related only', $compose(static function ($m, $f) { $m->isHTML(true); $m->Subject = 'Related'; $m->Body = '<img src="cid:pix">'; $m->addEmbeddedImage($f . '/pixel.gif', 'pix'); }));
$say('message html attach', $compose(static function ($m, $f) { $m->isHTML(true); $m->Subject = 'HTML attach'; $m->Body = '<p>See file</p>'; $m->addAttachment($f . '/notes.txt'); }));
$say('message alt attach', $compose(static function ($m, $f) { $m->isHTML(true); $m->Subject = 'Alt attach'; $m->Body = '<p>Hi</p>'; $m->AltBody = 'Hi'; $m->addAttachment($f . '/notes.txt', 'my notes ü.txt'); }));
$say('message ical alt', $compose(static function ($m) { $m->isHTML(true); $m->Subject = 'Invite'; $m->Body = '<p>Invite</p>'; $m->AltBody = 'Invite'; $m->Ical = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nEND:VCALENDAR"; }));
$say('message ical unknown method', $compose(static function ($m) { $m->isHTML(true); $m->Subject = 'Invite'; $m->Body = '<p>Invite</p>'; $m->AltBody = 'Invite'; $m->Ical = "BEGIN:VCALENDAR\r\nMETHOD:BOGUS\r\nEND:VCALENDAR"; }));
$say('message utf8 parts', $compose(static function ($m, $f) { $m->CharSet = 'utf-8'; $m->isHTML(true); $m->Subject = 'Ü'; $m->Body = '<p>Ünï</p>'; $m->AltBody = 'Ünï ' . str_repeat('z', 1000); $m->addStringAttachment('Ü data', 'ü.txt', 'quoted-printable', 'text/plain', 'inline'); }));
$say('message odd names', $compose(static function ($m, $f) { $m->Subject = 'Names'; $m->Body = 'x'; $m->addStringAttachment('a', 'name with spaces.txt'); $m->addStringAttachment('b', 'quote"name.txt'); $m->addStringAttachment('c', 'semi;colon.txt', 'base64', '', 'inline'); $m->addStringAttachment('d', '', 'base64', 'application/x-custom'); $m->addStringAttachment('e', 'seven.txt', '7bit'); }));
$say('message html no alt', $compose(static function ($m) { $m->isHTML(true); $m->Subject = 'HTML only'; $m->Body = "<p>One</p>\n<p>Two</p>"; }));
$say('message empty subject', $compose(static function ($m) { $m->Subject = ''; $m->Body = 'x'; $m->FromName = ''; }));
$say('message transports', array_map(static function ($mailer) use ($normalize) {
    $mail = new PHPMailer(true);
    $mail->Mailer = $mailer;
    $mail->MessageID = '<probe-id@example.test>';
    $mail->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->addAddress('to@example.test', 'To Name');
    $mail->addCC('cc@example.test', 'Cc Name');
    $mail->addBCC('bcc@example.test', 'Bcc Name');
    $mail->addReplyTo('reply@example.test');
    $mail->Subject = 'Transport ' . $mailer;
    $mail->Body = 'Body';
    $mail->preSend();
    return [$mailer, $normalize($mail->getSentMIMEMessage()), $normalize($mail->getMailMIME())];
}, ['mail', 'sendmail', 'qmail', 'smtp']));
$say('message generated ids', (static function () {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Hostname = 'host.example.test';
    $mail->setFrom('sender@example.test');
    $mail->addAddress('to@example.test');
    $mail->Subject = 'Ids';
    $mail->Body = 'x';
    $mail->preSend();
    $mime = $mail->getSentMIMEMessage();
    preg_match('/^Message-ID: (.*)$/m', $mime, $id);
    preg_match('/^Date: (.*)$/m', $mime, $date);
    return [preg_replace('/^<[A-Za-z0-9]+@/', '<ID@', trim($id[1] ?? '')), preg_match('/^[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4} \d\d:\d\d:\d\d [+-]\d{4}$/', trim($date[1] ?? '')), $mail->getLastMessageID() === trim($id[1] ?? '')];
})());
$say('message mail transport', (static function () use ($normalize) {
    $mail = new PHPMailer(true);
    $mail->MessageID = '<probe-id@example.test>';
    $mail->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->addAddress('to@example.test');
    $mail->Subject = 'Via mail()';
    $mail->Body = 'Body';
    $mail->preSend();
    return [$normalize($mail->getSentMIMEMessage()), $normalize($mail->getMailMIME()), $mail->getLastMessageID()];
})());
$say('message errors', [
    $compose(static function ($m) { $m->clearAllRecipients(); $m->Subject = 'x'; $m->Body = 'y'; }),
    $compose(static function ($m) { $m->Subject = 'x'; $m->Body = ''; }),
    $compose(static function ($m) { $m->Subject = 'x'; $m->Body = ''; $m->AllowEmpty = true; }),
    $compose(static function ($m) { $m->Subject = 'x'; $m->Body = 'y'; $m->From = 'not-an-address'; }),
    $compose(static function ($m) { $m->Subject = 'x'; $m->Body = 'y'; $m->Encoding = 'nonsense'; }),
    $compose(static function ($m) { $m->Subject = 'x'; $m->Body = 'y'; $m->Mailer = 'nonsense'; }),
]);
$say('no-throw errors', (static function () {
    $mail = new PHPMailer(false);
    $mail->Subject = 'x';
    $mail->Body = 'y';
    return [$mail->preSend(), $mail->ErrorInfo, $mail->isError()];
})());
$say('language', [PHPMailer::setLanguage('en'), array_keys((new PHPMailer())->getTranslations())]);
$say('translations', (new PHPMailer())->getTranslations());
$say('exception', (static function () { $e = new MailerException('Something <b>bad</b>'); return [$e->getMessage(), $e->errorMessage()]; })());
$say('smtp instance', (static function () {
    $mail = new PHPMailer();
    $smtp = $mail->getSMTPInstance();
    return [get_class($smtp), $smtp === $mail->getSMTPInstance(), $smtp->getTimeout(), $smtp->getDebugLevel(), $smtp->getVerp(), $smtp->connected(), $smtp->getError(), $smtp->getLastReply(), $smtp->getServerExtList()];
})());
$say('dkim', (static function () {
    $mail = new PHPMailer();
    return [$mail->DKIM_QP("a=b; c\r\nd\te"), $mail->DKIM_HeaderC("Subject:  Some   Thing \r\nFrom: A <a@b>\r\n"), $mail->DKIM_BodyC("line one  \r\nline two\t\r\n\r\n\r\n"), $mail->DKIM_BodyC('')];
})());

// The given date is kept (reformatted, its offset kept) only when it is a moment already past; the generated
// date and id are normalized ("(now)", "GEN").
$now = static fn (?string $date): ?string => $date !== null && ($t = strtotime($date)) !== false && abs($t - time()) <= 10 ? '(now)' : $date;
$headerOf = static fn (string $mime, string $name): ?string => preg_match('/^' . $name . ': (.*)\r?$/m', $mime, $m) ? rtrim($m[1], "\r") : null;
$quick = static function (callable $setup) use ($normalize) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->addAddress('to@example.test');
    $mail->Subject = 'S';
    $mail->Body = 'B';
    $mail->MessageID = '<probe-id@example.test>';
    $mail->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
    $setup($mail);
    $mail->preSend();
    return [$normalize($mail->getSentMIMEMessage()), $mail];
};
$dates = ['Mon, 05 Oct 2026 12:00:00 +0200', '2026-10-05 12:00:00', '@1791201600', '1990-01-01 00:00:00', '1969-12-31 23:59:59 +0000', 'Mon, 05 Oct 2026 12:00:00 Europe/Paris', '05 Oct 26 12:00:00 +0000', 'garbage', '', 'now', 'tomorrow', '9999-12-31', '1791201600', 'a minute ahead' => gmdate('Y-m-d H:i:s', time() + 60) . ' +0000'];
$say('message dates', array_map(static fn ($d) => $now($headerOf($quick(static function ($m) use ($d) { $m->MessageDate = $d; })[0], 'Date')), $dates));
$say('message ids', array_map(static function ($id) use ($quick, $headerOf) {
    [$mime, $mail] = $quick(static function ($m) use ($id) { $m->MessageID = $id; });
    return [$id, preg_replace('/^<[A-Za-z0-9]{20,}@/', '<GEN@', (string) $headerOf($mime, 'Message-ID')), preg_replace('/^<[A-Za-z0-9]{20,}@/', '<GEN@', $mail->getLastMessageID())];
}, ['<a@b>', 'a@b', '<bad>', '<a b@c>', '<a@b@c>', '', '<x@[127.0.0.1]>', '<x.y+z@host-name.example>']));
$say('message types', array_map(static fn ($setup) => $quick($setup)[0], [
    'plain alt' => static function ($m) { $m->AltBody = 'alt body'; },
    'alt inline ical' => static function ($m) use ($fixtures) { $m->isHTML(true); $m->Body = '<p>x</p>'; $m->AltBody = 'x'; $m->Ical = "BEGIN:VCALENDAR\r\nMETHOD:PUBLISH\r\nEND:VCALENDAR"; $m->addEmbeddedImage($fixtures . '/pixel.gif', 'pix'); },
    'alt inline attach ical' => static function ($m) use ($fixtures) { $m->isHTML(true); $m->Body = '<p>x</p>'; $m->AltBody = 'x'; $m->Ical = "BEGIN:VCALENDAR\r\nMETHOD:cancel\r\nEND:VCALENDAR"; $m->addEmbeddedImage($fixtures . '/pixel.gif', 'pix'); $m->addStringAttachment('a', 'a.txt'); },
    'alt attach ical lower' => static function ($m) { $m->isHTML(true); $m->Body = '<p>x</p>'; $m->AltBody = 'x'; $m->Ical = "BEGIN:VCALENDAR\nMETHOD:reply\nEND:VCALENDAR"; $m->addStringAttachment('a', 'a.txt'); },
    'binary' => static function ($m) { $m->Encoding = 'binary'; $m->Body = "bin\r\nbody"; },
    'dupes' => static function ($m) use ($fixtures) { $m->addStringAttachment('a', 'a.txt'); $m->addStringAttachment('a', 'a.txt'); $m->addEmbeddedImage($fixtures . '/pixel.gif', 'pix'); $m->addEmbeddedImage($fixtures . '/pixel.gif', 'pix', 'other.gif'); $m->addStringEmbeddedImage('GIF', 'pix', 'p.gif'); },
    'headers odd' => static function ($m) { $m->addCustomHeader('X-Utf', 'Ünï value'); $m->addCustomHeader('X-Long', str_repeat('word ', 220)); $m->addCustomHeader('X-Empty', ''); $m->Priority = 0; $m->XMailer = ' '; $m->CharSet = 'utf-8'; },
    'priority xmailer trim' => static function ($m) { $m->Priority = 5; $m->XMailer = '  Spaced  '; $m->ConfirmReadingTo = '  r@x.y '; },
    'from names' => static function ($m) { $m->FromName = 'Name, With Comma'; $m->addCC('c@x.y', 'Ünï'); $m->addReplyTo('r@x.y', "O'Brien"); },
    'subject trims' => static function ($m) { $m->Subject = "  Spaced\r\n subject  "; },
    'cc only' => static function ($m) { $m->clearAddresses(); $m->addCC('c@x.y'); },
    'bcc only' => static function ($m) { $m->clearAddresses(); $m->addBCC('b@x.y'); },
    'sendmail bcc only' => static function ($m) { $m->isSendmail(); $m->clearAddresses(); $m->addBCC('b@x.y'); },
    'single to' => static function ($m) { $m->SingleTo = true; $m->addAddress('c@example.test'); },
]));
$say('message file gone', (static function () {
    $tmp = (string) tempnam(sys_get_temp_dir(), 'minn-mail');
    file_put_contents($tmp, 'x');
    $mail = new PHPMailer(true);
    $mail->setFrom('a@b.c');
    $mail->addAddress('d@e.f');
    $mail->Body = 'x';
    $mail->addAttachment($tmp, 'gone.txt');
    unlink($tmp);
    try {
        return [$mail->preSend()];
    } catch (Throwable $e) {
        return ['throws', str_replace($tmp, 'TMP', $e->getMessage()), str_replace($tmp, 'TMP', $mail->ErrorInfo)];
    }
})());
$html = static function (string $h, string $base = '') use ($fixtures) {
    $m = new PHPMailer();
    $body = $m->msgHTML($h, $base);
    return [$body, $m->AltBody, array_map(static fn ($a) => [is_file((string) $a[0]) ? str_replace($fixtures, 'FX', (string) $a[0]) : 'DATA:' . md5((string) $a[0]), $a[1], $a[2], $a[4], $a[5], $a[6], $a[7]], $m->getAttachments())];
};
$say('msgHTML cases', [
    $html('<img src="pixel.gif">', $fixtures),
    $html('<p>x <IMG SRC="pixel.gif"></p>', $fixtures),
    $html('<img src="mail/pixel.gif">', dirname($fixtures)),
    $html('<img src="../mail/pixel.gif">', $fixtures . '/'),
    $html('<img src="' . $fixtures . '/pixel.gif">'),
    $html("<img src='//cdn.example/x.gif'><img src=\"cid:already\"><img src=\"nope.gif\">", $fixtures),
    $html('<td background="pixel.gif">x</td><img src="pixel.gif">', $fixtures),
    $html('<img src="data:image/png,%89PNG"><img src="data:image/svg+xml;base64,PHN2Zy8+"><img src="data:text/html;base64,PGI+">'),
    $html('<a href="mailto:x@y.z"><img src="file:///etc/passwd"></a>'),
    (static function () use ($html, $fixtures) {
        // No base directory: a relative path is not read from the working directory.
        $cwd = (string) getcwd();
        chdir($fixtures);
        $result = $html('<img src="pixel.gif"><img src="notes.txt">');
        chdir($cwd);
        return $result;
    })(),
    $html('<img src="notes.txt"><img src="./pixel.gif"><img src="pixel.gif?v=1">', $fixtures),
    $html('<img src="mail/../mail/pixel.gif">', dirname($fixtures)),
    $html("<html><head><style>p{}</style><title>T</title></head><body>A &nbsp; B &lt;x&gt; &#8364; <b>c</b>\n\n\nD</body></html>"),
]);
$say('decodeHeader', [PHPMailer::decodeHeader('=?utf-8?Q?J=C3=BCrgen?='), PHPMailer::decodeHeader('=?utf-8?Q?J=C3=BCrgen?=', 'utf-8'), PHPMailer::decodeHeader('=?utf-8?B?w5xuw68=?= =?utf-8?B?IHRleHQ=?=', 'utf-8'), PHPMailer::decodeHeader('plain text'), PHPMailer::decodeHeader('a =?iso-8859-1?Q?=FC?= b', 'utf-8'), PHPMailer::decodeHeader('=?utf-8?Q?a_b?=', 'utf-8')]);
$say('pathinfo cases', [PHPMailer::mb_pathinfo('fïle.txt'), PHPMailer::mb_pathinfo('/dir/'), PHPMailer::mb_pathinfo('noext'), PHPMailer::mb_pathinfo('C:\\dir\\file.txt'), PHPMailer::mb_pathinfo('/a/b.c', 'dirname'), PHPMailer::mb_pathinfo('/a/b.c', PATHINFO_BASENAME), PHPMailer::mb_pathinfo('/a/b.c', 'bogus'), PHPMailer::mb_pathinfo('.hidden')]);
$say('types and hosts', [[PHPMailer::filenameToType('a.png?x=1'), PHPMailer::filenameToType('A.PDF'), PHPMailer::_mime_types('PDF'), PHPMailer::_mime_types('')], array_map([PHPMailer::class, 'isValidHost'], ['a', 'a.b', '-a.com', 'a-.com', 'a..b', '1.2.3.4', '999.1.1.1', '[1.2.3.4]', '[::ffff:1.2.3.4]', '[zz]', 'ex_ample.com', 'xn--bcher-kva.example', str_repeat('a', 63) . '.com', str_repeat('a', 64) . '.com', 'a.b.', 5])]);
$say('address details', [
    (static function () { $m = new PHPMailer(); $m->Sender = 'keep@x.y'; $m->setFrom('a@b.c'); $m2 = new PHPMailer(); $m2->setFrom('a@b.c', '', false); return [$m->Sender, $m2->Sender]; })(),
    (static function () { $m = new PHPMailer(); return [$m->addCC('bad'), $m->ErrorInfo, $m->addBCC('bad2'), $m->ErrorInfo, $m->addReplyTo('bad3'), $m->ErrorInfo, $m->addReplyTo('R@x.y', 'R'), $m->addReplyTo('r@X.y', 'r2'), $m->getReplyToAddresses()]; })(),
    (static function () { $m = new PHPMailer(); $m->CharSet = 'utf-8'; $a = $m->addAddress('user@bücher.example', 'U'); $b = $m->addAddress('user@bücher.example'); $c = $m->addReplyTo('r@bücher.example'); $before = [$m->getToAddresses(), $m->getReplyToAddresses(), $m->getAllRecipientAddresses()]; $m->setFrom('from@bücher.example'); $from = $m->From; $m->Body = 'x'; $m->isSMTP(); $m->preSend(); return [$a, $b, $c, $before, $from, $m->From, $m->getToAddresses(), $m->getReplyToAddresses(), $m->getAllRecipientAddresses(), $m->needsSMTPUTF8()]; })(),
]);
$say('state details', [
    (static function () { $m = new PHPMailer(); return [$m->set('Subject', 'x'), $m->Subject, $m->set('Nope', 'y'), $m->ErrorInfo]; })(),
    (static function () { $m = new PHPMailer(); $m->addAddress('a@b.c'); $m->addCC('c@b.c'); $m->addBCC('d@b.c'); $m->clearCCs(); $r1 = $m->getAllRecipientAddresses(); $m->clearAddresses(); $r2 = $m->getAllRecipientAddresses(); $m->addCustomHeader('X-A', '1'); $m->addCustomHeader('X-A', '2'); $m->addCustomHeader('X-B', '1'); $m->clearCustomHeader('X-A', '2'); $h1 = $m->getCustomHeaders(); $m->clearCustomHeader('X-B: 1'); $h2 = $m->getCustomHeaders(); $m->replaceCustomHeader('X-New', 'n'); $h3 = $m->getCustomHeaders(); return [$r1, $r2, $h1, $h2, $h3, $m->replaceCustomHeader("X-Bad\n", 'v'), $m->ErrorInfo]; })(),
    (static function () { $m = new PHPMailer(); return [$m->setSMTPXclientAttribute('ADDR', '1.2.3.4'), $m->setSMTPXclientAttribute('BAD', 'x'), $m->setSMTPXclientAttribute('NAME', 'n'), $m->setSMTPXclientAttribute('NAME', null), $m->getSMTPXclientAttributes()]; })(),
    (static function () { $m = new PHPMailer(); $m->CharSet = 'utf-8'; $m->Mailer = 'mail'; return [$m->encodeHeader(str_repeat('Ünïcödé ', 10)), $m->encodeHeader(str_repeat('a', 80))]; })(),
    (static function () { $m = new PHPMailer(); $r = new ReflectionMethod($m, 'lang'); return [$r->invoke($m, 'nope'), $r->invoke($m, 'authenticate')]; })(),
]);
$say('dkim static', (static function () use ($fixtures) {
    $mail = new PHPMailer(true);
    $mail->DKIM_domain = 'example.test';
    $mail->DKIM_selector = 'sel';
    $mail->DKIM_private = $fixtures . '/dkim-test.key';
    return $mail->DKIM_Sign("from:a@b\r\nsubject:x");
})());
// The signature changes with the second it is made in (t=); the suite checks the bytes against a capture.
$say('dkim message', (static function () use ($fixtures, $normalize) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->MessageID = '<probe-id@example.test>';
    $mail->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
    $mail->setFrom('sender@example.test', 'Probe Sender');
    $mail->addAddress('to@example.test');
    $mail->addCustomHeader('X-Extra', 'signed');
    $mail->Subject = 'Signed';
    $mail->Body = "Signed body.\n";
    $mail->DKIM_domain = 'example.test';
    $mail->DKIM_selector = 'sel';
    $mail->DKIM_identity = 'sender@example.test';
    $mail->DKIM_extraHeaders = ['X-Extra'];
    $mail->DKIM_private_string = (string) file_get_contents($fixtures . '/dkim-test.key');
    $mail->preSend();
    return preg_replace(['/t=\d+;/', '/ b=[A-Za-z0-9+\/=\s]+?(\r?\n\r?\n)/'], ['t=T;', ' b=SIG$1'], $normalize($mail->getSentMIMEMessage()));
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
