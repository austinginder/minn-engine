<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Support\Html;

/** The /code-size/ page: two stacks side by side, then the breakdown and the counting rules. */
final readonly class CodeSizePage
{
    public function __construct(
        private CodeSize $report,
        private Permalinks $permalinks,
        private string $siteName,
        private SiteChrome $chrome,
        private string $themeUri,
    ) {
    }

    public function html(): string
    {
        $wordpress = $this->report->stack('wordpress');
        $minn = $this->report->stack('minn');
        $title = 'Code size · ' . Html::esc($this->siteName);
        return '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n"
            . '<meta charset="UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<title>' . $title . '</title>' . "\n"
            . $this->chrome->stylesheet($this->themeUri)
            . '</head>' . "\n"
            . '<body class="minn-lex-page minn-size-page">' . "\n"
            . '<a class="skip-link" href="#content">Skip to content</a>' . "\n"
            . $this->chrome->part('header', CodeSizeController::PATH)
            . '<main id="content" class="minn-lexicon minn-size">' . "\n"
            . '<header class="minn-lex-hero"><span class="minn-kicker">By the numbers</span><h1>'
            . Html::esc($wordpress['label'] . ' ' . $wordpress['version']) . ' and ' . Html::esc($minn['label'] . ' ' . $minn['version']) . '</h1>'
            . '<p>The code that ships to a site, measured the same way on both sides: PHP, JavaScript and CSS, minified duplicates skipped, bundled libraries on their own row. Measured ' . Html::esc($this->report->measured) . ' from the ' . Html::esc($wordpress['label'] . ' ' . $wordpress['version']) . ' release zip and the ' . Html::esc($this->report->versionLabel('minn')) . ' trees.</p>'
            . '</header>' . "\n"
            . $this->cards($wordpress, $minn)
            . $this->ratio($wordpress, $minn)
            . $this->languages($wordpress, $minn)
            . $this->breakdown($wordpress)
            . $this->breakdown($minn)
            . $this->method()
            . '</main>' . "\n"
            . $this->chrome->part('footer', CodeSizeController::PATH)
            . '</body>' . "\n" . '</html>' . "\n";
    }

    private function cards(array $wordpress, array $minn): string
    {
        $max = max($wordpress['totals']['lines'], $minn['totals']['lines'], 1);
        $out = '<div class="minn-size-cards">' . "\n";
        foreach ([$wordpress, $minn] as $stack) {
            $width = (int) round(100 * $stack['totals']['lines'] / $max);
            $out .= '<section class="minn-size-card" data-stack="' . Html::attr($stack['id']) . '">'
                . '<h2>' . Html::esc($stack['label']) . ' <span class="minn-size-version">' . Html::esc($stack['version']) . (isset($stack['adminVersion']) ? ' + Minn Admin ' . Html::esc($stack['adminVersion']) : '') . '</span></h2>'
                . '<p class="minn-size-big">' . self::n($stack['totals']['lines']) . '</p>'
                . '<p class="minn-size-sub">lines in ' . self::n($stack['totals']['files']) . ' files, ' . self::mb($stack['totals']['bytes']) . '</p>'
                . '<div class="minn-size-bar" role="img" aria-label="' . Html::attr(self::n($stack['totals']['lines']) . ' lines') . '"><span style="width:' . $width . '%"></span></div>'
                . '<p class="minn-size-sub">' . self::n($stack['own']['lines']) . ' lines of its own code, ' . self::n($stack['thirdParty']['lines']) . ' in bundled libraries (' . self::mb($stack['thirdParty']['bytes']) . ')</p>'
                . '</section>' . "\n";
        }
        return $out . '</div>' . "\n";
    }

    private function ratio(array $wordpress, array $minn): string
    {
        $all = number_format(CodeSize::ratio($wordpress['totals']['lines'], $minn['totals']['lines']), 1);
        $own = number_format(CodeSize::ratio($wordpress['own']['lines'], $minn['own']['lines']), 1);
        $bytes = number_format(CodeSize::ratio($wordpress['totals']['bytes'], $minn['totals']['bytes']), 1);
        return '<p class="minn-size-ratio">' . Html::esc($wordpress['label'] . ' ' . $wordpress['version']) . ' ships ' . Html::esc($all) . ' times the lines of ' . Html::esc($this->report->versionLabel('minn')) . ', ' . Html::esc($own) . ' times counting only each side\'s own code, and ' . Html::esc($bytes) . ' times the bytes.</p>' . "\n"
            . '<p>This is a size comparison, not a feature comparison. WordPress core carries the block editor packages, TinyMCE, the Customizer, the media library and every admin screen; Minn carries one interface (Minn Admin), renders block themes, and runs plugins through a facade that maps their calls onto the engine. Smaller is the point. What Minn does not do yet is on the <a href="' . Html::attr($this->permalinks->url('/#status')) . '">status list</a>.</p>' . "\n";
    }

    private function languages(array $wordpress, array $minn): string
    {
        $names = ['php' => 'PHP', 'js' => 'JavaScript', 'css' => 'CSS'];
        $out = '<h2 id="languages">By language</h2>' . "\n"
            . '<div class="minn-lex-table-wrap"><table>' . "\n"
            . '<thead><tr><th>Language</th><th class="minn-num">' . Html::esc($wordpress['label']) . ' lines</th><th class="minn-num">' . Html::esc($minn['label']) . ' lines</th><th class="minn-num">Ratio</th><th class="minn-num">' . Html::esc($wordpress['label']) . ' files</th><th class="minn-num">' . Html::esc($minn['label']) . ' files</th></tr></thead>' . "\n<tbody>\n";
        foreach ($names as $key => $name) {
            $a = $wordpress['totals']['languages'][$key] ?? ['files' => 0, 'lines' => 0];
            $b = $minn['totals']['languages'][$key] ?? ['files' => 0, 'lines' => 0];
            $out .= '<tr><td>' . $name . '</td>' . self::num($a['lines']) . self::num($b['lines']) . '<td class="minn-num">' . Html::esc((string) CodeSize::ratio($a['lines'], $b['lines'])) . '×</td>' . self::num($a['files']) . self::num($b['files']) . '</tr>' . "\n";
        }
        $out .= '<tr><td><strong>Total</strong></td>' . self::num($wordpress['totals']['lines']) . self::num($minn['totals']['lines']) . '<td class="minn-num">' . Html::esc((string) CodeSize::ratio($wordpress['totals']['lines'], $minn['totals']['lines'])) . '×</td>' . self::num($wordpress['totals']['files']) . self::num($minn['totals']['files']) . '</tr>' . "\n";
        return $out . '</tbody></table></div>' . "\n";
    }

    private function breakdown(array $stack): string
    {
        $id = Html::attr($stack['id']);
        $out = '<h2 id="' . $id . '">' . Html::esc($stack['label'] . ' ' . $stack['version']) . '</h2>' . "\n"
            . '<p>' . Html::esc((string) $stack['describe']) . '. Source: ' . Html::esc((string) $stack['source']) . '.</p>' . "\n"
            . '<div class="minn-lex-table-wrap"><table>' . "\n"
            . '<thead><tr><th>Part</th><th class="minn-num">Files</th><th class="minn-num">PHP</th><th class="minn-num">JS</th><th class="minn-num">CSS</th><th class="minn-num">Lines</th><th class="minn-num">Size</th></tr></thead>' . "\n<tbody>\n";
        foreach ($stack['components'] as $row) {
            $note = $row['note'] !== '' ? '<br /><span class="minn-size-note">' . Html::esc((string) $row['note']) . '</span>' : '';
            $out .= '<tr' . ($row['thirdParty'] ? ' class="minn-size-third"' : '') . '><td>' . Html::esc((string) $row['label']) . $note . '</td>'
                . self::num($row['files'])
                . self::num($row['languages']['php']['lines'] ?? 0)
                . self::num($row['languages']['js']['lines'] ?? 0)
                . self::num($row['languages']['css']['lines'] ?? 0)
                . self::num($row['lines'])
                . '<td class="minn-num">' . Html::esc(self::mb($row['bytes'])) . '</td></tr>' . "\n";
        }
        $t = $stack['totals'];
        $out .= '<tr><td><strong>Total</strong></td>' . self::num($t['files']) . self::num($t['languages']['php']['lines'] ?? 0) . self::num($t['languages']['js']['lines'] ?? 0) . self::num($t['languages']['css']['lines'] ?? 0) . self::num($t['lines']) . '<td class="minn-num">' . Html::esc(self::mb($t['bytes'])) . '</td></tr>' . "\n";
        return $out . '</tbody></table></div>' . "\n";
    }

    private function method(): string
    {
        $m = $this->report->method;
        $json = Html::attr($this->permalinks->url('/code-size.json'));
        return '<h2 id="method">How it was counted</h2>' . "\n<ul>\n"
            . '<li>' . Html::esc((string) ($m['languages'] ?? '')) . '.</li>' . "\n"
            . '<li>Lines are ' . Html::esc((string) ($m['lines'] ?? '')) . '.</li>' . "\n"
            . '<li>' . Html::esc(ucfirst((string) ($m['minified'] ?? ''))) . '. A minified-only library counts few lines and honest bytes, which is why both appear.</li>' . "\n"
            . '<li>' . Html::esc(ucfirst((string) ($m['thirdParty'] ?? ''))) . '.</li>' . "\n"
            . '<li>Left out of WordPress: ' . Html::esc((string) ($m['wordpressExcludes'] ?? '')) . '.</li>' . "\n"
            . '<li>Left out of Minn: ' . Html::esc((string) ($m['minnExcludes'] ?? '')) . '.</li>' . "\n"
            . '<li>Refreshed with <code>' . Html::esc((string) ($m['refresh'] ?? '')) . '</code>, which downloads the current WordPress release from wordpress.org and measures the Minn trees in place. The numbers on this page are the <a href="' . $json . '">report as JSON</a>.</li>' . "\n"
            . '</ul>' . "\n";
    }

    private static function num(int|float $value): string
    {
        return '<td class="minn-num">' . self::n((int) $value) . '</td>';
    }

    private static function n(int $value): string
    {
        return number_format($value);
    }

    private static function mb(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024) . ' KB';
    }
}
