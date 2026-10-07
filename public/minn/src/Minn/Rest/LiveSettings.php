<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * wp/v2/settings with plugins loaded, served from the registered settings
 * as the reference serves it (probe rest-settings): every setting shown in
 * REST, core's and a plugin's alike, under its REST name. A read asks
 * rest_pre_get_setting first, then get_option with the schema's default,
 * and a value its schema refuses reads as null. A write takes the body's
 * values its schema accepts (null always), offers each to
 * rest_pre_update_setting, deletes the option for null (refused when what
 * is stored does not fit the schema, since null could not restore it) and
 * otherwise hands it to update_option.
 */
final readonly class LiveSettings
{
    public function __construct(private Schema $schema)
    {
    }

    /**
     * The settings shown in REST, by REST name, with the arguments the filters are handed.
     *
     * @return array<string, array{name: string, schema: array<string, mixed>, option_name: string}>
     */
    public function shown(): array
    {
        $out = [];
        foreach (\get_registered_settings() as $option => $args) {
            $rest = $args['show_in_rest'] ?? false;
            if (empty($rest)) {
                continue;
            }
            $rest = is_array($rest) ? $rest : [];
            $name = (string) ($rest['name'] ?? $option);
            $schema = ['type' => $args['type'] ?? 'string', 'title' => $args['label'] ?? '', 'description' => $args['description'] ?? '', 'default' => $args['default'] ?? null];
            $out[$name] = ['name' => $name, 'schema' => array_merge($schema, (array) ($rest['schema'] ?? [])), 'option_name' => (string) $option];
        }
        return $out;
    }

    /** Every shown setting's value. @return array<string, mixed> */
    public function payload(): array
    {
        $out = [];
        foreach ($this->shown() as $name => $args) {
            $value = \apply_filters('rest_pre_get_setting', null, $name, $args);
            $value ??= Runtime::options()->filtered($args['option_name'], $args['schema']['default']);
            $out[$name] = $this->schema->validate($value, $args['schema'], $name) === true ? $this->schema->sanitize($value, $args['schema'], $name) : null;
        }
        return $out;
    }

    /** Writes the shown settings a body names, after refusing the values their schemas refuse. */
    public function store(array $body): void
    {
        $shown = array_intersect_key($this->shown(), $body);
        $this->refuseInvalid($shown, $body);
        foreach ($shown as $name => $args) {
            $value = $body[$name] === null ? null : $this->schema->sanitize($body[$name], $args['schema'], $name);
            if (\apply_filters('rest_pre_update_setting', false, $name, $value, $args)) {
                continue;
            }
            if ($value !== null) {
                \update_option($args['option_name'], $value);
                continue;
            }
            if ($this->schema->validate(Runtime::options()->filtered($args['option_name'], false), $args['schema'], $name) !== true) {
                throw new RestError('rest_invalid_stored_value', sprintf('The %s property has an invalid stored value, and cannot be updated to null.', $name), 500);
            }
            \delete_option($args['option_name']);
        }
    }

    /** @param array<string, array{schema: array<string, mixed>}> $shown */
    private function refuseInvalid(array $shown, array $body): void
    {
        $invalid = [];
        $details = [];
        foreach ($shown as $name => $args) {
            $verdict = $body[$name] === null ? true : $this->schema->validate($body[$name], $args['schema'], $name);
            if ($verdict !== true) {
                $invalid[$name] = $verdict->message;
                $details[$name] = ['code' => $verdict->code, 'message' => $verdict->message, 'data' => $verdict->data];
            }
        }
        if ($invalid !== []) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): ' . implode(', ', array_keys($invalid)), 400, ['params' => $invalid, 'details' => $details]);
        }
    }
}
