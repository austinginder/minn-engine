<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Content\Site;
use Minn\Db;
use Minn\Runtime\CronTable;
use Minn\Front\Permalinks;
use Minn\Http\Request;
use Minn\Mail\MailSettings;

/**
 * The System view's facts about this install: the engine, PHP, the
 * database, and the server, with the health checks a site owner acts on.
 * Every number is read live; nothing is cached or fetched from outside.
 */
final readonly class Diagnostics
{
    private const AUTOLOAD_VALUES = ['yes', 'on', 'auto', 'auto-on'];

    public function __construct(
        private Db $db,
        private Site $site,
        private Permalinks $permalinks,
        private InstalledSoftware $software,
        private Logs $logs,
        private string $engineVersion,
        private string $webroot,
    ) {
    }

    /** The System view: checks, config, logs, and the four info groups. */
    public function payload(Request $request): array
    {
        $autoload = $this->autoloadSummary();
        $cron = $this->cronSummary();
        $mail = MailSettings::fromSite($this->site);
        [$database, $tables] = $this->databaseGroup();
        return [
            'generated' => gmdate('c'),
            'checks' => $this->checks($request, $autoload, $cron, $mail),
            'config' => $this->config(),
            'logs' => $this->logs->listPayload(),
            'licenses' => null,
            'extensions' => $this->software->manifest(),
            'integrations' => null,
            'groups' => [
                ['title' => 'Minn Engine', 'icon' => 'server', 'rows' => self::rows($this->engineGroup($cron, $mail))],
                ['title' => 'PHP', 'icon' => 'php', 'rows' => self::rows(self::phpGroup())],
                ['title' => 'Database', 'icon' => 'database', 'rows' => self::rows($database), 'tables' => $tables, 'autoload' => $autoload],
                ['title' => 'Server', 'icon' => 'server', 'rows' => self::rows($this->serverGroup($request))],
            ],
        ];
    }

    /**
     * The pass / warn / fail rows the System view leads with.
     *
     * @param array<string, mixed> $autoload
     * @param array<string, mixed> $cron
     * @return list<array<string, string>>
     */
    private function checks(Request $request, array $autoload, array $cron, MailSettings $mail): array
    {
        $memory = self::bytes((string) ini_get('memory_limit'));
        $opcacheOn = self::opcacheOn();
        $uploadsWritable = is_writable("{$this->webroot}/wp-content/uploads");
        $debugOn = defined('WP_DEBUG') && WP_DEBUG;
        return [
            self::check('engine', 'Minn Engine', 'pass', "{$this->engineVersion} is running; there is no WordPress on this site"),
            self::check('php', 'PHP version', version_compare(PHP_VERSION, '8.3', '>=') ? 'pass' : 'fail', version_compare(PHP_VERSION, '8.3', '>=') ? PHP_VERSION . ' is current' : PHP_VERSION . ' is below the engine floor of 8.3'),
            self::check('https', 'HTTPS', $request->secure ? 'pass' : 'warn', $request->secure ? 'Served over TLS' : 'This request is not over HTTPS'),
            self::check('memory', 'Memory limit', ($memory < 0 || $memory >= 256 * 1024 * 1024) ? 'pass' : ($memory >= 128 * 1024 * 1024 ? 'warn' : 'fail'), ini_get('memory_limit') . ' available to PHP'),
            self::check('opcache', 'OPcache', $opcacheOn ? 'pass' : 'warn', $opcacheOn ? 'Bytecode caching is on' : 'Not enabled; pages recompile each request'),
            self::check('debug', 'Debug mode', $debugOn ? 'warn' : 'pass', $debugOn ? 'WP_DEBUG is on in wp-config.php' : 'Off'),
            self::check('uploads', 'Uploads writable', $uploadsWritable ? 'pass' : 'fail', $uploadsWritable ? 'The uploads directory accepts writes' : 'Uploads directory is not writable'),
            self::check(
                'autoload',
                'Autoload size',
                $autoload['size'] < 800 * 1024 ? 'pass' : ($autoload['size'] < 3 * 1024 * 1024 ? 'warn' : 'fail'),
                "{$autoload['size_human']} across {$autoload['count']} options" . ($autoload['size'] < 800 * 1024 ? '; healthy' : ' loads on every request; see the top offenders in the Database card'),
            ),
            self::check(
                'cron',
                'Scheduled posts',
                $cron['overdue'] > 0 ? 'warn' : 'pass',
                $cron['overdue'] > 0
                    ? "{$cron['overdue']} scheduled post" . ($cron['overdue'] === 1 ? ' is' : 's are') . ' past due; cron may be stalled'
                    : ($cron['events'] === 0 ? 'Nothing is scheduled' : "{$cron['events']} scheduled, none overdue"),
            ),
            self::check('mail', 'Mail', $mail->transport === 'log' ? 'warn' : 'pass', match ($mail->transport) {
                'smtp' => 'Sent over SMTP via ' . $mail->host,
                'log' => 'Mail is written to wp-content/minn-mail.log and never sent',
                default => "Sent through PHP's mail()",
            }),
        ];
    }

    /** @param array<string, mixed> $cron @return array<string, string> */
    private function engineGroup(array $cron, MailSettings $mail): array
    {
        $debugOn = defined('WP_DEBUG') && WP_DEBUG;
        return [
            'Version' => $this->engineVersion,
            'Speaks' => 'the WordPress 7.1 operational contracts',
            'Site URL' => rtrim((string) ($this->site->option('siteurl') ?? ''), '/'),
            'Home URL' => rtrim((string) ($this->site->option('home') ?? ''), '/'),
            'Login URL' => $this->permalinks->url('/wp-login.php'),
            'Language' => (string) ($this->site->option('WPLANG') ?: 'en_US'),
            'Timezone' => (string) ($this->site->option('timezone_string') ?: self::offsetLabel((float) ($this->site->option('gmt_offset') ?? 0))),
            'Permalinks' => (string) ($this->site->option('permalink_structure') ?: 'Plain'),
            'Debug mode' => $debugOn ? ((defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) ? 'On + log' : 'On') : 'Off',
            'Mail transport' => $mail->transport,
            'Active theme' => $this->software->activeThemeLabel(),
            'Active extensions' => (string) $this->software->activeExtensionCount(),
            'Cron' => $cron['events'] === 0 ? 'nothing scheduled' : "{$cron['events']} scheduled post" . ($cron['events'] === 1 ? '' : 's') . ($cron['next'] !== null ? ', next ' . self::relative($cron['next']) : ''),
        ];
    }

    /** @return array<string, string> */
    private static function phpGroup(): array
    {
        $opcacheOn = self::opcacheOn();
        $loaded = array_values(array_filter(
            ['curl', 'gd', 'imagick', 'mbstring', 'xml', 'zip', 'intl', 'openssl', 'opcache', 'redis', 'memcached', 'apcu', 'exif', 'fileinfo', 'sodium'],
            static fn (string $ext) => extension_loaded($ext),
        ));
        return [
            'Version' => PHP_VERSION,
            'Interface (SAPI)' => PHP_SAPI,
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'max_input_vars' => (string) ini_get('max_input_vars'),
            'max_input_time' => ini_get('max_input_time') . 's',
            'OPcache' => $opcacheOn ? 'Enabled' : (function_exists('opcache_get_status') ? 'Disabled' : 'Not installed'),
            'Extensions' => implode(', ', $loaded),
            'cURL' => function_exists('curl_version') ? (string) (curl_version()['version'] ?? 'yes') : 'no',
        ];
    }

    /** @return array<string, string> */
    private function serverGroup(Request $request): array
    {
        $uploadsWritable = is_writable("{$this->webroot}/wp-content/uploads");
        $hasUname = function_exists('php_uname');
        $free = function_exists('disk_free_space') ? @disk_free_space($this->webroot) : false;
        $total = function_exists('disk_total_space') ? @disk_total_space($this->webroot) : false;
        return [
            'Web server' => $request->server['software'] !== '' ? $request->server['software'] : 'Unknown',
            'Protocol' => $request->server['protocol'],
            'HTTPS' => $request->secure ? 'Yes' : 'No',
            'Operating system' => $hasUname ? php_uname('s') . ' ' . php_uname('r') : PHP_OS,
            'Architecture' => $hasUname ? php_uname('m') : (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit'),
            'Server IP' => $request->server['address'],
            'Uploads writable' => $uploadsWritable ? 'Yes' : 'No',
            'Disk free' => ($free && $total) ? Logs::human((int) $free) . ' free of ' . Logs::human((int) $total) : 'Unknown',
        ];
    }

    private static function opcacheOn(): bool
    {
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        return is_array($opcache) && !empty($opcache['opcache_enabled']);
    }

    /** The wp-config debug constants as they stand; the engine never rewrites the file. */
    public function config(): array
    {
        $names = [
            'WP_DEBUG' => ['Debug mode', 'Master switch for debugging.'],
            'WP_DEBUG_LOG' => ['Log to file', 'Write notices and errors to wp-content/debug.log.'],
            'WP_DEBUG_DISPLAY' => ['Show errors on screen', 'Render errors in the page. Leave off in production and read the log instead.'],
            'SCRIPT_DEBUG' => ['Unminified assets', 'Load the full-length JS/CSS.'],
            'SAVEQUERIES' => ['Log database queries', 'Record every query for inspection; turn off when done.'],
        ];
        $source = (string) @file_get_contents("{$this->webroot}/wp-config.php");
        $constants = [];
        foreach ($names as $name => [$label, $desc]) {
            $constants[] = [
                'name' => $name,
                'label' => $label,
                'desc' => $desc,
                'value' => defined($name) && (bool) constant($name),
                'in_config' => preg_match("/define\\(\\s*(['\"])" . preg_quote($name, '/') . "\\1/", $source) === 1,
                'locked' => true,
            ];
        }
        $log = $this->logs->debugLogPath();
        return [
            'editable' => false,
            'writable' => false,
            'disallowed' => true,
            'constants' => $constants,
            'log' => [
                'path' => ltrim(str_replace($this->webroot, '', $log), '/'),
                'exists' => is_file($log),
                'size_human' => is_file($log) ? Logs::human((int) filesize($log)) : '0 B',
            ],
        ];
    }

    /** The scheduled events: the cron option's hooks and the engine's own scheduled posts, soonest first. */
    public function cron(): array
    {
        $now = time();
        $items = $this->cronOptionEvents();
        foreach ($this->futurePosts() as $row) {
            $next = (int) strtotime($row['post_date_gmt'] . ' UTC');
            $items[] = [
                'hook' => 'minn_publish_post',
                'next' => $next,
                'overdue' => $next < $now - 300,
                'recurrence' => 'One-off',
                'args' => 1,
                'title' => (string) $row['post_title'],
            ];
        }
        usort($items, static fn (array $a, array $b): int => $a['next'] <=> $b['next']);
        return ['now' => $now, 'disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'items' => $items];
    }

    /**
     * The cron option's events as rows, a recurring event's interval read
     * as its recurrence and a single event marked one-off.
     *
     * @return list<array<string, mixed>>
     */
    private function cronOptionEvents(): array
    {
        $now = time();
        $items = [];
        foreach (CronTable::fromBlob($this->site->option('cron')) as $timestamp => $hooks) {
            foreach ($hooks as $hook => $keys) {
                foreach ((array) $keys as $entry) {
                    $interval = isset($entry['interval']) ? (int) $entry['interval'] : 0;
                    $items[] = [
                        'hook' => (string) $hook,
                        'next' => (int) $timestamp,
                        'overdue' => (int) $timestamp < $now - 300,
                        'recurrence' => $interval > 0 ? self::humanInterval($interval) : 'One-off',
                        'args' => count((array) ($entry['args'] ?? [])),
                        'title' => '',
                    ];
                }
            }
        }
        return $items;
    }

    /** A recurrence interval as its two largest whole units. */
    private static function humanInterval(int $seconds): string
    {
        $units = [['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
        $parts = [];
        foreach ($units as [$name, $length]) {
            if ($seconds < $length) {
                continue;
            }
            $count = intdiv($seconds, $length);
            $seconds -= $count * $length;
            $parts[] = $count . ' ' . $name . ($count === 1 ? '' : 's');
            if (count($parts) === 2) {
                break;
            }
        }
        return $parts === [] ? '0 seconds' : implode(' ', $parts);
    }

    /** The autoloaded options: the summary and the largest rows. */
    public function autoload(): array
    {
        $summary = $this->autoloadSummary();
        $rows = $this->db->rows(
            "SELECT option_name AS name, LENGTH(option_value) AS len, autoload FROM {$this->db->table('options')} WHERE autoload IN (?) ORDER BY len DESC LIMIT 200",
            [self::AUTOLOAD_VALUES],
        );
        return [
            'count' => $summary['count'],
            'size' => $summary['size'],
            'size_human' => $summary['size_human'],
            'shown' => count($rows),
            'items' => array_map(static fn (array $r) => [
                'name' => (string) $r['name'],
                'size' => (int) $r['len'],
                'sizeh' => Logs::human((int) $r['len']),
                'autoload' => (string) $r['autoload'],
            ], $rows),
        ];
    }

    private function autoloadSummary(): array
    {
        $options = $this->db->table('options');
        $totals = $this->db->row("SELECT COUNT(*) AS c, COALESCE(SUM(LENGTH(option_value)), 0) AS s FROM {$options} WHERE autoload IN (?)", [self::AUTOLOAD_VALUES]);
        $top = $this->db->rows("SELECT option_name AS name, LENGTH(option_value) AS len FROM {$options} WHERE autoload IN (?) ORDER BY len DESC LIMIT 8", [self::AUTOLOAD_VALUES]);
        $size = (int) ($totals['s'] ?? 0);
        return [
            'count' => (int) ($totals['c'] ?? 0),
            'size' => $size,
            'size_human' => Logs::human($size),
            'top' => array_map(static fn (array $r) => ['name' => (string) $r['name'], 'size' => Logs::human((int) $r['len'])], $top),
        ];
    }

    /** @return array{events: int, overdue: int, next: ?int} */
    private function cronSummary(): array
    {
        $now = time();
        $events = 0;
        $overdue = 0;
        $next = null;
        foreach ($this->cron()['items'] as $item) {
            $at = (int) $item['next'];
            $events++;
            if ($item['overdue']) {
                $overdue++;
            }
            $next = $next === null ? $at : min($next, $at);
        }
        return ['events' => $events, 'overdue' => $overdue, 'next' => $next];
    }

    /** @return list<array<string, mixed>> */
    private function futurePosts(): array
    {
        return $this->db->rows(
            "SELECT ID, post_title, post_date_gmt FROM {$this->db->table('posts')} WHERE post_status = 'future' ORDER BY post_date_gmt ASC LIMIT 200",
        );
    }

    /** @return array{0: array<string, string>, 1: list<array>} */
    private function databaseGroup(): array
    {
        $connection = $this->db->connection();
        $info = (string) $connection->server_info;
        $prefix = $this->db->prefix();
        $rows = $this->db->rows(
            'SELECT TABLE_NAME AS name, DATA_LENGTH + INDEX_LENGTH AS size, TABLE_ROWS AS rows_est FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE ? ORDER BY size DESC',
            [str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%'],
        );
        $size = 0;
        $top = [];
        foreach ($rows as $i => $table) {
            $size += (int) $table['size'];
            if ($i < 5) {
                $top[] = ['name' => (string) $table['name'], 'size' => Logs::human((int) $table['size']), 'rows' => number_format((int) $table['rows_est'])];
            }
        }
        $expired = $this->db->row(
            "SELECT COUNT(*) AS c, COALESCE(SUM(LENGTH(b.option_value)), 0) AS s FROM {$this->db->table('options')} a
             LEFT JOIN {$this->db->table('options')} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
             WHERE a.option_name LIKE '\\_transient\\_timeout\\_%' AND a.option_value < UNIX_TIMESTAMP()",
        );
        $database = [
            'Engine' => stripos($info, 'maria') !== false ? 'MariaDB' : 'MySQL',
            'Version' => $info,
            'Host' => defined('DB_HOST') ? (string) DB_HOST : '',
            'Name' => defined('DB_NAME') ? (string) DB_NAME : '',
            'Charset' => defined('DB_CHARSET') ? (string) DB_CHARSET : $connection->character_set_name(),
            'Collation' => defined('DB_COLLATE') && DB_COLLATE !== '' ? (string) DB_COLLATE : '(default)',
            'Prefix' => $prefix,
            'Tables' => (string) count($rows),
            'Size' => Logs::human($size),
            'Expired transients' => number_format((int) ($expired['c'] ?? 0)) . ((int) ($expired['s'] ?? 0) > 0 ? ' (' . Logs::human((int) $expired['s']) . ')' : ''),
        ];
        return [$database, $top];
    }

    private static function check(string $key, string $label, string $status, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /** @return list<array{key: string, value: string}> */
    private static function rows(array $pairs): array
    {
        $out = [];
        foreach ($pairs as $key => $value) {
            $out[] = ['key' => (string) $key, 'value' => (string) $value];
        }
        return $out;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (float) $value;
        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private static function offsetLabel(float $offset): string
    {
        $sign = $offset < 0 ? '-' : '+';
        $hours = (int) abs($offset);
        $minutes = (int) round((abs($offset) - $hours) * 60);
        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    private static function relative(int $at): string
    {
        $delta = $at - time();
        if ($delta <= 0) {
            return 'due now';
        }
        if ($delta < 3600) {
            return 'in ' . max(1, intdiv($delta, 60)) . ' min';
        }
        if ($delta < 86400) {
            return 'in ' . intdiv($delta, 3600) . ' h';
        }
        return 'in ' . intdiv($delta, 86400) . ' d';
    }
}
