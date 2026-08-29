<?php
/**
 * The plugin API: the functions plugin code registers with. Signatures
 * follow contracts/api/functions.json; behaviour follows
 * contracts/fixtures/api/hooks.json. Every function delegates to the
 * request's Minn\Runtime\Hooks.
 */

use Minn\Runtime\Runtime;

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    // A null priority (plugins pass one through) reads as the default.
    return Runtime::hooks()->add((string) $hook_name, $callback, $priority === null || $priority === '' ? 10 : $priority, (int) $accepted_args);
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1)
{
    return add_filter($hook_name, $callback, $priority, $accepted_args);
}

function remove_filter($hook_name, $callback, $priority = 10)
{
    return Runtime::hooks()->remove((string) $hook_name, $callback, $priority === null || $priority === '' ? 10 : $priority);
}

function remove_action($hook_name, $callback, $priority = 10)
{
    return remove_filter($hook_name, $callback, $priority);
}

function has_filter($hook_name, $callback = false, $priority = false)
{
    return Runtime::hooks()->has((string) $hook_name, $callback);
}

function has_action($hook_name, $callback = false, $priority = false)
{
    return has_filter($hook_name, $callback, $priority);
}

function apply_filters($hook_name, $value, ...$args)
{
    return Runtime::hooks()->filter((string) $hook_name, [$value, ...$args]);
}

function do_action($hook_name, ...$arg)
{
    Runtime::hooks()->action((string) $hook_name, $arg);
}

function apply_filters_ref_array($hook_name, $args)
{
    return Runtime::hooks()->filter((string) $hook_name, array_values((array) $args));
}

function do_action_ref_array($hook_name, $args)
{
    Runtime::hooks()->action((string) $hook_name, array_values((array) $args));
}

function current_filter()
{
    return Runtime::hooks()->current();
}

function current_action()
{
    return current_filter();
}

function doing_filter($hook_name = null)
{
    return Runtime::hooks()->doing($hook_name === null ? null : (string) $hook_name);
}

function doing_action($hook_name = null)
{
    return doing_filter($hook_name);
}

function did_action($hook_name)
{
    return Runtime::hooks()->actionsDone((string) $hook_name);
}

function did_filter($hook_name)
{
    return Runtime::hooks()->filtersDone((string) $hook_name);
}

function remove_all_filters($hook_name, $priority = false)
{
    return Runtime::hooks()->removeAll((string) $hook_name, $priority);
}

function remove_all_actions($hook_name, $priority = false)
{
    return remove_all_filters($hook_name, $priority);
}

function apply_filters_deprecated($hook_name, $args, $version, $replacement = '', $message = '')
{
    if (!has_filter($hook_name)) {
        return $args[0];
    }
    _deprecated_hook($hook_name, $version, $replacement, $message);
    return apply_filters_ref_array($hook_name, $args);
}

function do_action_deprecated($hook_name, $args, $version, $replacement = '', $message = '')
{
    if (!has_action($hook_name)) {
        return;
    }
    _deprecated_hook($hook_name, $version, $replacement, $message);
    do_action_ref_array($hook_name, $args);
}
