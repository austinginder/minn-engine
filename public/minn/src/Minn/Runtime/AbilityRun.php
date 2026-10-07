<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Running an ability as the reference runs one (probe abilities-registry):
 * input given to an ability without an input schema is refused, input that
 * its schema refuses is ability_invalid_input; a permission callback that
 * does not answer true is ability_invalid_permissions (an error it returns
 * is reported as a notice); then wp_before_execute_ability, the callback
 * (an error it returns is the answer), the output checked against the
 * output schema (ability_invalid_output), and wp_after_execute_ability.
 */
final class AbilityRun
{
    /** True, or why the input does not fit. */
    public static function input(string $name, array $schema, mixed $input): true|\WP_Error
    {
        if ($schema === []) {
            return $input === null ? true : new \WP_Error('ability_missing_input_schema', sprintf('Ability "%s" does not define an input schema required to validate the provided input.', $name));
        }
        $valid = \rest_validate_value_from_schema($input, $schema, 'input');
        return \is_wp_error($valid) ? new \WP_Error('ability_invalid_input', sprintf('Ability "%1$s" has invalid input. Reason: %2$s', $name, $valid->get_error_message())) : true;
    }

    /** The ability's answer, or the error that stopped it. */
    public static function execute(\WP_Ability $ability, mixed $callback, mixed $input): mixed
    {
        $name = $ability->get_name();
        $valid = self::input($name, $ability->get_input_schema(), $input);
        if ($valid instanceof \WP_Error) {
            return $valid;
        }
        $allowed = $ability->check_permissions($input);
        if ($allowed instanceof \WP_Error) {
            \_doing_it_wrong('WP_Ability::execute', $allowed->get_error_message(), '6.9.0');
        }
        if ($allowed !== true) {
            return new \WP_Error('ability_invalid_permissions', sprintf('Ability "%s" does not have necessary permission.', $name));
        }
        \do_action('wp_before_execute_ability', $name, $input);
        $result = !is_callable($callback) ? null : ($input === null ? call_user_func($callback) : call_user_func($callback, $input));
        if ($result instanceof \WP_Error) {
            return $result;
        }
        $schema = $ability->get_output_schema();
        $fits = $schema === [] ? true : \rest_validate_value_from_schema($result, $schema, 'output');
        if (\is_wp_error($fits)) {
            return new \WP_Error('ability_invalid_output', sprintf('Ability "%1$s" has invalid output. Reason: %2$s', $name, $fits->get_error_message()));
        }
        \do_action('wp_after_execute_ability', $name, $input, $result);
        return $result;
    }
}
