<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Errors;

/**
 * The checks of an ability's run, as the reference makes them (probes
 * abilities-registry, abilities-classes); WP_Ability::execute strings them:
 * input left out takes the input schema's default, input given to an ability
 * without an input schema is refused, input that its schema refuses is
 * ability_invalid_input; a permission callback that does not answer true is
 * ability_invalid_permissions (an error it returns is reported as a notice);
 * then wp_before_execute_ability, the callback
 * (an error it returns is the answer), the output checked against the
 * output schema (ability_invalid_output), and wp_after_execute_ability.
 */
final class AbilityRun
{
    /** True, or why the input does not fit. */
    public static function input(string $name, array $schema, mixed $input): true|Errors
    {
        if ($schema === []) {
            return $input === null ? true : Handoff::newError('ability_missing_input_schema', sprintf('Ability "%s" does not define an input schema required to validate the provided input.', $name));
        }
        $valid = \rest_validate_value_from_schema($input, $schema, 'input');
        return \is_wp_error($valid) ? Handoff::newError('ability_invalid_input', sprintf('Ability "%1$s" has invalid input. Reason: %2$s', $name, $valid->get_error_message())) : true;
    }

    /** True, or the error a permission answer other than true becomes (an error it returns is reported as a notice). */
    public static function allowed(string $name, mixed $answer): true|Errors
    {
        if ($answer instanceof Errors) {
            Notices::doingItWrong('WP_Ability::execute', $answer->get_error_message(), '6.9.0');
        }
        return $answer === true ? true : Handoff::newError('ability_invalid_permissions', sprintf('Ability "%s" does not have necessary permission.', $name));
    }

    /** True, or why the output does not fit the output schema. */
    public static function output(string $name, array $schema, mixed $output): true|Errors
    {
        $fits = $schema === [] ? true : \rest_validate_value_from_schema($output, $schema, 'output');
        return \is_wp_error($fits) ? Handoff::newError('ability_invalid_output', sprintf('Ability "%1$s" has invalid output. Reason: %2$s', $name, $fits->get_error_message())) : true;
    }
}
