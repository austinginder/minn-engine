<?php
// Interactive blocks have their directives processed once, when the outermost
// interactive block on the page finishes rendering; nested ones ride along.

function _minn_block_is_interactive(array $parsed_block): bool
{
    $name = $parsed_block['blockName'] ?? null;
    if (!is_string($name)) {
        return false;
    }
    $type = WP_Block_Type_Registry::get_instance()->get_registered($name);
    $supports = $type->supports['interactivity'] ?? false;
    return $supports === true || (is_array($supports) && ($supports['interactive'] ?? false) === true);
}

add_filter('render_block_data', static function ($parsed_block) {
    if (is_array($parsed_block) && _minn_block_is_interactive($parsed_block)) {
        $stack = Minn\Runtime\Runtime::current()->get('interactive_stack', 0);
        Minn\Runtime\Runtime::current()->set('interactive_stack', $stack + 1);
        $parsed_block['_minnInteractiveRoot'] = $stack === 0;
    }
    return $parsed_block;
}, 10, 1);

add_filter('render_block', static function ($block_content, $parsed_block) {
    if (!is_array($parsed_block) || !array_key_exists('_minnInteractiveRoot', $parsed_block)) {
        return $block_content;
    }
    $stack = Minn\Runtime\Runtime::current()->get('interactive_stack', 0);
    Minn\Runtime\Runtime::current()->set('interactive_stack', max(0, $stack - 1));
    return $parsed_block['_minnInteractiveRoot'] ? wp_interactivity_process_directives((string) $block_content) : $block_content;
}, 100, 2);
