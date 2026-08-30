<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use JsonSerializable;
use Minn\Runtime\Refusal;
use stdClass;

/**
 * JSON-schema handling the way the REST API's argument validation does it:
 * validate a value against a schema (a Refusal names what failed), sanitise
 * it into the schema's type, filter a response by context, and the type
 * tests the schema vocabulary needs. Behaviour pinned by contracts/fixtures/api/rest.json.
 */
final readonly class Schema
{
    private const TYPES = ['array', 'object', 'string', 'number', 'integer', 'boolean', 'null'];

    /**
     * @param Closure(string): bool $email the email test, so the reference's is_email filter applies
     * @param Closure(int|float): string $number the localised number formatter for messages
     * @param Closure(string, mixed): mixed $format the sanitiser for a string format (hex-color, email, uri, ...)
     */
    public function __construct(private Closure $email, private Closure $number, private Closure $format)
    {
    }

    public function validate(mixed $value, array $args, string $param = ''): true|Refusal
    {
        if (isset($args['anyOf'])) {
            foreach ($args['anyOf'] as $schema) {
                if ($this->validate($value, $schema, $param) === true) {
                    return true;
                }
            }
            return new Refusal('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
        }
        if (isset($args['oneOf'])) {
            $matches = 0;
            foreach ($args['oneOf'] as $schema) {
                if ($this->validate($value, $schema, $param) === true) {
                    $matches++;
                }
            }
            if ($matches === 0) {
                return new Refusal('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
            }
            return $matches === 1 ? true : new Refusal('rest_one_of_multiple_matches', sprintf('%s matches more than one of the expected formats.', $param));
        }
        if (!isset($args['type'])) {
            // An enum alone is still checked; nothing else is without a type.
            return empty($args['enum']) ? true : $this->enum($value, $args['enum'], $param);
        }
        if (is_array($args['type'])) {
            $best = self::bestType($value, $args['type']);
            if ($best === '') {
                return $this->wrongType($param, implode(',', $args['type']));
            }
            $args['type'] = $best;
        }
        $type = (string) $args['type'];
        if ($type === 'array') {
            $refused = $this->validateArray($value, $args, $param);
            if ($refused !== null) {
                return $refused;
            }
        }
        if ($type === 'object') {
            $refused = $this->validateObject($value, $args, $param);
            if ($refused !== null) {
                return $refused;
            }
        }
        if ($type === 'null') {
            return $value === null ? true : $this->wrongType($param, 'null');
        }
        if (!empty($args['enum'])) {
            $refused = $this->enum($value, $args['enum'], $param);
            if ($refused !== true) {
                return $refused;
            }
        }
        if (in_array($type, ['integer', 'number'], true) && !is_numeric($value)) {
            return $this->wrongType($param, $type);
        }
        if ($type === 'integer' && !self::isInteger($value)) {
            return $this->wrongType($param, 'integer');
        }
        if ($type === 'boolean' && !self::isBoolean($value)) {
            return $this->wrongType($param, 'boolean');
        }
        if ($type === 'string') {
            if (!is_string($value)) {
                return $this->wrongType($param, 'string');
            }
            if (isset($args['minLength']) && mb_strlen($value) < $args['minLength']) {
                return new Refusal('rest_too_short', sprintf('%1$s must be at least %2$s %3$s long.', $param, ($this->number)($args['minLength']), (int) $args['minLength'] === 1 ? 'character' : 'characters'));
            }
            if (isset($args['maxLength']) && mb_strlen($value) > $args['maxLength']) {
                return new Refusal('rest_too_long', sprintf('%1$s must be at most %2$s %3$s long.', $param, ($this->number)($args['maxLength']), (int) $args['maxLength'] === 1 ? 'character' : 'characters'));
            }
            if (isset($args['pattern']) && !preg_match('#' . str_replace('#', '\\#', $args['pattern']) . '#u', $value)) {
                return new Refusal('rest_invalid_pattern', sprintf('%1$s does not match pattern %2$s.', $param, $args['pattern']));
            }
        }
        if (isset($args['format']) && ($type === 'string' || !in_array($type, self::TYPES, true))) {
            $refused = $this->validateFormat($value, (string) $args['format'], $param);
            if ($refused !== null) {
                return $refused;
            }
        }
        if (in_array($type, ['number', 'integer'], true)) {
            $refused = $this->validateBounds($value, $args, $param);
            if ($refused !== null) {
                return $refused;
            }
            if (isset($args['multipleOf']) && fmod((float) $value, (float) $args['multipleOf']) != 0) {
                return new Refusal('rest_invalid_multiple', sprintf('%1$s must be a multiple of %2$s.', $param, $args['multipleOf']));
            }
        }
        return true;
    }

    private function validateArray(mixed $value, array $args, string $param): ?Refusal
    {
        if (!self::isArray($value)) {
            return $this->wrongType($param, 'array');
        }
        $value = self::toArray($value);
        if (isset($args['items'])) {
            foreach ($value as $index => $v) {
                $valid = $this->validate($v, $args['items'], $param . '[' . $index . ']');
                if ($valid !== true) {
                    return $valid;
                }
            }
        }
        if (isset($args['minItems']) && count($value) < $args['minItems']) {
            return new Refusal('rest_too_few_items', sprintf('%1$s must contain at least %2$s %3$s.', $param, ($this->number)($args['minItems']), (int) $args['minItems'] === 1 ? 'item' : 'items'));
        }
        if (isset($args['maxItems']) && count($value) > $args['maxItems']) {
            return new Refusal('rest_too_many_items', sprintf('%1$s must contain at most %2$s %3$s.', $param, ($this->number)($args['maxItems']), (int) $args['maxItems'] === 1 ? 'item' : 'items'));
        }
        if (!empty($args['uniqueItems']) && count(array_unique(array_map('serialize', $value))) !== count($value)) {
            return new Refusal('rest_duplicate_items', sprintf('%s has duplicate items.', $param));
        }
        return null;
    }

    private function validateObject(mixed $value, array $args, string $param): ?Refusal
    {
        if (!self::isObject($value)) {
            return $this->wrongType($param, 'object');
        }
        $value = self::toObject($value);
        if (isset($args['required']) && is_array($args['required'])) {
            foreach ($args['required'] as $name) {
                if (!array_key_exists($name, $value)) {
                    return new Refusal('rest_property_required', sprintf('%1$s is a required property of %2$s.', $name, $param));
                }
            }
        } elseif (isset($args['properties'])) {
            foreach ($args['properties'] as $name => $property) {
                if (isset($property['required']) && $property['required'] === true && !array_key_exists($name, $value)) {
                    return new Refusal('rest_property_required', sprintf('%1$s is a required property of %2$s.', $name, $param));
                }
            }
        }
        foreach ($value as $property => $v) {
            $schema = $args['properties'][$property] ?? self::patternProperty((string) $property, $args);
            if ($schema === null && isset($args['additionalProperties'])) {
                if ($args['additionalProperties'] === false) {
                    return new Refusal('rest_additional_properties_forbidden', sprintf('%1$s is not a valid property of Object.', $property));
                }
                $schema = is_array($args['additionalProperties']) ? $args['additionalProperties'] : null;
            }
            if ($schema !== null) {
                $valid = $this->validate($v, $schema, $param . '[' . $property . ']');
                if ($valid !== true) {
                    return $valid;
                }
            }
        }
        return null;
    }

    private function validateFormat(mixed $value, string $format, string $param): ?Refusal
    {
        return match ($format) {
            'hex-color' => self::parseHexColor($value) === false ? new Refusal('rest_invalid_hex_color', 'Invalid hex color.') : null,
            'date-time' => self::parseDate($value) === false ? new Refusal('rest_invalid_date', 'Invalid date.') : null,
            'email' => ($this->email)((string) $value) ? null : new Refusal('rest_invalid_email', 'Invalid email address.'),
            'ip' => filter_var($value, FILTER_VALIDATE_IP) ? null : new Refusal('rest_invalid_ip', sprintf('%s is not a valid IP address.', $param)),
            'uuid' => self::isUuid($value) ? null : new Refusal('rest_invalid_uuid', sprintf('%s is not a valid UUID.', $param)),
            default => null,
        };
    }

    private function validateBounds(mixed $value, array $args, string $param): ?Refusal
    {
        $min = $args['minimum'] ?? null;
        $max = $args['maximum'] ?? null;
        if ($min === null && $max === null) {
            return null;
        }
        $exMin = !empty($args['exclusiveMinimum']);
        $exMax = !empty($args['exclusiveMaximum']);
        if ($min !== null && $max === null) {
            if ($exMin && $value <= $min) {
                return new Refusal('rest_out_of_bounds', sprintf('%1$s must be greater than %2$d', $param, $min));
            }
            if (!$exMin && $value < $min) {
                return new Refusal('rest_out_of_bounds', sprintf('%1$s must be greater than or equal to %2$d', $param, $min));
            }
            return null;
        }
        if ($max !== null && $min === null) {
            if ($exMax && $value >= $max) {
                return new Refusal('rest_out_of_bounds', sprintf('%1$s must be less than %2$d', $param, $max));
            }
            if (!$exMax && $value > $max) {
                return new Refusal('rest_out_of_bounds', sprintf('%1$s must be less than or equal to %2$d', $param, $max));
            }
            return null;
        }
        $belowMin = $exMin ? $value <= $min : $value < $min;
        $aboveMax = $exMax ? $value >= $max : $value > $max;
        if ($belowMin || $aboveMax) {
            $lower = $exMin ? 'exclusive' : 'inclusive';
            $upper = $exMax ? 'exclusive' : 'inclusive';
            return new Refusal('rest_out_of_bounds', sprintf('%1$s must be between %2$d (' . $lower . ') and %3$d (' . $upper . ')', $param, $min, $max));
        }
        return null;
    }

    private function enum(mixed $value, array $enum, string $param): true|Refusal
    {
        foreach ($enum as $option) {
            if (self::valuesEqual($value, $option)) {
                return true;
            }
        }
        $encoded = array_map(static fn ($v) => is_scalar($v) ? (string) $v : (string) json_encode($v), $enum);
        if (count($encoded) === 1) {
            return new Refusal('rest_not_in_enum', sprintf('%1$s is not %2$s.', $param, $encoded[0]));
        }
        $last = array_pop($encoded);
        return new Refusal('rest_not_in_enum', sprintf('%1$s is not one of %2$s and %3$s.', $param, implode(', ', $encoded), $last));
    }

    private function wrongType(string $param, string $type): Refusal
    {
        return new Refusal('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, $type), ['param' => $param]);
    }

    public function sanitize(mixed $value, array $args, string $param = ''): mixed
    {
        foreach (['anyOf', 'oneOf'] as $combinator) {
            if (isset($args[$combinator])) {
                foreach ($args[$combinator] as $schema) {
                    if ($this->validate($value, $schema, $param) === true) {
                        return $this->sanitize($value, $schema, $param);
                    }
                }
                return $value;
            }
        }
        if (!isset($args['type'])) {
            return $value;
        }
        if (is_array($args['type'])) {
            $best = self::bestType($value, $args['type']);
            if ($best === '') {
                return null;
            }
            $args['type'] = $best;
        }
        $type = (string) $args['type'];
        if ($type === 'array') {
            $value = self::toArray($value);
            if (!empty($args['items'])) {
                foreach ($value as $index => $v) {
                    $value[$index] = $this->sanitize($v, $args['items'], $param . '[' . $index . ']');
                }
            }
            if (!empty($args['uniqueItems'])) {
                $value = array_map('unserialize', array_values(array_unique(array_map('serialize', $value))));
            }
            return $value;
        }
        if ($type === 'object') {
            $value = self::toObject($value);
            foreach ($value as $property => $v) {
                $schema = $args['properties'][$property] ?? self::patternProperty((string) $property, $args);
                if ($schema === null && isset($args['additionalProperties'])) {
                    if ($args['additionalProperties'] === false) {
                        unset($value[$property]);
                        continue;
                    }
                    $schema = is_array($args['additionalProperties']) ? $args['additionalProperties'] : null;
                }
                if ($schema !== null) {
                    $value[$property] = $this->sanitize($v, $schema, $param . '[' . $property . ']');
                }
            }
            return $value;
        }
        if ($type === 'null') {
            return null;
        }
        if ($type === 'integer') {
            return (int) $value;
        }
        if ($type === 'number') {
            return (float) $value;
        }
        if ($type === 'boolean') {
            return self::toBoolean($value);
        }
        if (isset($args['format']) && $type === 'string' && in_array($args['format'], ['hex-color', 'date-time', 'email', 'uri', 'ip', 'uuid', 'text-field', 'textarea-field'], true)) {
            return ($this->format)((string) $args['format'], $value);
        }
        return $type === 'string' ? (string) $value : $value;
    }

    /** Drops the properties whose schema does not list the context; an array whose items do not is emptied. */
    public function filterByContext(mixed $data, array $schema, string $context): mixed
    {
        if (isset($schema['anyOf'])) {
            $matching = $this->anyMatching($data, $schema, '');
            if ($matching !== null) {
                $schema['type'] ??= $matching['type'] ?? null;
                $schema['properties'] = $matching['properties'] ?? [];
            }
        }
        if (!is_array($data) && !is_object($data)) {
            return $data;
        }
        $types = (array) ($schema['type'] ?? []);
        $isArray = in_array('array', $types, true);
        $isObject = in_array('object', $types, true);
        if ($isArray && $isObject) {
            if (self::isArray($data)) {
                $isObject = false;
            } else {
                $isArray = false;
            }
        }
        $additional = $isObject && isset($schema['additionalProperties']) && is_array($schema['additionalProperties']) ? $schema['additionalProperties'] : null;
        foreach ($data as $key => $value) {
            $check = [];
            if ($isArray) {
                $check = $schema['items'] ?? [];
            } elseif ($isObject) {
                $check = $schema['properties'][$key] ?? self::patternProperty((string) $key, $schema) ?? $additional ?? [];
            }
            if (!isset($check['context'])) {
                continue;
            }
            if (!in_array($context, $check['context'], true)) {
                if ($isArray) {
                    return [];
                }
                if (is_object($data)) {
                    unset($data->{$key});
                } else {
                    unset($data[$key]);
                }
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $filtered = $this->filterByContext($value, $check, $context);
                if (is_object($data)) {
                    $data->{$key} = $filtered;
                } else {
                    $data[$key] = $filtered;
                }
            }
        }
        return $data;
    }

    /** The first anyOf branch the value validates against. */
    public function anyMatching(mixed $value, array $args, string $param): ?array
    {
        foreach ($args['anyOf'] ?? [] as $schema) {
            if ($this->validate($value, $schema, $param) === true) {
                return $schema;
            }
        }
        return null;
    }

    /** An object schema that names its properties forbids the others unless it says otherwise, all the way down. */
    private const ENDPOINT_KEYWORDS = ['type', 'format', 'enum', 'items', 'properties', 'additionalProperties', 'patternProperties', 'minProperties', 'maxProperties', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems', 'uniqueItems', 'anyOf', 'oneOf'];

    /**
     * The argument map a route derives from an item schema: every writable
     * property with the default validators, its keywords, defaults and
     * required flags on the create route only, and any arg_options overrides.
     */
    public static function endpointArgs(array $schema, bool $creatable): array
    {
        $args = [];
        foreach ((array) ($schema['properties'] ?? []) as $field => $params) {
            if (!empty($params['readonly'])) {
                continue;
            }
            $arg = ['validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'rest_sanitize_request_arg'];
            if ($creatable && isset($params['default'])) {
                $arg['default'] = $params['default'];
            }
            if ($creatable && !empty($params['required'])) {
                $arg['required'] = true;
            }
            foreach (self::ENDPOINT_KEYWORDS as $keyword) {
                if (isset($params[$keyword])) {
                    $arg[$keyword] = $params[$keyword];
                }
            }
            if (isset($params['arg_options'])) {
                $overrides = (array) $params['arg_options'];
                if (!$creatable) {
                    unset($overrides['required']);
                }
                $arg = array_merge($arg, $overrides);
            }
            $args[$field] = $arg;
        }
        return $args;
    }

    public static function closeObjects(array $schema): array
    {
        $type = (array) ($schema['type'] ?? []);
        if (in_array('object', $type, true)) {
            foreach ($schema['properties'] ?? [] as $key => $child) {
                $schema['properties'][$key] = self::closeObjects($child);
            }
            $schema['additionalProperties'] ??= false;
        }
        if (in_array('array', $type, true) && isset($schema['items'])) {
            $schema['items'] = self::closeObjects($schema['items']);
        }
        return $schema;
    }

    // ---- The type vocabulary.

    public static function isBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['false', 'true', '0', '1'], true);
        }
        return is_int($value) && in_array($value, [0, 1], true);
    }

    public static function toBoolean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['false', '0'], true)) {
                $value = false;
            }
        }
        return (bool) $value;
    }

    public static function isInteger(mixed $value): bool
    {
        return is_numeric($value) && round((float) $value) === (float) $value;
    }

    public static function isArray(mixed $value): bool
    {
        if (is_scalar($value)) {
            $value = self::list($value);
        }
        return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
    }

    public static function toArray(mixed $value): array
    {
        if (is_scalar($value)) {
            return self::list($value);
        }
        return is_array($value) ? array_values($value) : [];
    }

    public static function isObject(mixed $value): bool
    {
        if ($value === '' || $value instanceof stdClass) {
            return true;
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        return is_array($value);
    }

    public static function toObject(mixed $value): array
    {
        if ($value === '') {
            return [];
        }
        if ($value instanceof stdClass) {
            return (array) $value;
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        return is_array($value) ? $value : [];
    }

    /** The one type among the candidates the value reads as, in the reference's order of preference; '' when none. */
    public static function bestType(mixed $value, array|string $types): string
    {
        $checks = ['array' => self::isArray(...), 'object' => self::isObject(...), 'null' => static fn ($v) => $v === null, 'boolean' => self::isBoolean(...), 'integer' => self::isInteger(...), 'number' => static fn ($v) => is_numeric($v), 'string' => static fn ($v) => is_string($v)];
        $types = array_values(array_intersect(array_keys($checks), (array) $types));
        if (count($types) === 1) {
            return $types[0];
        }
        foreach ($checks as $type => $check) {
            if (in_array($type, $types, true) && $check($value)) {
                return $type;
            }
        }
        return '';
    }

    public static function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            if (count($a) !== count($b)) {
                return false;
            }
            foreach ($a as $index => $value) {
                if (!isset($b[$index]) || !self::valuesEqual($value, $b[$index])) {
                    return false;
                }
            }
            return true;
        }
        if ((is_int($a) && is_float($b)) || (is_float($a) && is_int($b))) {
            return (float) $a === (float) $b;
        }
        return $a === $b;
    }

    public static function patternProperty(string $property, array $args): ?array
    {
        foreach ($args['patternProperties'] ?? [] as $pattern => $schema) {
            if (preg_match('#' . str_replace('#', '\\#', (string) $pattern) . '#u', $property)) {
                return $schema;
            }
        }
        return null;
    }

    /** Keys sorted at every level, so two equal structures serialise the same. */
    public static function stabilize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = self::stabilize($v);
        }
        return $value;
    }

    /** A timestamp for an RFC3339-shaped date, false otherwise; $forceUtc reads the offset as Z. */
    public static function parseDate(mixed $date, bool $forceUtc = false): int|false
    {
        $date = (string) $date;
        if ($forceUtc) {
            $date = (string) preg_replace('#^(.*?)[+-]\d{2}:?\d{2}$#', '$1Z', $date);
        }
        if (!preg_match('#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#', $date)) {
            return false;
        }
        return strtotime($date);
    }

    public static function parseHexColor(mixed $color): mixed
    {
        return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color) ? $color : false;
    }

    public static function isUuid(mixed $uuid): bool
    {
        return is_string($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /** @return list<string> */
    private static function list(mixed $value): array
    {
        return preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
