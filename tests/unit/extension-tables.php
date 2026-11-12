<?php

declare(strict_types=1);

use Minn\Content\Reader;
use Minn\Extension\Manifest;
use Minn\Extension\Seams;
use Minn\Extension\Tables;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * An extension's own tables and REST routes, the parts that need no site:
 * minn.json "tables" read as name => lines, the CREATE TABLE statements
 * built under the prefix with names held to the extension's own, the
 * marker that says when to apply them again, and Seams::rest refusing a
 * namespace the engine keeps. tests/extension-records.test.php drives
 * both on the test site.
 */
$scratch = sys_get_temp_dir() . '/minn-tables-unit-' . getmypid();
$manifest = static function (string $slug, array $extra = []) use ($scratch): Manifest {
    $dir = "{$scratch}/{$slug}";
    @mkdir($dir, 0755, true);
    file_put_contents("{$dir}/minn.json", Minn\Json::encode($extra + ['name' => $slug, 'version' => '1.2.0', 'extension' => 'Minn\\Ext\\Unit\\Extension']));
    $read = Manifest::read($dir) ?? throw new RuntimeException("{$slug}: no manifest");
    exec('rm -rf ' . escapeshellarg($scratch));
    return $read;
};
$thrown = static function (Closure $call): string {
    try {
        $call();
        return 'nothing thrown';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
};
$notes = ['acme_notes' => ['id bigint(20) unsigned NOT NULL AUTO_INCREMENT', 'body text NOT NULL', 'PRIMARY KEY  (id)']];
$db = new Minn\Db(new mysqli(), 'wp_');
$seams = static fn (): Seams => new Seams($db, new Minn\Content\Site($db), new Request(Method::Get, '/', [], [], [], '', true, 'unit.test'), Reader::anonymous());

return [
    'tables read as name => lines; a name without a list of strings is left out' => static function () use ($manifest, $notes): bool|string {
        $read = $manifest('acme', ['tables' => $notes + ['acme_bad' => 'id int', 'acme_mixed' => ['id int', 7], 'acme_empty' => []]])->tables;
        $none = $manifest('quiet')->tables;
        return $read === $notes && $none === [] ? true : json_encode([$read, $none]);
    },
    'statements are CREATE TABLE IF NOT EXISTS under the prefix, one line per definition, in the site collation' => static function () use ($manifest, $notes): bool|string {
        $statements = Tables::statements($manifest('acme', ['tables' => $notes]), 'wp_', 'utf8mb4_unicode_520_ci');
        $expected = "CREATE TABLE IF NOT EXISTS wp_acme_notes (\n  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  body text NOT NULL,\n  PRIMARY KEY  (id)\n) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci";
        return $statements === ['wp_acme_notes' => $expected] ? true : json_encode($statements);
    },
    'a slug with dashes owns names with underscores' => static function () use ($manifest): bool|string {
        $statements = Tables::statements($manifest('acme-shop', ['tables' => ['acme_shop_orders' => ['id int']]]), 'zz_');
        return array_keys($statements) === ['zz_acme_shop_orders'] ? true : json_encode($statements);
    },
    'a table outside the extension\'s own names throws' => static function () use ($manifest, $thrown): bool|string {
        $said = [
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['posts' => ['id int']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme' => ['id int']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_Notes' => ['id int']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_' . str_repeat('x', 60) => ['id int']]]), 'wp_')),
        ];
        $refused = array_filter($said, static fn (string $message): bool => str_starts_with($message, 'acme may declare only tables named acme_*: '));
        return count($refused) === 4 ? true : json_encode($said);
    },
    // Security sweep 2026-11-12 (1.5): a slug that is a core table's start, and another extension's names.
    'no extension declares a core table or another extension\'s, however its slug reads' => static function () use ($manifest, $thrown): bool|string {
        $said = [
            $thrown(static fn () => Tables::statements($manifest('term', ['tables' => ['term_relationships' => ['id int']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('comment', ['tables' => ['commentmeta' => ['id int']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_x_orders' => ['id int']]]), 'wp_', 'utf8mb4_unicode_520_ci', ['acme-x'])),
        ];
        $kept = Tables::statements($manifest('acme', ['tables' => ['acme_orders' => ['id int']]]), 'wp_', 'utf8mb4_unicode_520_ci', ['acme-x', 'other']);
        return !in_array('nothing thrown', $said, true) && array_keys($kept) === ['wp_acme_orders'] ? true : json_encode([$said, array_keys($kept)]);
    },
    'a line that is more than one definition throws' => static function () use ($manifest, $thrown): bool|string {
        $said = [
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_x' => ['id int); DROP TABLE wp_users; --']]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_x' => ["id int,\nother int"]]]), 'wp_')),
            $thrown(static fn () => Tables::statements($manifest('acme', ['tables' => ['acme_x' => ['  ']]]), 'wp_')),
        ];
        foreach ($said as $message) {
            if (!str_starts_with($message, 'acme: table acme_x has a line that is not one column or key definition')) {
                return json_encode($said);
            }
        }
        return true;
    },
    'the marker changes with the version and with the declaration, not otherwise' => static function () use ($manifest, $notes): bool|string {
        $a = Tables::marker($manifest('acme', ['tables' => $notes]));
        $same = Tables::marker($manifest('acme', ['tables' => $notes]));
        $bumped = Tables::marker($manifest('acme', ['version' => '1.3.0', 'tables' => $notes]));
        $grown = Tables::marker($manifest('acme', ['tables' => ['acme_notes' => [...$notes['acme_notes'], 'KEY body (body(20))']]]));
        return str_starts_with($a, '1.2.0:') && $a === $same && $bumped !== $a && str_starts_with($bumped, '1.3.0:') && $grown !== $a ? true : json_encode([$a, $same, $bumped, $grown]);
    },
    'Seams::rest takes routes in a namespace of the extension\'s own' => static function () use ($seams): bool|string {
        $own = new class () {
            #[Route(Method::Get, '/acme/v1/notes', policy: new Policy(Access::SignedIn))]
            public function notes(): Response
            {
                return Response::json([]);
            }
        };
        $minn = $seams();
        $minn->rest($own);
        return $minn->registrations()->rest === [$own] ? true : 'not registered';
    },
    'Seams::rest refuses wp, wp-*, oembed, minn-admin and namespace-less patterns' => static function () use ($seams, $thrown): bool|string {
        $said = [];
        foreach (['/wp/v2/types', '/wp-site-health/v1/tests', '/oembed/1.0/embed', '/minn-admin/v1/boot', '/notes'] as $pattern) {
            $controller = eval('return new class () { #[\Minn\Http\Route(\Minn\Http\Method::Get, ' . var_export($pattern, true) . ')] public function go(): \Minn\Http\Response { return \Minn\Http\Response::json([]); } };');
            $minn = $seams();
            $said[$pattern] = $thrown(static fn () => $minn->rest($controller));
            if ($minn->registrations()->rest !== []) {
                return "{$pattern} was registered";
            }
        }
        foreach ($said as $pattern => $message) {
            if ($message !== "REST route {$pattern} is not in a namespace of the extension's own (/acme/v1/...)") {
                return json_encode($said);
            }
        }
        return true;
    },
];
