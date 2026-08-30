<?php

declare(strict_types=1);

/**
 * The interactive lexicon: contracts/lexicon.md is the source, /lexicon/
 * is the page, /lexicon.md is the same file raw. Engine-only; the
 * reference 404s this path on purpose.
 *
 *   php tests/lexicon.test.php
 */

putenv('MINN_TEST_KEEP_THEME=1');
require __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn-engine.localhost', '/');
$MD = $ROOT . '/contracts/lexicon.md';

require_once "$ROOT/public/minn/src/Minn/Autoloader.php";
Minn\Autoloader::register();

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($detail === '' ? '' : ": $detail") . "\n";
    }
};

$check(is_file($MD), 'contracts/lexicon.md is on disk');
$located = Minn\Front\Lexicon::locate("$ROOT/public/minn");
$check($located === $MD, 'locate() finds the site-root contracts file', (string) $located);
$check(Minn\Front\Lexicon::locate(sys_get_temp_dir() . '/minn-no-lexicon/x') === null, 'locate() is null when the file is absent');

$lexicon = Minn\Front\Lexicon::fromFile($MD);
$check($lexicon->title === 'The WordPress lexicon', 'parsed title', $lexicon->title);
$ids = [];
$filterableIntro = 0;
$ordered = 0;
foreach ($lexicon->blocks as $block) {
    if ($block->kind === Minn\Front\LexiconKind::Heading) {
        $ids[] = $block->id;
    }
    if ($block->kind === Minn\Front\LexiconKind::Table && $block->filterable && $block->status === null) {
        $filterableIntro++;
    }
    if ($block->kind === Minn\Front\LexiconKind::List && $block->ordered) {
        $ordered++;
    }
}
foreach (['speak', 'hear', 'mute', 'three-audiences', 'three-statuses', 'agent-checklist'] as $id) {
    $check(in_array($id, $ids, true), "heading id $id");
}
$check($lexicon->familyCount() > 40, 'familyCount covers the glossary tables', (string) $lexicon->familyCount());
$check($filterableIntro === 0, 'intro tables are not filterable');
$check($ordered >= 2, 'numbered markdown lists stay ordered', (string) $ordered);

$families = [];
foreach ($lexicon->blocks as $block) {
    if ($block->filterable) {
        foreach ($block->rows as $row) {
            $families[] = $row[0] ?? '';
        }
    }
}
foreach (['Portable site unit', 'Admin menu registration', 'Customizer', 'XML-RPC / pingback / trackback'] as $name) {
    $found = false;
    foreach ($families as $family) {
        if (str_contains($family, $name)) {
            $found = true;
            break;
        }
    }
    $check($found, "family present: $name");
}

$check(
    Minn\Front\LexiconPage::inline('use `wp/v2` and **Speak** <x>') === 'use <code>wp/v2</code> and <strong>Speak</strong> &lt;x&gt;',
    'inline markdown: code, bold, escaped HTML',
    Minn\Front\LexiconPage::inline('use `wp/v2` and **Speak** <x>'),
);

$fetch = static function (string $url, bool $follow = true) use ($ENGINE): array {
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => $follow ? 1 : 0],
    ]);
    $body = (string) @file_get_contents($ENGINE . $url, false, $ctx);
    $status = 0;
    $type = '';
    $location = '';
    $powered = '';
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($header, 'Content-Type:') === 0) {
            $type = trim(substr($header, 13));
        } elseif (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, 9));
        } elseif (stripos($header, 'X-Powered-By:') === 0) {
            $powered = trim(substr($header, 13));
        }
    }
    return [$status, $type, $body, $location, $powered];
};

[$status, $type, $html, , $powered] = $fetch('/lexicon/');
$check($status === 200, '/lexicon/ answers 200', (string) $status);
$check(str_contains($type, 'text/html'), '/lexicon/ is HTML', $type);
$check($powered === 'Minn Engine', 'X-Powered-By is Minn Engine', $powered);
$check(str_contains($html, '<title>The WordPress lexicon'), 'document title names the lexicon');
$check(str_contains($html, 'class="minn-lex-page"'), 'body uses the lexicon chrome');
$check(str_contains($html, 'href="#content">Skip to content'), 'skip link targets main');
$check(str_contains($html, 'data-lex-filter="speak"'), 'Speak chip is present');
$check(str_contains($html, 'data-lex-filter="hear"'), 'Hear chip is present');
$check(str_contains($html, 'data-lex-filter="mute"'), 'Mute chip is present');
$check(str_contains($html, 'id="minn-lex-q"'), 'search field is present');
$check(str_contains($html, 'href="/lexicon.md"') || str_contains($html, 'href="' . $ENGINE . '/lexicon.md"'), 'source markdown is linked');
$check(str_contains($html, 'aria-current="page"'), 'nav marks the lexicon current');
$check(str_contains($html, '/wp-content/themes/minn-site/style.css'), 'theme stylesheet is linked');
$check(str_contains($html, 'data-lex-section') && str_contains($html, 'data-status="speak"'), 'Speak wraps a filterable section');
$check(str_contains($html, 'Portable site unit'), 'a Speak family renders');
$check(str_contains($html, 'Admin menu registration'), 'a Hear family renders');
$check(str_contains($html, 'Customizer'), 'a Mute family renders');
$rowCount = substr_count($html, '<tr data-lex-row');
$check($rowCount === $lexicon->familyCount(), 'rendered rows match familyCount', $rowCount . ' vs ' . $lexicon->familyCount());
$check(!preg_match('/<tr data-lex-row[^>]*>\s*<td><strong>Tooling/', $html), 'audience rows are not filterable');
$check(str_contains($html, '<ol>'), 'graduation steps render as an ordered list');
$check(str_contains($html, 'Minn needs to speak WordPress'), 'the page is sourced from the markdown');

[$mdStatus, $mdType, $mdBody] = $fetch('/lexicon.md');
$check($mdStatus === 200, '/lexicon.md answers 200', (string) $mdStatus);
$check(str_contains($mdType, 'text/markdown'), '/lexicon.md is markdown', $mdType);
$check($mdBody === str_replace("\r\n", "\n", (string) file_get_contents($MD)), '/lexicon.md is the contracts file byte-for-byte');

[$slashless, , , $location] = $fetch('/lexicon', false);
$check($slashless === 301, '/lexicon 301s to the trailing slash', (string) $slashless);
$check(str_ends_with(rtrim($location, '/'), '/lexicon') || str_contains($location, '/lexicon/'), '/lexicon Location is /lexicon/', $location);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
