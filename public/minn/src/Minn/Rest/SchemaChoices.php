<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Refusal;

/**
 * anyOf and oneOf the way the REST API's validation judges them (probe
 * rest-schema-choices): a choice's schemas without a type take the
 * choice's own; the refusal for matching none is named from the closest
 * schema, or lists the titles, or is plain; several matches are named by
 * their titles. Schema::validate() asks here first, then applies the
 * schema's own keywords.
 */
final readonly class SchemaChoices
{
    public function __construct(private Schema $schema)
    {
    }

    /**
     * The first of an anyOf's schemas the value matches, each without a type
     * taking the choice's own, or the refusal for matching none (probe
     * rest-media-actions).
     *
     * @return array<string, mixed>|Refusal
     */
    public function matchAny(mixed $value, array $args, string $param): array|Refusal
    {
        $errors = [];
        foreach ((array) ($args['anyOf'] ?? []) as $index => $schema) {
            $schema = (array) $schema + (isset($args['type']) ? ['type' => $args['type']] : []);
            $verdict = $this->schema->validate($value, $schema, $param);
            if ($verdict === true) {
                return $schema;
            }
            $errors[] = [$verdict, $schema, $index];
        }
        return $this->combiningError($value, $param, $errors);
    }

    /**
     * The one of a oneOf's schemas the value matches, each without a type
     * taking the choice's own; "first" settles for the first match. Matching
     * none is refused as matchAny() refuses it; matching several names them
     * when every one has a title, with their positions.
     *
     * @return array<string, mixed>|Refusal
     */
    public function matchOne(mixed $value, array $args, string $param, string $settle = 'one'): array|Refusal
    {
        $matches = [];
        $errors = [];
        foreach ((array) ($args['oneOf'] ?? []) as $index => $schema) {
            $schema = (array) $schema + (isset($args['type']) ? ['type' => $args['type']] : []);
            $verdict = $this->schema->validate($value, $schema, $param);
            if ($verdict !== true) {
                $errors[] = [$verdict, $schema, $index];
                continue;
            }
            if ($settle === 'first') {
                return $schema;
            }
            $matches[$index] = $schema;
        }
        if (count($matches) === 1) {
            return reset($matches);
        }
        if ($matches === []) {
            return $this->combiningError($value, $param, $errors);
        }
        $titled = array_filter($matches, static fn (array $schema) => isset($schema['title']));
        $message = count($titled) === count($matches)
            ? sprintf('%1$s matches %2$s, but should match only one.', $param, self::listed(array_map(static fn (array $schema) => (string) $schema['title'], array_values($matches))))
            : sprintf('%s matches more than one of the expected formats.', $param);
        return new Refusal('rest_one_of_multiple_matches', $message, ['positions' => array_keys($matches)]);
    }

    /**
     * The refusal for a value no schema of a choice matched (probe
     * rest-media-actions). With one schema, its refusal is named. Otherwise
     * the refusals that say only the value is not the schema's type are set
     * aside, and one left is named. Of several left, when the first is an
     * object's, the one whose properties share the most keys with the value
     * is named (the first of a tie, none when none share). Then, when every
     * schema has a title, they are listed; else the plain refusal.
     *
     * @param list<array{0: Refusal, 1: array<string, mixed>, 2: int|string}> $errors refusal, schema, position
     */
    public function combiningError(mixed $value, string $param, array $errors): Refusal
    {
        $kept = count($errors) === 1 ? $errors : array_values(array_filter($errors, static fn (array $error) => !($error[0]->code === 'rest_invalid_type' && is_array($error[0]->data) && ($error[0]->data['param'] ?? null) === $param)));
        $closest = count($kept) === 1 ? $kept[0] : self::closestObject($value, $kept);
        if ($closest !== null) {
            return $this->combiningFailure($param, isset($closest[1]['title']) ? (string) $closest[1]['title'] : null, $closest[0]->message, $closest[2]);
        }
        $titles = array_map(static fn (array $error) => $error[1]['title'] ?? null, $errors);
        if ($errors !== [] && !in_array(null, $titles, true)) {
            return new Refusal('rest_no_matching_schema', sprintf('%1$s is not a valid %2$s.', $param, self::listed(array_map('strval', $titles))));
        }
        return new Refusal('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
    }

    /**
     * The refusal for a value that matched none of a combinator's schemas,
     * worded from the one that came closest: its title when it has one (an
     * empty title still reads as one), and that schema's own reason.
     */
    public function combiningFailure(string $param, ?string $title, string $reason, mixed $position): Refusal
    {
        $message = $title === null
            ? sprintf('%1$s does not match the expected format. Reason: %2$s', $param, $reason)
            : sprintf('%1$s is not a valid %2$s. Reason: %3$s', $param, $title, $reason);
        return new Refusal('rest_no_matching_schema', $message, ['position' => $position]);
    }

    /**
     * Of several object schemas' refusals, the one whose properties share
     * the most keys with the value; null when the first is not an object's
     * or none shares a key.
     *
     * @param list<array{0: Refusal, 1: array<string, mixed>, 2: int|string}> $errors
     * @return array{0: Refusal, 1: array<string, mixed>, 2: int|string}|null
     */
    private static function closestObject(mixed $value, array $errors): ?array
    {
        if (count($errors) < 2 || ($errors[0][1]['type'] ?? null) !== 'object') {
            return null;
        }
        $closest = null;
        $most = 0;
        foreach ($errors as $error) {
            $shared = isset($error[1]['properties']) ? count(array_intersect_key((array) $error[1]['properties'], is_array($value) ? $value : [])) : 0;
            if ($shared > $most) {
                [$closest, $most] = [$error, $shared];
            }
        }
        return $closest;
    }

    /** Names in a sentence: "A and B", "A, B, and C". @param list<string> $names */
    private static function listed(array $names): string
    {
        if (count($names) < 3) {
            return implode(' and ', $names);
        }
        $last = array_pop($names);
        return implode(', ', $names) . ', and ' . $last;
    }
}
