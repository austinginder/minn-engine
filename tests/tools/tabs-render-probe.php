<?php
/**
 * The tabs block rendered whole through do_blocks (probe tabs-render): one
 * tabs id from the wp_unique_id counter per block, panels numbered in order
 * and keeping an id their markup carries (a comment's anchor alone is not
 * one), the tab list's buttons wired to the panels, the panel ids in the
 * core/tabs state, and the view module a page then loads.
 * Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$panel = static fn (array $attrs = [], string $text = 'x') => '<!-- wp:tab-panel ' . wp_json_encode($attrs ?: new stdClass()) . ' --><div class="wp-block-tab-panel"><!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph --></div><!-- /wp:tab-panel -->';
$tabs = static fn (array $attrs, string $inner) => '<!-- wp:tabs ' . wp_json_encode($attrs ?: new stdClass()) . ' --><div class="wp-block-tabs">' . $inner . '</div><!-- /wp:tabs -->';
$panels = static fn (string $inner) => '<!-- wp:tab-panels --><div class="wp-block-tab-panels">' . $inner . '</div><!-- /wp:tab-panels -->';
$list = '<!-- wp:tab-list /-->';
$cases = [
    'labels, and an anchor in the comment alone' => $tabs([], $list . $panels($panel(['label' => 'One'], 'first') . $panel(['label' => 'Two <b>&', 'anchor' => 'zz-two'], 'second') . $panel([], 'third'))),
    'the tabs anchored' => $tabs(['anchor' => 'zz-tabs'], $list . $panels($panel(['label' => 'A']))),
    'no panels' => $tabs([], $list),
    'no list' => $tabs([], $panels($panel(['label' => 'A']) . $panel(['label' => 'B']))),
    'a panel anchored in its markup' => $tabs([], $list . $panels($panel(['label' => 'A']) . '<!-- wp:tab-panel {"label":"B","anchor":"zz-html"} --><div id="zz-html" class="wp-block-tab-panel"><!-- wp:paragraph --><p>b</p><!-- /wp:paragraph --></div><!-- /wp:tab-panel -->')),
    'a list with buttons' => $tabs([], '<!-- wp:tab-list --><div class="wp-block-tab-list"><button>A</button><button>B</button></div><!-- /wp:tab-list -->' . $panels($panel(['label' => 'A']) . $panel(['label' => 'B']))),
    'two tabs blocks' => $tabs([], $list . $panels($panel(['label' => 'A']))) . $tabs([], $list . $panels($panel(['label' => 'B']))),
];
$tidy = static fn (string $html) => trim((string) preg_replace(['/\s+/', '/wp-container-[a-z0-9-]+/'], [' ', 'wp-container-{x}'], $html));
foreach ($cases as $label => $markup) {
    $say($label, $tidy(do_blocks($markup)));
}
$say('the core/tabs state', wp_interactivity_state('core/tabs'));
$say('the view module queued', array_values(array_filter(wp_script_modules()->get_queue(), static fn ($id) => str_contains($id, 'tabs'))));
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
