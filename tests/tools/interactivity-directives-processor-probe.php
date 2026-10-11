<?php
/**
 * The Interactivity API's tag processor (probe
 * interactivity-directives-processor): template content read and written
 * between balanced tags, content appended after a template's closer, a
 * walk to a tag's closer or the next balanced closer, and whether a tag
 * has a closer it visits. Each case runs steps on a fresh processor and
 * shows what each step answered, where the processor stood after it, and
 * the document at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// Steps are [method, ...args]; "next_tag" with a query moves the processor.
$run = static function (string $html, array $steps) use ($try) {
    $p = new WP_Interactivity_API_Directives_Processor($html);
    $out = [];
    foreach ($steps as $step) {
        $method = array_shift($step);
        $result = $try(static fn () => $p->{$method}(...$step));
        $out[] = [$method, $result, $p->get_tag(), $p->is_tag_closer()];
    }
    return ['steps' => $out, 'html' => $p->get_updated_html()];
};

$template = '<div><template data-wp-each="x"><p>a</p><span>b</span></template><i>x</i></div>';
$say('template content', [
    'a template' => $run($template, [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['next_tag']]),
    'not a template' => $run($template, [['next_tag', 'div'], ['get_content_between_balanced_template_tags']]),
    'nested templates' => $run('<template><template><b>i</b></template>o</template><p>z</p>', [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['next_tag']]),
    'the inner one' => $run('<template><template><b>i</b></template>o</template>', [['next_tag', 'template'], ['next_tag', 'template'], ['get_content_between_balanced_template_tags']]),
    'unclosed' => $run('<template><p>a</p>', [['next_tag', 'template'], ['get_content_between_balanced_template_tags']]),
    'empty' => $run('<template></template><p>z</p>', [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['next_tag']]),
    'at the closer' => $run($template, [['next_tag', ['tag_name' => 'template', 'tag_closers' => 'visit']], ['next_tag', ['tag_name' => 'template', 'tag_closers' => 'visit']], ['get_content_between_balanced_template_tags']]),
    'nothing yet' => $run($template, [['get_content_between_balanced_template_tags']]),
]);

$say('content set between balanced tags', [
    'a div' => $run('<div class="a"><p>old</p><span>x</span></div><b>after</b>', [['next_tag', 'div'], ['set_content_between_balanced_tags', '<em>new</em>'], ['next_tag']]),
    'nested divs' => $run('<div id="o"><div id="i">in</div>tail</div><b>after</b>', [['next_tag', 'div'], ['set_content_between_balanced_tags', 'N'], ['next_tag']]),
    'the inner div' => $run('<div id="o"><div id="i">in</div>tail</div>', [['next_tag', 'div'], ['next_tag', 'div'], ['set_content_between_balanced_tags', 'N'], ['next_tag']]),
    'a template' => $run($template, [['next_tag', 'template'], ['set_content_between_balanced_tags', '<u>T</u>']]),
    'an empty one' => $run('<p></p><b></b>', [['next_tag', 'p'], ['set_content_between_balanced_tags', 'filled'], ['next_tag']]),
    'to nothing' => $run('<p>gone</p><b></b>', [['next_tag', 'p'], ['set_content_between_balanced_tags', '']]),
    'a void element' => $run('<img src="a"><p>x</p>', [['next_tag', 'img'], ['set_content_between_balanced_tags', 'x']]),
    'a closer' => $run('<p>x</p>', [['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['set_content_between_balanced_tags', 'y']]),
    'unclosed' => $run('<div><p>x</p>', [['next_tag', 'div'], ['set_content_between_balanced_tags', 'y']]),
    'twice' => $run('<div>a</div><div>b</div>', [['next_tag', 'div'], ['set_content_between_balanced_tags', '1'], ['next_tag', 'div'], ['set_content_between_balanced_tags', '2']]),
    'after an attribute change' => (static function () use ($try) {
        $p = new WP_Interactivity_API_Directives_Processor('<div><p>old</p></div>');
        $p->next_tag('div');
        $p->set_attribute('data-x', 'y');
        $result = $try(static fn () => $p->set_content_between_balanced_tags('new'));
        return [$result, $p->get_updated_html()];
    })(),
]);

$say('content appended after a template', [
    'at its closer' => $run($template, [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['append_content_after_template_tag_closer', '<b>added</b>'], ['next_tag']]),
    'at its opener' => $run($template, [['next_tag', 'template'], ['append_content_after_template_tag_closer', '<b>added</b>']]),
    'after another closer' => $run('<p>x</p><i></i>', [['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['append_content_after_template_tag_closer', 'z']]),
    'twice' => $run('<template>t</template><i></i>', [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['append_content_after_template_tag_closer', '1'], ['append_content_after_template_tag_closer', '2']]),
    'empty' => $run('<template>t</template>', [['next_tag', 'template'], ['get_content_between_balanced_template_tags'], ['append_content_after_template_tag_closer', '']]),
]);

$say('to the closer', [
    'a div' => $run('<div><div>i</div><p>x</p></div><b>z</b>', [['next_tag', 'div'], ['skip_to_tag_closer'], ['next_tag']]),
    'a paragraph' => $run('<p>a<span>b</span></p>', [['next_tag', 'p'], ['skip_to_tag_closer']]),
    'a void element' => $run('<img><p>x</p>', [['next_tag', 'img'], ['skip_to_tag_closer']]),
    'unclosed' => $run('<div><p>x</p>', [['next_tag', 'div'], ['skip_to_tag_closer']]),
    'a closer' => $run('<p>x</p><p>y</p>', [['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['skip_to_tag_closer']]),
    'nothing yet' => $run('<p>x</p>', [['skip_to_tag_closer']]),
    'svg' => $run('<svg><path d="M0"/><g></g></svg><b></b>', [['next_tag', 'svg'], ['skip_to_tag_closer'], ['next_tag']]),
]);

$say('the next balanced closer', [
    'from an opener' => $run('<div><p>a</p><span>b</span></div><b>z</b>', [['next_tag', 'div'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag']]),
    'from inside' => $run('<div><p>a</p><span>b</span></div><b>z</b>', [['next_tag', 'p'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag']]),
    'with nested tags of the same name' => $run('<div><div><div>x</div></div></div>', [['next_tag', 'div'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag'], ['next_balanced_tag_closer_tag']]),
    'with voids' => $run('<div><br><img><hr></div>', [['next_tag', 'div'], ['next_balanced_tag_closer_tag']]),
    'unbalanced' => $run('<div><p>x</div>', [['next_tag', 'div'], ['next_balanced_tag_closer_tag']]),
    'nothing yet' => $run('<p>x</p>', [['next_balanced_tag_closer_tag']]),
]);

$visits = static fn (string $html, $query) => $run($html, [['next_tag', $query], ['has_and_visits_its_closer_tag']]);
$say('has and visits its closer', [
    'a div' => $visits('<div></div>', 'div'),
    'a void element' => $visits('<img>', 'img'),
    'a template' => $visits('<template></template>', 'template'),
    'a script' => $visits('<script>x</script>', 'script'),
    'a style' => $visits('<style>x</style>', 'style'),
    'a textarea' => $visits('<textarea>x</textarea>', 'textarea'),
    'a title' => $visits('<title>x</title>', 'title'),
    'an iframe' => $visits('<iframe></iframe>', 'iframe'),
    'noscript' => $visits('<noscript>x</noscript>', 'noscript'),
    'xmp' => $visits('<xmp>x</xmp>', 'xmp'),
    'noembed' => $visits('<noembed>x</noembed>', 'noembed'),
    'noframes' => $visits('<noframes>x</noframes>', 'noframes'),
    'a void inside svg' => $visits('<svg><br/></svg>', 'br'),
    'a self-closing div' => $visits('<div/>', 'div'),
    'svg' => $visits('<svg></svg>', 'svg'),
    'a self-closing path in svg' => $visits('<svg><path/></svg>', 'path'),
    'a closer' => $run('<p></p>', [['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['next_tag', ['tag_name' => 'p', 'tag_closers' => 'visit']], ['has_and_visits_its_closer_tag']]),
    'nothing yet' => $run('<p></p>', [['has_and_visits_its_closer_tag']]),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
