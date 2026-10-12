<?php
/**
 * The audit's Lows (2026-10-12) that live in the engine's own classes:
 * the app's asset route serves its assets folder and nothing else, the
 * development mail log is a PHP file that ends a request for it, and a
 * session keeps the request's address and user agent to the shape its
 * store is read back by.
 */

declare(strict_types=1);

use Minn\Admin\App;
use Minn\Auth\Sessions;
use Minn\Mail\MailLog;

$dir = sys_get_temp_dir() . '/minn-unit-audit-lows-' . getmypid();
@mkdir("{$dir}/app/assets/css", 0755, true);
file_put_contents("{$dir}/app/assets/css/app.css", 'a{}');
file_put_contents("{$dir}/app/assets/.secret", 'x');
file_put_contents("{$dir}/app/readme.md", '# app');
file_put_contents("{$dir}/app/minn-admin.php", '<?php');
register_shutdown_function(static function () use ($dir): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $node) {
        $node->isDir() ? @rmdir($node->getPathname()) : @unlink($node->getPathname());
    }
    @rmdir($dir);
});
$app = new App("{$dir}/app");
$served = static fn (string $path) => $app->asset($path) !== null;

return [
    'an asset is served' => static fn () => $served('assets/css/app.css') ?: 'refused',
    'a file beside the assets folder is not' => static fn () => !$served('assets/../readme.md') ?: 'served',
    'the plugin file is not' => static fn () => !$served('assets/../minn-admin.php') ?: 'served',
    'a path outside the assets folder is not' => static fn () => !$served('readme.md') ?: 'served',
    'a hidden file in the assets folder is not' => static fn () => !$served('assets/.secret') ?: 'served',
    'a path out of the app is not' => static fn () => !$served('assets/../../app/readme.md') ?: 'served',
    'the mail log opens with a line that exits' => static function () use ($dir) {
        $file = "{$dir}/minn-mail.log.php";
        MailLog::write($file, 'site@example.org', 'Site', ['a@example.org'], 'One', 'Body one');
        MailLog::write($file, 'site@example.org', '', ['b@example.org'], 'Two', 'Body two');
        $text = (string) file_get_contents($file);
        $messages = MailLog::read($file);
        return str_starts_with($text, MailLog::GUARD) && substr_count($text, MailLog::GUARD) === 1 && array_column($messages, 'subject') === ['One', 'Two'] && $messages[0]['from'] === 'Site <site@example.org>'
            ?: json_encode([substr($text, 0, 40), $messages]);
    },
    'the mail log is named as PHP' => static fn () => str_ends_with(MailLog::forSite(), 'wp-content/minn-mail.log.php') ?: MailLog::forSite(),
    'a session field keeps printable characters only, and 255 of them' => static function () {
        $plain = Sessions::plain("Agent\";s:5:\"x\";{evil}\x01\nend" . str_repeat('a', 300));
        return $plain === 'Agents:5:xevilend' . str_repeat('a', 238) ?: $plain;
    },
];
