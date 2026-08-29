<?php
/**
 * Behaviour probe for the Interactivity API's server-side directive
 * processing: wp_interactivity_process_directives over a matrix of
 * directives, plus the state/config/context helpers. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

wp_interactivity_state('minn-probe', ['on' => true, 'off' => false, 'nil' => null, 'word' => 'hello', 'num' => 0, 'list' => ['a', 'b'], 'nested' => ['deep' => 'value'], 'cls' => 'from-state']);
wp_interactivity_state('minn-other', ['word' => 'other']);
$say('state returns', wp_interactivity_state('minn-probe'));
$say('state merge', wp_interactivity_state('minn-probe', ['word' => 'hello', 'extra' => 1])['extra']);
$say('state unknown', wp_interactivity_state('minn-unknown'));
$say('state null ns', wp_interactivity_state());
$say('config', wp_interactivity_config('minn-probe', ['a' => 1]));
$say('config again', wp_interactivity_config('minn-probe', ['b' => 2]));
$say('config read', wp_interactivity_config('minn-probe'));
$say('data_wp_context', [wp_interactivity_data_wp_context(['a' => 1, 'q' => 'x"y', 'l' => "it's"]), wp_interactivity_data_wp_context([]), wp_interactivity_data_wp_context(['a' => 1], 'ns')]);

$p = static fn (string $html) => wp_interactivity_process_directives($html);

$open = '<div data-wp-interactive="minn-probe" data-wp-context=\'{"mode":"auto","flag":false,"empty":"","label":"OS auto","n":0,"cls":"from-context"}\'>';
$say('bind true', $p($open . '<button data-wp-bind--disabled="state.on" type="button">x</button></div>'));
$say('bind false', $p($open . '<button data-wp-bind--disabled="state.off" type="button">x</button></div>'));
$say('bind null', $p($open . '<button data-wp-bind--disabled="state.nil" type="button">x</button></div>'));
$say('bind string', $p($open . '<button data-wp-bind--title="state.word" type="button">x</button></div>'));
$say('bind zero', $p($open . '<button data-wp-bind--title="state.num" type="button">x</button></div>'));
$say('bind empty string', $p($open . '<button data-wp-bind--title="context.empty" type="button">x</button></div>'));
$say('bind aria true', $p($open . '<button data-wp-bind--aria-expanded="state.on" type="button">x</button></div>'));
$say('bind aria false', $p($open . '<button data-wp-bind--aria-expanded="context.flag" type="button">x</button></div>'));
$say('bind aria null', $p($open . '<button data-wp-bind--aria-expanded="state.nil" type="button">x</button></div>'));
$say('bind data false', $p($open . '<button data-wp-bind--data-open="state.off" type="button">x</button></div>'));
$say('bind data true', $p($open . '<button data-wp-bind--data-open="state.on" type="button">x</button></div>'));
$say('bind context string', $p($open . '<span data-wp-bind--aria-label="context.label">x</span></div>'));
$say('bind replaces existing', $p($open . '<button type="button" data-wp-bind--class="context.cls" class="static-class">x</button></div>'));
$say('bind existing false removes', $p($open . '<button type="button" data-wp-bind--disabled="state.off" disabled="disabled">x</button></div>'));
$say('bind negation', $p($open . '<button data-wp-bind--hidden="!state.on" type="button">x</button><i data-wp-bind--hidden="!state.off"></i></div>'));
$say('bind other ns', $p($open . '<span data-wp-bind--title="minn-other::state.word">x</span></div>'));
$say('bind unknown path', $p($open . '<span data-wp-bind--title="state.nope.deeper">x</span></div>'));
$say('bind nested', $p($open . '<span data-wp-bind--title="state.nested.deep">x</span></div>'));
$say('bind array', $p($open . '<span data-wp-bind--title="state.list">x</span></div>'));
$say('bind unknown ns', $p($open . '<span data-wp-bind--title="minn-nope::state.word">x</span></div>'));
$say('class add', $p($open . '<span data-wp-class--is-on="state.on" class="a">x</span></div>'));
$say('class add no class attr', $p($open . '<span data-wp-class--is-on="state.on">x</span></div>'));
$say('class remove', $p($open . '<span data-wp-class--gone="state.off" class="a gone b">x</span></div>'));
$say('class remove only', $p($open . '<span data-wp-class--gone="state.off" class="gone">x</span></div>'));
$say('class existing', $p($open . '<span data-wp-class--a="state.on" class="a b">x</span></div>'));
$say('style add', $p($open . '<span data-wp-style--color="state.word" style="margin:0">x</span></div>'));
$say('style add no attr', $p($open . '<span data-wp-style--color="state.word">x</span></div>'));
$say('style replace', $p($open . '<span data-wp-style--color="state.word" style="color: red; margin: 0;">x</span></div>'));
$say('style remove', $p($open . '<span data-wp-style--color="state.off" style="color: red; margin: 0">x</span></div>'));
$say('style remove last', $p($open . '<span data-wp-style--color="state.nil" style="color:red">x</span></div>'));
$say('text', $p($open . '<span data-wp-text="state.word">old <b>text</b></span></div>'));
$say('text escaped', $p($open . '<span data-wp-text="context.label">old</span><i data-wp-text="state.num"></i></div>'));
$say('text html', $p('<div data-wp-interactive="minn-probe" data-wp-context=\'{"h":"<b>&amp;</b>"}\'><span data-wp-text="context.h">old</span></div>'));
$say('text bool', $p($open . '<span data-wp-text="state.on">old</span><i data-wp-text="state.off">old</i><u data-wp-text="state.nil">old</u></div>'));
$say('text array', $p($open . '<span data-wp-text="state.list">old</span></div>'));
$say('context nested', $p($open . '<div data-wp-context=\'{"mode":"dark"}\'><span data-wp-bind--title="context.mode">x</span><i data-wp-bind--title="context.label"></i></div><span data-wp-bind--title="context.mode">y</span></div>'));
$say('context inherits ns', $p($open . '<div data-wp-context=\'{"x":1}\'><span data-wp-bind--title="context.x">x</span></div></div>'));
$say('context explicit ns', $p($open . '<div data-wp-context=\'minn-other::{"x":2}\'><span data-wp-bind--title="context.x">x</span><i data-wp-bind--title="minn-other::context.x"></i></div></div>'));
$say('context invalid json', $p($open . '<div data-wp-context=\'{oops\'><span data-wp-bind--title="context.mode">x</span></div></div>'));
$say('interactive nested ns', $p($open . '<div data-wp-interactive="minn-other"><span data-wp-bind--title="state.word">x</span></div><span data-wp-bind--title="state.word">y</span></div>'));
$say('interactive json form', $p('<div data-wp-interactive=\'{"namespace":"minn-probe"}\'><span data-wp-bind--title="state.word">x</span></div>'));
$say('interactive empty', $p('<div data-wp-interactive=""><span data-wp-bind--title="state.word">x</span></div>'));
$say('no interactive root', $p('<div><span data-wp-bind--title="minn-probe::state.word">x</span><i data-wp-bind--title="state.word"></i></div>'));
$say('directive outside interactive', $p('<span data-wp-bind--title="state.word">x</span>'));
$say('bind self closing', $p($open . '<input data-wp-bind--value="state.word" type="text"><img data-wp-bind--alt="context.label" src="x.png" /></div>'));
$say('bind void nested', $p($open . '<p>t<br><span data-wp-bind--title="state.word">x</span></p></div>'));
$say('directive on root', $p('<div data-wp-interactive="minn-probe" data-wp-bind--title="state.word" data-wp-class--is-on="state.on" class="root">x</div>'));
$say('directives kept', $p($open . '<span data-wp-on--click="actions.go" data-wp-init="callbacks.init" data-wp-watch="callbacks.w" data-wp-bind--title="state.word">x</span></div>'));
$say('each', $p($open . '<ul><template data-wp-each="state.list"><li data-wp-text="context.item"></li></template></ul></div>'));
$say('each key', $p($open . '<ul><template data-wp-each--thing="state.list"><li data-wp-bind--title="context.thing" data-wp-text="context.thing"></li></template><li data-wp-each-child>server</li></ul></div>'));
$say('each with existing children', $p($open . '<ul><template data-wp-each="state.list"><li data-wp-text="context.item"></li></template><li data-wp-each-child>a</li><li data-wp-each-child>b</li></ul></div>'));
$say('router region', $p($open . '<div data-wp-router-region="main">x</div></div>'));
$say('unbalanced', $p($open . '<span data-wp-bind--title="state.word">x</span>'));
$say('malformed', $p('<div data-wp-interactive="minn-probe"><span data-wp-bind--title="state.word">x</div>'));
$say('case', $p($open . '<SPAN DATA-WP-BIND--TITLE="state.word">x</SPAN></div>'));
$say('quote styles', $p($open . '<span data-wp-bind--title=state.word>x</span><i data-wp-bind--title=\'state.word\'></i></div>'));
$say('attr order insert', $p($open . '<button type="button" class="c" data-wp-bind--aria-expanded="state.off" id="i">x</button></div>'));
$say('multiple binds', $p($open . '<button data-wp-bind--aria-expanded="state.off" data-wp-bind--aria-label="state.word" data-wp-bind--disabled="state.on" type="button">x</button></div>'));
$say('get_context outside', wp_interactivity_get_context('minn-probe'));
$say('get_element outside', wp_interactivity_get_element());
$say('wp_interactivity class', get_class(wp_interactivity()));
$say('state derived callable', (static function () use ($p, $open) {
    wp_interactivity_state('minn-probe', ['derived' => static fn () => 'computed:' . wp_interactivity_get_context()['mode']]);
    return $p($open . '<span data-wp-bind--title="state.derived" data-wp-text="state.derived">x</span></div>');
})());
$say('state derived element', (static function () use ($p, $open) {
    wp_interactivity_state('minn-probe', ['fromEl' => static fn () => 'el:' . wp_interactivity_get_element()['attributes']['id']]);
    return $p($open . '<span id="me" data-wp-bind--title="state.fromEl">x</span></div>');
})());
$say('unicode', $p('<div data-wp-interactive="minn-probe" data-wp-context=\'{"w":"héllo <ç> & \\"q\\""}\'><span data-wp-bind--title="context.w" data-wp-text="context.w">x</span></div>'));

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
