<?php

declare(strict_types=1);

use Minn\Content\Reader;
use Minn\Extension\Manifest;
use Minn\Extension\Seams;
use Minn\Http\Method;
use Minn\Http\Request;

/**
 * What an extension is granted: needs.http in its minn.json read into the
 * hosts its $minn->http may reach, a client that throws (naming the key)
 * when the manifest did not ask, and one $minn per extension, so what a
 * closure is handed is its own extension's.
 */
$scratch = sys_get_temp_dir() . '/minn-grants-unit-' . getmypid();
$manifest = static function (string $slug, array $extra = []) use ($scratch): Manifest {
    $dir = "{$scratch}/{$slug}";
    @mkdir($dir, 0755, true);
    file_put_contents("{$dir}/minn.json", Minn\Json::encode(['name' => $slug, 'extension' => 'Minn\\Ext\\Unit\\Extension'] + $extra));
    return Manifest::read($dir) ?? throw new RuntimeException("{$slug}: no manifest");
};
$db = new Minn\Db(new mysqli(), 'wp_');
$seams = static fn (): Seams => new Seams($db, new Minn\Content\Site($db), new Request(Method::Get, '/', [], [], [], '', true, 'unit.test'), Reader::anonymous());
$thrown = static function (Closure $call): string {
    try {
        $call();
        return 'nothing thrown';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
};

return [
    'needs.http reads as a grant: true for any host, prefixes closed after the host, nothing when not asked' => static function () use ($manifest, $scratch): bool|string {
        $rows = [
            $manifest('any', ['needs' => ['http' => true]])->http,
            $manifest('listed', ['needs' => ['http' => ['https://api.example.com', 'https://cdn.example.net/v2/', 'ftp://nope.example/', 7]]])->http,
            $manifest('one', ['needs' => ['http' => 'https://api.example.com']])->http,
            $manifest('silent')->http,
            $manifest('empty', ['needs' => ['http' => []]])->http,
            $manifest('declined', ['needs' => ['http' => false]])->http,
        ];
        exec('rm -rf ' . escapeshellarg($scratch));
        return $rows === [[], ['https://api.example.com/', 'https://cdn.example.net/v2/'], ['https://api.example.com/'], null, null, null] ? true : json_encode($rows);
    },
    'an extension that did not ask for http is told the key at its first request' => static function () use ($manifest, $seams, $thrown, $scratch): bool|string {
        $minn = $seams()->forExtension($manifest('acme-footer'));
        exec('rm -rf ' . escapeshellarg($scratch));
        $said = $thrown(static fn () => $minn->http->get('https://api.example.com/status'));
        return $said === 'acme-footer did not ask for http: add "needs": {"http": ["https://api.example.com/"]} to its minn.json.' ? true : $said;
    },
    'an extension that asked is bound to its hosts' => static function () use ($manifest, $seams, $scratch): bool|string {
        $minn = $seams()->forExtension($manifest('acme-status', ['needs' => ['http' => ['https://api.example.com/']]]));
        exec('rm -rf ' . escapeshellarg($scratch));
        $reply = $minn->http->get('https://other.example/', hosts: ['https://other.example/']);
        return $reply->failed() && $reply->errno === 0 && str_starts_with((string) $reply->error, 'other.example is not one of the hosts this client was granted') ? true : (string) $reply->error;
    },
    'the shared seams carry no http of their own' => static function () use ($seams, $thrown): bool|string {
        $said = $thrown(static fn () => $seams()->http->get('https://api.example.com/'));
        return str_contains($said, 'needs') ? true : $said;
    },
    'each closure is handed its own extension\'s seams, in registration order' => static function () use ($manifest, $seams, $scratch): bool|string {
        $shared = $seams();
        $one = $shared->forExtension($manifest('one', ['needs' => ['http' => ['https://one.example/']]]));
        $two = $shared->forExtension($manifest('two'));
        exec('rm -rf ' . escapeshellarg($scratch));
        $one->footer(static fn (Seams $minn): string => $minn === $one ? '[one]' : '[wrong]');
        $one->shortcode('who', static fn (array $attributes, ?string $content, Seams $minn): string => $minn === $one ? 'one' : 'wrong');
        $two->footer(static fn (Seams $minn): string => $minn === $two ? '[two]' : '[wrong]');
        $two->title(static fn (string $title): string => "{$title} (two)");
        $shared->adopt($one);
        $shared->adopt($two);
        $runner = new Minn\Extension\SeamRunner($shared, $shared->registrations());
        $footer = $runner->footer();
        $code = Minn\Extension\Shortcodes::apply('[who]', $shared->registrations()->shortcodes, $shared);
        $title = $runner->title('Home');
        return $footer === '[one][two]' && $code === 'one' && $title === 'Home (two)' ? true : json_encode([$footer, $code, $title]);
    },
    'what an extension registers reaches the page only once it is adopted' => static function () use ($manifest, $seams, $scratch): bool|string {
        $shared = $seams();
        $broken = $shared->forExtension($manifest('broken'));
        exec('rm -rf ' . escapeshellarg($scratch));
        $broken->footer(static fn (): string => '[half]');
        $broken->bodyClass('half-registered');
        $registered = $shared->registrations();
        return $registered->footer === [] && $registered->bodyClasses === [] ? true : json_encode($registered->bodyClasses);
    },
];
