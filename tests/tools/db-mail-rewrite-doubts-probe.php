<?php
/**
 * The database, mail, rewrite and upgrader doubts (probe db-mail-rewrite-doubts):
 * a wpdb subclass's construction and the calls its readers and writers make,
 * the readers with no query, charsets of missing tables, prepare's notices,
 * print_error and $EZSQL_ERROR, query()'s filter and ready flag, set_prefix,
 * set_blog_id, set_sql_mode and the text strippers. Tables are TEMPORARY and
 * dropped in finally. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$escape = $wpdb->placeholder_escape();
$mask = static function ($value) use (&$mask, $escape) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($escape, '{escape}', $value) : $value;
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
$shape = static function ($value) {
    if (is_wp_error($value)) {
        return ['WP_Error', $value->get_error_code(), $value->get_error_message()];
    }
    return is_object($value) ? ['object', get_object_vars($value)] : $value;
};
$prop = static function (object $object, string $name) {
    $p = new ReflectionProperty($object, $name);
    $p->setAccessible(true);
    return $p->getValue($object);
};
$call = static function (object $object, string $method, ...$args) {
    $m = new ReflectionMethod($object, $method);
    $m->setAccessible(true);
    return $m->invoke($object, ...$args);
};

// A wpdb that records what it is asked: db_connect, prepare, the readers and check_safe_collation.
$calls = [];
$make = static function () use (&$calls) {
    return new class(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST, $calls) extends wpdb {
        public $zzCalls;
        public function __construct($u, $p, $n, $h, &$calls)
        {
            $this->zzCalls = &$calls;
            parent::__construct($u, $p, $n, $h);
        }
        public function db_connect($allow_bail = true)
        {
            $this->zzCalls[] = 'db_connect';
            return parent::db_connect($allow_bail);
        }
        public function prepare($query, ...$args)
        {
            $this->zzCalls[] = ['prepare', $query, count($args) === 1 && is_array($args[0]) ? $args[0] : $args];
            return parent::prepare($query, ...$args);
        }
        public function get_var($query = null, $x = 0, $y = 0)
        {
            $this->zzCalls[] = ['get_var', $query, $x, $y];
            return parent::get_var($query, $x, $y);
        }
        protected function check_safe_collation($query)
        {
            $this->zzCalls[] = ['check_safe_collation'];
            return parent::check_safe_collation($query);
        }
        public function zzSafe($query)
        {
            return parent::check_safe_collation($query);
        }
    };
};
$queries = [];
$hear = static function ($query) use (&$queries) {
    $queries[] = $query;
    return $query;
};

$t = 'zz_doubtf_t';
$tl = 'zz_doubtf_latin';
$made = [];
try {
    $calls = [];
    $db = $make();
    $connectCalls = $calls;
    $say('wpdb: a subclass is connected through db_connect, which sets the names', [array_values(array_filter($connectCalls, static fn ($c) => $c === 'db_connect' || ($c[0] ?? '') === 'prepare')), $db->charset, $db->collate, $db->ready]);

    $db->query("DROP TEMPORARY TABLE IF EXISTS {$t}");
    $made[] = $db->query("CREATE TEMPORARY TABLE {$t} (id int NOT NULL AUTO_INCREMENT, a varchar(3), b varchar(3), c varchar(10), n int, PRIMARY KEY (id)) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->query("DROP TEMPORARY TABLE IF EXISTS {$tl}");
    $made[] = $db->query("CREATE TEMPORARY TABLE {$tl} (a varchar(10)) DEFAULT CHARSET=latin1");

    // The writers prepare one statement with every value.
    $calls = [];
    $ins = $db->insert($t, ['a' => 'x', 'b' => 'y', 'c' => 'z']);
    $say('wpdb: insert prepares once', [$ins, array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare'))]);
    $calls = [];
    $db->insert($t, ['a' => 'x', 'b' => null, 'n' => 5], ['%s', '%s', '%d']);
    $say('wpdb: insert with a null prepares once', array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare')));
    $calls = [];
    $up = $db->update($t, ['a' => 'p', 'b' => 'q'], ['c' => 'z', 'id' => 1], null, ['%s', '%d']);
    $say('wpdb: update prepares once', [$up, array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare'))]);
    $calls = [];
    $db->update($t, ['a' => null], ['b' => null]);
    $say('wpdb: update with nulls', array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare')));
    $calls = [];
    $del = $db->delete($t, ['c' => 'nope', 'a' => 'p']);
    $say('wpdb: delete prepares once', [$del, array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare'))]);
    $calls = [];
    $db->delete($t, ['c' => null]);
    $say('wpdb: delete with a null', array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'prepare')));
    $db->insert_id = 777;
    $db->suppress_errors(true);
    $failed = $db->insert('zz_doubtf_missing', ['a' => 'x']);
    $say('wpdb: a failed insert resets insert_id', [$failed, $db->insert_id]);
    $db->insert_id = 777;
    $db->query("INSERT INTO {$t} (id, a) VALUES (1, 'dup')");
    $say('wpdb: a failed raw insert keeps insert_id', $db->insert_id);
    $db->insert_id = 777;
    $db->insert($t, ['id' => 1, 'a' => 'dup']);
    $say('wpdb: a refused insert resets insert_id', [$db->insert_id, $db->last_error !== '']);
    $db->insert_id = 777;
    $db->insert($t, ['a' => 'abcdef']);
    $say('wpdb: an insert too long for its column resets insert_id', $db->insert_id);
    $db->insert_id = 777;
    $db->query("INSERT INTO {$t} (a) VALUES ('x\xFFy')");
    $say('wpdb: a raw insert refused for its text', [$db->insert_id, $db->last_error]);
    $db->insert_id = 777;
    $db->query("DELETE FROM zz_doubtf_missing");
    $say('wpdb: a failed delete keeps insert_id', $db->insert_id);
    $db->suppress_errors(false);
    $wrong = [];
    $db->update($t, ['a' => null], ['b' => null]);
    $say('wpdb: a write with no placeholder is not a notice', $wrong);

    // Two fields too long are both named.
    $db->suppress_errors(true);
    $say('wpdb: two fields too long', [$db->insert($t, ['a' => 'abcdef', 'b' => 'ghijkl']), $db->last_error]);
    $say('wpdb: one field too long', [$db->insert($t, ['a' => 'abcdef', 'b' => 'g']), $db->last_error]);
    $db->suppress_errors(false);

    // The readers.
    $db->query("SELECT '' AS v UNION SELECT 'a' UNION SELECT NULL");
    $calls = [];
    $col = $db->get_col();
    $say('wpdb: get_col reads each row through get_var', [$col, array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'get_var'))]);
    $say('wpdb: get_col with a query', $db->get_col("SELECT '' UNION SELECT 'a' UNION SELECT NULL UNION SELECT '0'"));
    $say('wpdb: get_col of a second column', $db->get_col("SELECT 1, '' UNION SELECT 2, 'b'", 1));
    $say('wpdb: get_var of empty and zero', [$db->get_var("SELECT ''"), $db->get_var("SELECT '0'"), $db->get_var('SELECT NULL'), $db->get_var('SELECT 0'), $db->get_var("SELECT ' '")]);
    $db->query("SELECT 1 AS a UNION SELECT 2");
    $say('wpdb: get_row with no query', [$db->get_row(), $db->get_row(null, ARRAY_A, 1), $db->get_row(null, ARRAY_N)]);
    $db->query("SELECT 1 AS a UNION SELECT 2");
    $say('wpdb: get_results with no query', [$db->get_results(), $db->get_results(null, ARRAY_A)]);
    $say('wpdb: get_results with an unknown output', [$db->get_results('SELECT 1 AS a', 'BOGUS'), $db->get_results('SELECT 1 AS a', 'object'), $db->get_results('SELECT 1 AS a', 'array_a')]);
    $say('wpdb: get_row with an unknown output', [$db->get_row('SELECT 1 AS a', 'BOGUS'), $db->get_row('SELECT 1 AS a', 'array_a')]);
    $db->query("SELECT 1 AS a UNION SELECT 2");
    $say('wpdb: get_var with no query', [$db->get_var(), $db->get_var(null, 0, 1)]);
    $db->query("SELECT 1 AS a UNION SELECT 2");
    $say('wpdb: get_col with no query', $db->get_col());
    foreach (['get_var', 'get_row', 'get_col', 'get_results'] as $reader) {
        $calls = [];
        $db->{$reader}('SELECT 1');
        $say("wpdb: {$reader} asks check_safe_collation", count(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'check_safe_collation')));
    }

    // check_safe_collation by table collation and query kind.
    $kinds = [];
    foreach (['utf8mb4_unicode_ci', 'utf8mb4_bin', 'utf8mb4_general_ci', 'utf8mb3_general_ci', 'latin1_swedish_ci', 'ascii_general_ci', 'utf8mb4_unicode_520_ci', 'latin1_bin', 'binary'] as $i => $collation) {
        $name = "zz_doubtf_c{$i}";
        $db->query("DROP TEMPORARY TABLE IF EXISTS {$name}");
        $column = $collation === 'binary' ? 'varbinary(10)' : 'varchar(10) CHARACTER SET ' . explode('_', $collation)[0] . " COLLATE {$collation}";
        $made[] = $db->query("CREATE TEMPORARY TABLE {$name} (a {$column}, n int)");
        $kinds[$collation] = $db->zzSafe("SELECT * FROM {$name} WHERE a = 'é'");
        $db->query("DROP TEMPORARY TABLE IF EXISTS {$name}");
    }
    $say('wpdb: check_safe_collation by collation', $kinds);
    $say('wpdb: check_safe_collation by query', [
        $db->zzSafe("SELECT 'é'"),
        $db->zzSafe("DESCRIBE {$t} 'é'"),
        $db->zzSafe("SHOW TABLES LIKE 'é'"),
        $db->zzSafe("CREATE TABLE zz_doubtf_x (a varchar(3) DEFAULT 'é')"),
        $db->zzSafe("desc {$t} 'é'"),
        $db->zzSafe("EXPLAIN SELECT * FROM {$t} WHERE a = 'é'"),
        $db->zzSafe("SELECT * FROM {$tl} WHERE a = 'é'"),
        $db->zzSafe("SELECT * FROM zz_doubtf_missing WHERE a = 'é'"),
        $db->zzSafe('SELECT 1'),
    ]);
    $say('wpdb: column metadata is the server row', (static function () use ($db, $t, $prop) {
        $db->get_col_charset($t, 'a');
        $meta = $prop($db, 'col_meta');
        $row = $meta[$t]['a'] ?? null;
        return [array_keys($meta[$t] ?? []), is_object($row) ? [$row->Field, $row->Type, $row->Collation] : $row];
    })());

    // A missing table's charset.
    $say('wpdb: charset of a missing table', [$shape($call($db, 'get_table_charset', 'zz_doubtf_missing')), $shape($db->get_col_charset('zz_doubtf_missing2', 'a')), $db->get_col_length('zz_doubtf_missing3', 'a')]);

    // prepare's notices.
    $say('wpdb: prepare notices', $mask([
        $heard(static fn () => $db->prepare('SELECT 1', 'x')),
        $heard(static fn () => $db->prepare('SELECT 100%%')),
        $heard(static fn () => $db->prepare('SELECT 5%')),
        $heard(static fn () => $db->prepare('SELECT 1')),
        $heard(static fn () => $db->prepare('SELECT %s', ['a', 'b'])),
        $heard(static fn () => $db->prepare('SELECT %s', ['a'])),
        $heard(static fn () => $db->prepare('SELECT %s, %s', ['a'])),
        $heard(static fn () => $db->prepare('SELECT %s, %s', ['a', 'b', 'c'])),
        $heard(static fn () => $db->prepare('SELECT %s', 'a', 'b')),
        $heard(static fn () => $db->prepare('SELECT %s', [])),
        $heard(static fn () => $db->prepare('SELECT %1$s %1$s', ['a', 'b'])),
    ]));

    // print_error, $EZSQL_ERROR and the log line.
    $logFile = tempnam(sys_get_temp_dir(), 'zzdoubtf');
    $oldLog = ini_set('error_log', $logFile);
    $GLOBALS['EZSQL_ERROR'] = [];
    $db->show_errors(true);
    $db->suppress_errors(true);
    ob_start();
    $db->query('SELECT * FROM zz_doubtf_nowhere');
    $printedBoth = ob_get_clean();
    $db->suppress_errors(false);
    $db->show_errors(false);
    $db->query('SELECT * FROM zz_doubtf_nowhere2');
    ini_set('error_log', (string) $oldLog);
    $lines = array_values(array_filter(array_map(static fn ($line) => preg_replace('/^\[[^\]]+\] /', '', $line), (array) file($logFile, FILE_IGNORE_NEW_LINES))));
    @unlink($logFile);
    $dbName = static fn ($text) => str_replace(DB_NAME, '{db}', (string) $text);
    $say('wpdb: print_error with show and suppress', [$printedBoth, array_map(static fn ($e) => [array_keys($e), $e['query'] ?? null, $dbName($e['error_str'] ?? '')], (array) $GLOBALS['EZSQL_ERROR'])]);
    $say('wpdb: the logged line names its caller', array_map(static fn ($line) => $dbName(preg_replace('/ made by .*/', ' made by {caller}', $line)), $lines));

    // query(): the filter sees the last query's state, ready=false runs nothing.
    $db->query('SELECT 1 UNION SELECT 2');
    $seen = null;
    $look = static function ($query) use (&$seen, $db) {
        $seen = [$db->last_query, $db->num_rows];
        return $query;
    };
    add_filter('query', $look);
    $db->query('SELECT 3');
    remove_filter('query', $look);
    $say('wpdb: the query filter sees the last query', $seen);
    $db->ready = false;
    $queries = [];
    add_filter('query', $hear);
    $notReady = $db->query('SELECT 4');
    remove_filter('query', $hear);
    $say('wpdb: a wpdb that is not ready runs nothing', [$notReady, $queries, $db->last_query]);
    $db->ready = true;

    // select() on a missing database.
    $db->suppress_errors(true);
    $other = $make();
    $say('wpdb: select a missing database', (static function () use ($other) {
        try {
            return [$other->select('zz_doubtf_no_db'), $other->ready];
        } catch (Throwable $e) {
            return ['threw', get_class($e)];
        }
    })());
    $other->close();

    // set_blog_id on one site.
    $one = $make();
    $one->set_prefix($wpdb->base_prefix);
    $one->prefix = 'zzx_';
    $one->posts = 'zzx_posts';
    $base = $one->base_prefix;
    $old = $one->set_blog_id(4, 2);
    $say('wpdb: set_blog_id on one site', [$old, $one->blogid, $one->siteid, $one->prefix === $base, $one->posts === $base . 'posts', $one->categories === $base . 'categories', $one->users === $base . 'users']);

    // set_prefix.
    $two = $make();
    $two->set_prefix('zzp_');
    $before = $two->prefix;
    $r1 = $two->set_prefix('zzq_');
    $r2 = $two->set_prefix('zzr_', false);
    $r3 = $two->set_prefix('zzs_');
    $say('wpdb: set_prefix returns and names', [$r1 === $before, $r1, $r2, $r3, $two->prefix, $two->base_prefix, $two->posts, $two->categories, $two->post2cat, $two->link2cat, $two->users, $two->blogs]);

    // set_sql_mode with no modes asks the server directly.
    $three = $make();
    $calls = [];
    $queries = [];
    add_filter('query', $hear);
    $n = $three->num_queries;
    $lastBefore = $three->last_query;
    $three->set_sql_mode();
    remove_filter('query', $hear);
    $say('wpdb: set_sql_mode with no modes', [array_values(array_filter($calls, static fn ($c) => ($c[0] ?? '') === 'get_var')), $queries, $three->num_queries - $n, $three->last_query === $lastBefore]);

    // strip_invalid_text for other charsets, and from a query.
    $strip = static function ($value, $charset) use ($db, $call) {
        $out = $call($db, 'strip_invalid_text', ['f' => ['value' => $value, 'charset' => $charset, 'length' => false]]);
        return is_wp_error($out) ? ['WP_Error', $out->get_error_code()] : bin2hex((string) $out['f']['value']);
    };
    $queries = [];
    add_filter('query', $hear);
    $say('wpdb: strip_invalid_text by charset', [$strip("abc\u{e9}", 'ascii'), $strip("abc\xE9", 'big5'), $strip("abc\u{1F600}", 'utf8'), $strip("abc\xFF", 'utf8mb4'), $strip("abc\u{e9}", 'latin1'), $strip("abc\u{e9}", 'cp1251'), $strip('plain', 'ascii')]);
    remove_filter('query', $hear);
    $say('wpdb: strip_invalid_text asks the server', count($queries));
    $say('wpdb: strip_invalid_text_from_query', array_map('bin2hex', [
        $call($db, 'strip_invalid_text_from_query', "SELECT * FROM {$t} WHERE a = 'x\xFFy'"),
        $call($db, 'strip_invalid_text_from_query', "SELECT 'x\xFFy\u{1F600}'"),
        $call($db, 'strip_invalid_text_from_query', "SELECT * FROM {$tl} WHERE a = 'x\xFFy'"),
        $call($db, 'strip_invalid_text_from_query', "SELECT * FROM {$t} WHERE a = 'x\u{e9}y'"),
    ]));

    // PHPMailer: nothing is sent; SMTP goes to a recording SMTP object, sendmail to a recording script.
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    $mailDir = sys_get_temp_dir() . '/zzdoubtf-mail-' . bin2hex(random_bytes(4));
    mkdir($mailDir);
    $normalize = static fn (string $mime): string => (string) preg_replace(['/b([123])=_[A-Za-z0-9]+/', '/(PHPMailer )[0-9.]+/', '/----[0-9A-F]{32}/'], ['b$1=_ID', '$1V', '----SMIME'], $mime);
    $mailer = static function (string $kind) {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->{'is' . $kind}();
        $m->MessageID = '<zzdoubtf@example.test>';
        $m->MessageDate = 'Mon, 05 Oct 2026 12:00:00 +0000';
        $m->Hostname = 'probe.example.test';
        $m->setFrom('from@example.test', 'From Person');
        $m->Subject = 'zzdoubtf';
        $m->Body = 'Body';
        return $m;
    };
    $lang = new ReflectionMethod(PHPMailer\PHPMailer\PHPMailer::class, 'lang');
    $lang->setAccessible(true);
    $say('mail: lang smtp_connect_failed', [$lang->invoke(null, 'smtp_connect_failed'), $lang->invoke(null, 'zz-nokey')]);
    $safe = new ReflectionMethod(PHPMailer\PHPMailer\PHPMailer::class, 'isShellSafe');
    $safe->setAccessible(true);
    $say('mail: isShellSafe', array_map(static fn ($v) => [$v, $safe->invoke(null, $v)], ['a+b@example.test', 'a=b@example.test', "\u{e4}@example.test", 'ab@example.test', 'a_b-c.d@example.test', '-oQ@x.com', "a'b@example.test", 'a b@example.test', 'a!b@example.test', '']));

    // mail(): the headers it adds, composed without sending.
    $m = $mailer('Mail');
    $m->addCC('cc@example.test', 'Cc Person');
    $m->preSend();
    $say('mail: mail() with only a Cc', [$prop($m, 'mailHeader')]);
    $m = $mailer('Mail');
    $m->addAddress('one@example.test', 'One Person');
    $m->isHTML(true);
    $m->Body = '<p>Body</p>';
    $m->AltBody = 'Body';
    $m->preSend();
    $say('mail: preSend with an AltBody', [$m->ContentType, $call($m, 'getMailMIME') === '' ? '' : 'mime', $prop($m, 'message_type')]);

    // serverHostname with no Hostname and an empty SERVER_NAME.
    $hadServerName = array_key_exists('SERVER_NAME', $_SERVER);
    $serverName = $_SERVER['SERVER_NAME'] ?? null;
    $host = new ReflectionMethod(PHPMailer\PHPMailer\PHPMailer::class, 'serverHostname');
    $host->setAccessible(true);
    $hm = new PHPMailer\PHPMailer\PHPMailer();
    $hm->Hostname = '';
    $_SERVER['SERVER_NAME'] = '';
    $hosts = [$host->invoke($hm)];
    $_SERVER['SERVER_NAME'] = 'zz.example.test';
    $hosts[] = $host->invoke($hm);
    $_SERVER['SERVER_NAME'] = 'bad host!';
    $hosts[] = $host->invoke($hm);
    if ($hadServerName) {
        $_SERVER['SERVER_NAME'] = $serverName;
    } else {
        unset($_SERVER['SERVER_NAME']);
    }
    $say('mail: serverHostname', $hosts);

    // replaceCustomHeader with a bad name or value.
    $replace = static function (bool $exceptions, callable $setup) {
        $m = new PHPMailer\PHPMailer\PHPMailer($exceptions);
        $args = $setup($m);
        try {
            $out = $m->replaceCustomHeader(...$args);
        } catch (Throwable $e) {
            $out = ['threw', $e->getMessage()];
        }
        return [$out, $m->ErrorInfo, $m->isError(), $m->getCustomHeaders()];
    };
    $say('mail: replaceCustomHeader refused', [
        $replace(false, static function ($m) {
            return ["X-Zz\r\nBcc", 'v'];
        }),
        $replace(false, static function ($m) {
            $m->addCustomHeader('X-Zz', 'one');
            return ['X-Zz', "two\r\nBcc: x@example.test"];
        }),
        $replace(true, static function ($m) {
            return ["X-Zz\r\nBcc", 'v'];
        }),
        $replace(false, static function ($m) {
            $m->addCustomHeader('X-Zz', 'one');
            $m->addCustomHeader('X-Zz', 'two');
            return ['X-Zz', 'three'];
        }),
        $replace(false, static function ($m) {
            return ['X-New', 'v'];
        }),
    ]);

    // S/MIME: a throwaway certificate signs three messages.
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'zzdoubtf.example.test'], $key), null, $key, 1);
    openssl_x509_export_to_file($cert, "{$mailDir}/cert.pem");
    openssl_pkey_export_to_file($key, "{$mailDir}/key.pem");
    $signed = [];
    foreach (['ascii' => ['Body', ''], 'utf8' => ["B\u{f6}dy", ''], 'html+alt' => ['<p>Body</p>', 'Body']] as $case => [$body, $alt]) {
        $m = $mailer('SMTP');
        $m->addAddress('one@example.test');
        $m->CharSet = 'utf-8';
        $m->Body = $body;
        if ($alt !== '') {
            $m->isHTML(true);
            $m->AltBody = $alt;
        }
        $m->sign("{$mailDir}/cert.pem", "{$mailDir}/key.pem", '');
        $m->preSend();
        $message = $normalize($m->getSentMIMEMessage());
        preg_match_all('/^(?:MIME-Version|Content-Type|Content-Transfer-Encoding|Content-Disposition):.*$/m', $message, $lines);
        $signed[$case] = [substr_count($m->MIMEHeader ?? $prop($m, 'MIMEHeader'), 'MIME-Version:'), array_map('trim', $lines[0])];
    }
    $say('mail: S/MIME signed headers', $signed);

    // SMTP through a recording SMTP object: hellos, STARTTLS, XCLIENT, SMTPUTF8 and the port.
    $smtp = static function (callable $setup, array $ext = ['STARTTLS' => true]) use ($mailer) {
        $rec = new class extends PHPMailer\PHPMailer\SMTP {
            public $zz = [];
            public $zzExt = [];
            private $zzOpen = false;
            public $zzRefuse = [];
            public $zzXclient = true;
            public function connect($host, $port = null, $timeout = 30, $options = [])
            {
                $this->zz[] = ['connect', $host, $port];
                return $this->zzOpen = !in_array($host, $this->zzRefuse, true);
            }
            public function connected()
            {
                return $this->zzOpen;
            }
            public function hello($host = '')
            {
                $this->zz[] = ['hello'];
                return true;
            }
            public function getServerExt($name)
            {
                return $this->zzExt[$name] ?? false;
            }
            public function startTLS()
            {
                $this->zz[] = ['startTLS'];
                return true;
            }
            public function authenticate($username, $password, $authtype = null, $OAuth = null)
            {
                $this->zz[] = ['authenticate'];
                return true;
            }
            public function xclient($vars)
            {
                $this->zz[] = ['xclient', $vars];
                return $this->zzXclient;
            }
            public function setSMTPUTF8($enabled = false)
            {
                $this->zz[] = ['setSMTPUTF8', $enabled];
                return parent::setSMTPUTF8($enabled);
            }
            public function mail($from)
            {
                $this->zz[] = ['mail', $from, $this->getSMTPUTF8()];
                return true;
            }
            public function recipient($address, $dsn = '')
            {
                $this->zz[] = ['recipient', $address];
                return true;
            }
            public function data($msg_data)
            {
                $this->zz[] = ['data'];
                return true;
            }
            public function reset()
            {
                return true;
            }
            public function quit($close_on_error = true)
            {
                $this->zz[] = ['quit'];
                return true;
            }
            public function close()
            {
                $this->zzOpen = false;
                $this->zz[] = ['close'];
            }
            public function getLastTransactionID()
            {
                return 'zzid';
            }
            public function getError()
            {
                return ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
            }
        };
        $rec->zzExt = $ext;
        $m = $mailer('SMTP');
        $m->addAddress('one@example.test');
        $m->setSMTPInstance($rec);
        $m->Host = 'mail.example.test';
        $m->Port = 2525;
        $m->SMTPAutoTLS = true;
        $direct = $setup($m, $rec);
        try {
            $sent = $direct === 'connect' ? $m->smtpConnect() : $m->send();
        } catch (Throwable $e) {
            $sent = ['threw', $e->getMessage()];
        }
        return [$sent, $rec->zz, $m->ErrorInfo];
    };
    $say('mail: SMTP to localhost offering STARTTLS', $smtp(static function ($m) {
        $m->Host = 'localhost';
    }));
    $say('mail: SMTP to a remote host offering STARTTLS', $smtp(static function ($m) {
    }));
    $connected = static fn (array $run) => array_values(array_filter($run[1], static fn ($c) => $c[0] === 'connect'));
    $say('mail: SMTP host ports', [
        $connected($smtp(static function ($m) {
            $m->Host = 'mail.example.test:70000';
        }, [])),
        $connected($smtp(static function ($m) {
            $m->Host = 'mail.example.test:0';
        }, [])),
        $connected($smtp(static function ($m) {
            $m->Host = 'mail.example.test:65535';
        }, [])),
        $connected($smtp(static function ($m) {
            $m->Host = 'mail.example.test:2526';
        }, [])),
    ]);
    $say('mail: SMTP with XCLIENT attributes', $smtp(static function ($m) {
        $m->setSMTPXclientAttribute('ADDR', '192.0.2.1');
        $m->setSMTPXclientAttribute('NAME', 'zz.example.test');
    }, []));
    $say('mail: SMTP with SMTPUTF8', $smtp(static function ($m) {
        $m->UseSMTPUTF8 = true;
    }, ['SMTPUTF8' => true]));
    $say('mail: SMTP to localhost asking for tls', $smtp(static function ($m) {
        $m->Host = 'localhost';
        $m->SMTPSecure = 'tls';
    }));
    $say('mail: SMTP to 127.0.0.1 offering STARTTLS', $smtp(static function ($m) {
        $m->Host = '127.0.0.1';
    }));
    $say('mail: smtpConnect alone', $smtp(static function ($m) {
        $m->UseSMTPUTF8 = true;
        return 'connect';
    }, []));
    $say('mail: SMTP with a first host refused', $smtp(static function ($m, $rec) {
        $m->Host = 'a.example.test;b.example.test';
        $rec->zzRefuse = ['a.example.test'];
    }, []));
    $say('mail: SMTP asking SMTPUTF8 of a server without it', $smtp(static function ($m) {
        $m->UseSMTPUTF8 = true;
    }, []));
    $say('mail: SMTP with XCLIENT and a login', $smtp(static function ($m) {
        $m->SMTPAuth = true;
        $m->Username = 'u';
        $m->Password = 'p';
        $m->setSMTPXclientAttribute('ADDR', '192.0.2.1');
    }, []));
    $say('mail: SMTP with XCLIENT refused', $smtp(static function ($m, $rec) {
        $m->setSMTPXclientAttribute('ADDR', '192.0.2.1');
        $rec->zzXclient = false;
    }, []));
    $say('mail: SMTP that cannot connect', (static function () use ($mailer) {
        $rec = new class extends PHPMailer\PHPMailer\SMTP {
            public function connect($host, $port = null, $timeout = 30, $options = [])
            {
                return false;
            }
            public function getError()
            {
                return ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
            }
        };
        $m = $mailer('SMTP');
        $m->addAddress('one@example.test');
        $m->setSMTPInstance($rec);
        $m->Host = 'mail.example.test';
        $r = new ReflectionProperty($m, 'exceptions');
        $r->setAccessible(true);
        $r->setValue($m, false);
        return [$m->send(), $m->ErrorInfo];
    })());

    // sendmail through a recording script.
    $script = "{$mailDir}/sendmail.sh";
    $sendLog = "{$mailDir}/sendmail.log";
    file_put_contents($script, "#!/bin/sh\necho \"ARGS:\$*\" >> " . escapeshellarg($sendLog) . "\ncat >> " . escapeshellarg($sendLog) . "\necho '--END--' >> " . escapeshellarg($sendLog) . "\nexit 0\n");
    chmod($script, 0755);
    $sendmail = static function (string $kind, callable $setup) use ($mailer, $script, $sendLog) {
        @unlink($sendLog);
        $m = $mailer($kind);
        $m->Sendmail = $script;
        $m->Sender = '';
        $m->addAddress('one@example.test', 'One Person');
        $setup($m);
        try {
            $sent = $m->send();
        } catch (Throwable $e) {
            $sent = ['threw', $e->getMessage()];
        }
        $text = is_file($sendLog) ? (string) file_get_contents($sendLog) : '';
        preg_match_all('/^ARGS:(.*)$/m', $text, $args);
        preg_match_all('/^To:.*$/m', $text, $to);
        return [$sent, array_map(static fn ($a) => str_replace($script, '{script}', $a), $args[1]), $to[0], $m->Sender];
    };
    $oldFrom = ini_get('sendmail_from');
    $say('mail: sendmail arguments', [
        $sendmail('Sendmail', static function ($m) {
        }),
        $sendmail('Sendmail', static function ($m) {
            $m->Sender = 'from@example.test';
        }),
        $sendmail('Sendmail', static function ($m) {
            $m->Sender = 'bounce@example.test';
        }),
        $sendmail('Sendmail', static function ($m) {
            $m->UseSendmailOptions = false;
        }),
        $sendmail('Sendmail', static function ($m) {
            $m->Sender = 'bounce@example.test';
            $m->UseSendmailOptions = false;
        }),
        $sendmail('Sendmail', static function ($m) {
            $m->Sender = 'a+b@example.test';
        }),
        $sendmail('Qmail', static function ($m) {
        }),
        $sendmail('Qmail', static function ($m) {
            $m->Sender = 'bounce@example.test';
        }),
    ]);
    ini_set('sendmail_from', 'inifrom@example.test');
    $say('mail: sendmail takes sendmail_from', $sendmail('Sendmail', static function ($m) {
    }));
    ini_set('sendmail_from', (string) $oldFrom);
    $say('mail: sendmail one at a time', $sendmail('Sendmail', static function ($m) {
        $m->SingleTo = true;
        $m->addAddress('two@example.test');
    }));

    // WP_Rewrite on fresh objects; options are only read, never written with another value.
    $fresh = static function (string $structure) {
        $r = new WP_Rewrite();
        $r->permalink_structure = $structure;
        $r->front = substr($structure, 0, (int) strpos($structure, '%'));
        $r->root = '';
        return $r;
    };
    $dates = [];
    foreach (['/%year%/%monthnum%/%day%/%postname%/', '/%day%/%monthnum%/%year%/%postname%/', '/%monthnum%/%day%/%year%/%postname%/', '/blog/%year%/%monthnum%/%day%/%postname%/', '/%post_id%/%year%/%monthnum%/%postname%/', '/%postname%/'] as $structure) {
        $dates[$structure] = [$fresh($structure)->get_year_permastruct(), $fresh($structure)->get_month_permastruct(), $fresh($structure)->get_day_permastruct()];
    }
    $say('rewrite: year, month and day structures', $dates);
    $set = $fresh('/%year%/%postname%/');
    $set->date_structure = '/zzd/%year%/%monthnum%/%day%';
    $set->author_structure = '/zza/%author%';
    $set->search_structure = '/zzs/%search%';
    $set->page_structure = '/zzp/%pagename%';
    $set->feed_structure = '/zzf/%feed%';
    $set->comment_feed_structure = '/zzcf/%feed%';
    $say('rewrite: structures already set', [$set->get_date_permastruct(), $set->get_year_permastruct(), $set->get_month_permastruct(), $set->get_author_permastruct(), $set->get_search_permastruct(), $set->get_page_permastruct(), $set->get_feed_permastruct(), $set->get_comment_feed_permastruct()]);
    $kept = $fresh('/%year%/%postname%/');
    $kept->get_date_permastruct();
    $kept->get_author_permastruct();
    $kept->get_search_permastruct();
    $kept->get_page_permastruct();
    $kept->get_feed_permastruct();
    $kept->get_comment_feed_permastruct();
    $say('rewrite: structures kept once made', array_map(static fn ($name) => $kept->{$name} ?? null, ['date_structure', 'author_structure', 'search_structure', 'page_structure', 'feed_structure', 'comment_feed_structure']));
    $plain = $fresh('');
    $plain->author_structure = '/zza/%author%';
    $say('rewrite: structures without permalinks', [$plain->get_author_permastruct(), $plain->get_date_permastruct(), $plain->get_year_permastruct(), $plain->get_search_permastruct()]);

    $deprecated = [];
    $hearDeprecated = static function ($hook, $replacement, $version, $message) use (&$deprecated) {
        $deprecated[] = [$hook, $replacement, $version];
    };
    add_action('deprecated_hook_run', $hearDeprecated, 10, 4);
    add_filter('deprecated_hook_trigger_error', '__return_false');
    $qs = static fn ($string) => $string . '&zzq=1';
    add_filter('query_string', $qs);
    $w = new WP();
    $w->query_vars = ['zzv' => 'a'];
    $w->build_query_string();
    remove_filter('query_string', $qs);
    remove_action('deprecated_hook_run', $hearDeprecated, 10);
    $say('rewrite: the query_string filter is deprecated', [$w->query_string, $w->query_vars, $deprecated]);

    $w = new WP();
    $w->extra_query_vars = ['zzold' => 1];
    $seenParse = null;
    $stop = static function ($do, $wp, $extra) use (&$seenParse) {
        $seenParse = [$wp->extra_query_vars, $extra];
        return false;
    };
    add_filter('do_parse_request', $stop, 10, 3);
    $parsed = $w->parse_request('zznew=2');
    $seenString = $seenParse;
    $parsedArray = $w->parse_request(['zzarr' => 3]);
    $seenArray = $seenParse;
    remove_filter('do_parse_request', $stop, 10);
    $say('rewrite: do_parse_request hears the given extra vars', [$parsed, $seenString, $parsedArray, $seenArray]);

    $r = $fresh('/%postname%/');
    $r->add_endpoint('zzdoubtf-ep', EP_ALL, null);
    $r->add_endpoint('zzdoubtf-ep2', EP_ALL, false);
    $r->add_endpoint('zzdoubtf-ep3', EP_ALL, '');
    $r->add_endpoint('zzdoubtf-ep4', EP_ALL);
    $say('rewrite: add_endpoint query vars', $r->endpoints);
    $r->add_permastruct('zzpd', 'zz/%zzpd%', false, EP_PAGES);
    $r->add_permastruct('zzpe', 'zz/%zzpe%', ['ep_mask' => EP_PAGES, 'with_front' => false]);
    $r->add_permastruct('zzpf', 'zz/%zzpf%', true);
    $say('rewrite: add_permastruct with the old arguments', [$r->extra_permastructs['zzpd'] ?? null, $r->extra_permastructs['zzpe'] ?? null, $r->extra_permastructs['zzpf'] ?? null]);

    $r = $fresh('/%postname%/');
    $r->add_rewrite_tag('%zza%', '([a-z]+)', 'zza=');
    $r->add_rewrite_tag('%zzb%', '([0-9]+)', 'zzb=');
    $r->remove_rewrite_tag('%zza%');
    $r->remove_rewrite_tag('%monthnum%');
    $say('rewrite: remove_rewrite_tag keeps the keys', [array_keys($r->rewritecode), $r->rewritecode, array_keys($r->rewritereplace), array_keys($r->queryreplace)]);

    $r = $fresh('/%postname%/');
    $home = static fn () => 'https://zz-iis.example.test';
    add_filter('home_url', $home);
    preg_match('/<rule name="([^"]*)"/', $r->iis7_url_rewrite_rules(), $iis);
    remove_filter('home_url', $home);
    $say('rewrite: the IIS rule is named for home_url', $iis[1] ?? null);

    $r = new WP_Rewrite();
    $say('rewrite: a new WP_Rewrite has no top rules', [$r->extra_rules_top, $r->extra_rules, $r->non_wp_rules, $r->endpoints, $r->extra_permastructs]);
    $r->extra_rules_top = ['zztop' => 'index.php?zz=1'];
    $r->extra_rules = ['zzbottom' => 'index.php?zz=2'];
    $r->non_wp_rules = ['zzext' => 'zz.php'];
    $r->endpoints = [[EP_ALL, 'zzep', 'zzep']];
    $r->extra_permastructs = ['zzps' => ['struct' => '/zz/%zz%']];
    $r->author_structure = 'zzmark';
    $r->init();
    $say('rewrite: init keeps and clears', [$r->extra_rules_top, $r->extra_rules, $r->non_wp_rules, $r->endpoints, array_keys($r->extra_permastructs), isset($r->author_structure)]);
    global $wp_rewrite;
    // Minn Admin's own address is the engine's alone.
    $say('rewrite: the global top rules', array_values(array_filter(array_keys((array) $wp_rewrite->extra_rules_top), static fn ($rule) => !str_contains((string) $rule, 'minn-admin'))));

    foreach (['set_category_base' => 'category_base', 'set_tag_base' => 'tag_base'] as $method => $option) {
        $r = new WP_Rewrite();
        $r->author_structure = 'zzmark';
        $r->extra_rules = ['zzbottom' => 'index.php?zz=2'];
        $before = get_option($option);
        $r->{$method}($before);
        $say("rewrite: {$method} unchanged does not init", [isset($r->author_structure), $r->extra_rules, get_option($option) === $before]);
    }

    // Upgraders: nothing is downloaded or deleted; a recording filesystem stands in for $wp_filesystem.
    foreach (['wp-admin/includes/plugin.php', 'wp-admin/includes/file.php', 'wp-admin/includes/misc.php', 'wp-admin/includes/theme.php', 'wp-admin/includes/class-wp-upgrader.php'] as $admin) {
        if (is_file(ABSPATH . $admin)) {
            require_once ABSPATH . $admin;
        }
    }
    $silentSkin = static function () {
        return new class extends WP_Upgrader_Skin {
            public $zzCreds = [];
            public $zzFeedback = [];
            public $zzAnswer = true;
            public function request_filesystem_credentials($error = false, $context = '', $allow_relaxed_file_ownership = false)
            {
                $this->zzCreds[] = is_wp_error($error) ? 'error:' . $error->get_error_code() : $error;
                return $this->zzAnswer;
            }
            public function feedback($feedback, ...$args)
            {
                $this->zzFeedback[] = $feedback;
            }
            public function header()
            {
            }
            public function footer()
            {
            }
            public function error($errors)
            {
                $this->zzFeedback[] = is_wp_error($errors) ? 'error:' . $errors->get_error_code() : $errors;
            }
        };
    };
    $shapeAll = static fn ($v) => is_wp_error($v) ? ['WP_Error', $v->get_error_code(), $v->get_error_message()] : $v;

    // fs_connect when the filesystem cannot be set up.
    $hadFs = array_key_exists('wp_filesystem', $GLOBALS);
    $savedFs = $GLOBALS['wp_filesystem'] ?? null;
    $bogus = static fn () => 'zzdoubtfbogus';
    add_filter('filesystem_method', $bogus, PHP_INT_MAX);
    $skin = $silentSkin();
    $u = new WP_Upgrader($skin);
    $u->init();
    $connected = @$u->fs_connect([WP_CONTENT_DIR]);
    remove_filter('filesystem_method', $bogus, PHP_INT_MAX);
    $GLOBALS['wp_filesystem'] = $savedFs;
    $say('upgrader: fs_connect without a filesystem', [$shapeAll($connected), $skin->zzCreds]);
    $skin = $silentSkin();
    $skin->zzAnswer = false;
    $u = new WP_Upgrader($skin);
    $u->init();
    $say('upgrader: fs_connect with no credentials', [$shapeAll($u->fs_connect([WP_CONTENT_DIR])), $skin->zzCreds]);
    $GLOBALS['wp_filesystem'] = $savedFs;

    // deactivate_plugin_before_upgrade with no plugin named.
    $pu = new Plugin_Upgrader($silentSkin());
    $pu->init();
    $pu->upgrade_strings();
    $say('upgrader: deactivate_plugin_before_upgrade with no plugin', [$shapeAll($pu->deactivate_plugin_before_upgrade(true, [])), $shapeAll($pu->deactivate_plugin_before_upgrade(true, ['plugin' => ''])), $shapeAll($pu->deactivate_plugin_before_upgrade(true, ['plugin' => 'zz-doubtf-none/zz.php']))]);

    // delete_old_plugin and delete_old_theme through a recording filesystem.
    $recordingFs = static function (bool $exists) {
        return new class($exists) {
            public $zz = [];
            public function __construct(private bool $there)
            {
            }
            public function wp_plugins_dir()
            {
                return '{plugins}/';
            }
            public function wp_themes_dir($theme = false)
            {
                return '{themes}/';
            }
            public function exists($path)
            {
                $this->zz[] = ['exists', $path];
                return $this->there;
            }
            public function is_dir($path)
            {
                $this->zz[] = ['is_dir', $path];
                return $this->there;
            }
            public function delete($path, $recursive = false, $type = false)
            {
                $this->zz[] = ['delete', $path, $recursive];
                return true;
            }
        };
    };
    $deletes = [];
    foreach (['/x.php', 'zz-dir/zz.php', 'x.php'] as $file) {
        $GLOBALS['wp_filesystem'] = $recordingFs(true);
        $out = $pu->delete_old_plugin(true, '', '', ['plugin' => $file]);
        $deletes[$file] = [$shapeAll($out), $GLOBALS['wp_filesystem']->zz];
    }
    $GLOBALS['wp_filesystem'] = $recordingFs(false);
    $deletes['absent'] = [$shapeAll($pu->delete_old_plugin('zzremoved', '', '', ['plugin' => 'zz-dir/zz.php'])), $GLOBALS['wp_filesystem']->zz];
    $GLOBALS['wp_filesystem'] = $savedFs;
    $say('upgrader: delete_old_plugin', $deletes);
    $tu = new Theme_Upgrader($silentSkin());
    $tu->init();
    $tu->upgrade_strings();
    $themeDeletes = [];
    $themeRoot = static fn ($path) => str_replace(get_theme_root(), '{root}', (string) $path);
    foreach (['none' => [true, []], 'absent' => [false, ['theme' => 'zz-doubtf-theme']], 'there' => [true, ['theme' => 'zz-doubtf-theme']]] as $case => [$exists, $theme]) {
        $GLOBALS['wp_filesystem'] = $recordingFs($exists);
        $out = $tu->delete_old_theme('zzremoved', '', '', $theme);
        $themeDeletes[$case] = [$shapeAll($out), array_map(static fn ($c) => array_map(static fn ($v) => is_string($v) ? $themeRoot($v) : $v, $c), $GLOBALS['wp_filesystem']->zz)];
    }
    $GLOBALS['wp_filesystem'] = $savedFs;
    if (!$hadFs) {
        unset($GLOBALS['wp_filesystem']);
    }
    $say('upgrader: delete_old_theme', $themeDeletes);

    // check_parent_theme_filter asks the theme directory for a missing parent.
    $apiAsked = [];
    $noApi = static function ($result, $action, $args) use (&$apiAsked) {
        $apiAsked[] = [$action, (array) $args];
        return new WP_Error('zz_no_api', 'No directory here.');
    };
    add_filter('themes_api', $noApi, 10, 3);
    $childDir = get_theme_root() . '/zz-doubtf-child';
    $madeChild = false;
    try {
        if (!file_exists($childDir)) {
            mkdir($childDir, 0755);
            $madeChild = true;
            file_put_contents("{$childDir}/style.css", "/*\nTheme Name: ZZ Doubtf Child\nTemplate: zz-doubtf-missing-parent\n*/\n");
        }
        wp_clean_themes_cache();
        $skin = $silentSkin();
        $tu = new Theme_Upgrader($skin);
        $tu->init();
        $tu->install_strings();
        $tu->result = ['destination_name' => 'zz-doubtf-child', 'local_destination' => get_theme_root(), 'destination' => "{$childDir}/", 'remote_destination' => "{$childDir}/"];
        $parentResult = $tu->check_parent_theme_filter(true, [], $tu->result);
    } finally {
        remove_filter('themes_api', $noApi, 10);
        if ($madeChild) {
            @unlink("{$childDir}/style.css");
            @rmdir($childDir);
        }
        wp_clean_themes_cache();
    }
    $say('upgrader: a missing parent theme is looked up', [$parentResult, $skin->zzFeedback, $apiAsked]);

    // should_update_to_version after a failed core update.
    $current = wp_get_wp_version();
    $parts = explode('.', preg_replace('/[^0-9.].*/', '', $current));
    $parts[2] = (string) ((int) ($parts[2] ?? 0) + 1);
    $next = implode('.', array_slice($parts, 0, 3));
    $failed = null;
    $failure = static function () use (&$failed) {
        return $failed;
    };
    add_filter('pre_site_option_auto_core_update_failed', $failure);
    $answers = [];
    foreach (['critical' => ['current' => $current, 'critical' => true, 'attempted' => '0.0.1', 'retry' => true], 'same, no retry' => ['current' => $current, 'critical' => false, 'attempted' => $next, 'retry' => false], 'same, retry' => ['current' => $current, 'critical' => false, 'attempted' => $next, 'retry' => true], 'another' => ['current' => $current, 'critical' => false, 'attempted' => '0.0.1', 'retry' => false], 'from another version' => ['current' => '0.0.1', 'critical' => false, 'attempted' => $next, 'retry' => false], 'none' => false] as $case => $record) {
        $failed = $record;
        $answers[$case] = Core_Upgrader::should_update_to_version($next);
    }
    remove_filter('pre_site_option_auto_core_update_failed', $failure);
    $say('upgrader: should_update_to_version after a failure', $answers);

    // A real update of a throwaway plugin and theme takes its line out of auto_plugin_theme_update_emails.
    $upTmp = sys_get_temp_dir() . '/zzdoubtf-up-' . bin2hex(random_bytes(4));
    $upFolders = [WP_PLUGIN_DIR . '/zz-doubtf-up', get_theme_root() . '/zz-doubtf-uptheme', WP_CONTENT_DIR . '/upgrade-temp-backup/plugins/zz-doubtf-up', WP_CONTENT_DIR . '/upgrade-temp-backup/themes/zz-doubtf-uptheme'];
    $rmTree = static function (string $path): void {
        if (!is_dir($path)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    };
    $emailsBefore = get_option('auto_plugin_theme_update_emails', null);
    $cleanupWas = (bool) wp_next_scheduled('wp_delete_temp_updater_backups');
    $offersFor = [];
    $offerFilter = static function ($kind) use (&$offersFor) {
        return static function () use (&$offersFor, $kind) {
            return $offersFor[$kind] ?? false;
        };
    };
    $pluginOffer = $offerFilter('plugins');
    $themeOffer = $offerFilter('themes');
    $foldersThere = array_filter($upFolders, 'file_exists');
    try {
        if ($foldersThere !== []) {
            throw new RuntimeException('a zz-doubtf-up folder is already there');
        }
        mkdir($upTmp, 0755, true);
        $zipUp = static function (string $name, array $files) use ($upTmp): string {
            $archive = new ZipArchive();
            $archive->open("{$upTmp}/{$name}", ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($files as $entry => $contents) {
                $archive->addFromString($entry, $contents);
            }
            $archive->close();
            return "{$upTmp}/{$name}";
        };
        $pluginFile = static fn (string $v) => "<?php\n/**\n * Plugin Name: ZZ Doubtf Up\n * Version: {$v}\n */\n";
        $themeFile = static fn (string $v) => "/*\nTheme Name: ZZ Doubtf Up Theme\nVersion: {$v}\n*/\n";
        $pv1 = $zipUp('p1.zip', ['zz-doubtf-up/zz-doubtf-up.php' => $pluginFile('1.0')]);
        $pv2 = $zipUp('p2.zip', ['zz-doubtf-up/zz-doubtf-up.php' => $pluginFile('2.0')]);
        $tv1 = $zipUp('t1.zip', ['zz-doubtf-uptheme/style.css' => $themeFile('1.0'), 'zz-doubtf-uptheme/index.php' => "<?php\n"]);
        $tv2 = $zipUp('t2.zip', ['zz-doubtf-uptheme/style.css' => $themeFile('2.0'), 'zz-doubtf-uptheme/index.php' => "<?php\n"]);
        add_filter('async_update_translation', '__return_false');
        (new Plugin_Upgrader(new Automatic_Upgrader_Skin()))->install($pv1, ['clear_update_cache' => false]);
        (new Theme_Upgrader(new Automatic_Upgrader_Skin()))->install($tv1, ['clear_update_cache' => false]);
        update_option('auto_plugin_theme_update_emails', ['zz-doubtf-up/zz-doubtf-up.php' => '1.0', 'zz-doubtf-uptheme' => '1.0', 'zz-doubtf-other' => '1.0'], false);
        $offersFor['plugins'] = (object) ['last_checked' => time(), 'checked' => [], 'response' => ['zz-doubtf-up/zz-doubtf-up.php' => (object) ['slug' => 'zz-doubtf-up', 'plugin' => 'zz-doubtf-up/zz-doubtf-up.php', 'new_version' => '2.0', 'package' => $pv2]], 'no_update' => [], 'translations' => []];
        $offersFor['themes'] = (object) ['last_checked' => time(), 'checked' => [], 'response' => ['zz-doubtf-uptheme' => ['theme' => 'zz-doubtf-uptheme', 'new_version' => '2.0', 'package' => $tv2]], 'no_update' => [], 'translations' => []];
        add_filter('pre_site_transient_update_plugins', $pluginOffer);
        add_filter('pre_site_transient_update_themes', $themeOffer);
        $updated = [(new Plugin_Upgrader(new Automatic_Upgrader_Skin()))->upgrade('zz-doubtf-up/zz-doubtf-up.php', ['clear_update_cache' => false])];
        $afterPlugin = get_option('auto_plugin_theme_update_emails');
        $updated[] = (new Theme_Upgrader(new Automatic_Upgrader_Skin()))->upgrade('zz-doubtf-uptheme', ['clear_update_cache' => false]);
        $say('upgrader: an update leaves the update emails', [$updated, $afterPlugin, get_option('auto_plugin_theme_update_emails')]);
    } catch (RuntimeException $e) {
        $say('upgrader: an update leaves the update emails', ['skipped', $e->getMessage()]);
    } finally {
        remove_filter('pre_site_transient_update_plugins', $pluginOffer);
        remove_filter('pre_site_transient_update_themes', $themeOffer);
        if ($foldersThere === []) {
            foreach ($upFolders as $folder) {
                $rmTree($folder);
            }
        }
        $rmTree($upTmp);
        $emailsBefore === null ? delete_option('auto_plugin_theme_update_emails') : update_option('auto_plugin_theme_update_emails', $emailsBefore, false);
        if (!$cleanupWas) {
            wp_clear_scheduled_hook('wp_delete_temp_updater_backups');
        }
        wp_clean_plugins_cache(false);
        wp_clean_themes_cache(false);
    }

    // SAVEQUERIES and the custom user tables are constants: last, so nothing before them sees them.
    define('SAVEQUERIES', true);
    $four = $make();
    $four->queries = [];
    $four->query('SELECT 1');
    $entry = $four->queries[0] ?? null;
    $say('wpdb: SAVEQUERIES logs a query', [count((array) $four->queries), is_array($entry) ? [count($entry), $entry[0], is_float($entry[1]), is_string($entry[2]), is_float($entry[3]), $entry[4]] : $entry]);
    define('CUSTOM_USER_TABLE', 'zzdoubtf_users');
    define('CUSTOM_USER_META_TABLE', 'zzdoubtf_usermeta');
    $five = $make();
    $five->set_prefix('zzc_');
    $say('wpdb: the custom user tables', [$five->users, $five->usermeta, $five->tables('global'), $five->posts]);
} finally {
    if (isset($mailDir) && is_dir($mailDir)) {
        array_map('unlink', glob("{$mailDir}/*") ?: []);
        rmdir($mailDir);
    }
    foreach ([$t, $tl] as $table) {
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$table}");
        if (isset($db)) {
            $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}");
        }
    }
}

echo json_encode($log);
