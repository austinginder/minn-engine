<?php

declare(strict_types=1);

use Minn\Runtime\Hooks;
use Minn\Runtime\PluginCall;

/**
 * Extension code is called as WordPress calls it: from a file without strict
 * types, so PHP converts a numeric string handed to a callback typed int (and
 * the like) where a strict call would throw a TypeError. Elementor's heartbeat
 * hands the post ID it was posted, a string, to a callback typed int.
 */
return [
    'a hook callback typed int is handed a numeric string, converted as WordPress converts it' => static function (): bool|string {
        $hooks = new Hooks();
        $seen = null;
        $hooks->add('minn_unit_typed', static function (int $postId, $signal) use (&$seen): void {
            $seen = [$postId, $signal];
        }, 10, 2);
        $hooks->action('minn_unit_typed', ['3494', '1']);
        return $seen === [3494, '1'] ? true : json_encode($seen);
    },
    'a filter typed string is handed an int and its answer is kept' => static function (): bool|string {
        $hooks = new Hooks();
        $hooks->add('minn_unit_typed_filter', static fn (string $value): string => $value . '!', 10, 1);
        $out = $hooks->filter('minn_unit_typed_filter', [42]);
        return $out === '42!' ? true : var_export($out, true);
    },
    'the hooks with callbacks under a prefix are named without it' => static function (): bool|string {
        $hooks = new Hooks();
        $hooks->add('admin_action_elementor', static fn () => null);
        $hooks->add('admin_action_duplicate', static fn () => null);
        $hooks->add('admin_init', static fn () => null);
        $hooks->add('admin_action_gone', $gone = static fn () => null);
        $hooks->remove('admin_action_gone', $gone);
        $names = $hooks->names('admin_action_');
        return $names === ['duplicate', 'elementor'] ? true : json_encode($names);
    },
    'a call that cannot be converted still fails, as it does on WordPress' => static function (): bool|string {
        try {
            PluginCall::run(static fn (int $id): int => $id, ['not a number']);
        } catch (TypeError) {
            return true;
        }
        return 'no TypeError';
    },
];
