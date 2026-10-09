<?php

/**
 * Speculation rules kept by mode and id (probe speculative-loading): a rule
 * needs a mode the browser knows, an id that starts with a letter (lower
 * case, digits, - and _ after it) used once, either urls (a list rule) or
 * where (a document rule) but not both, and a valid eagerness when it
 * names one. It serializes as each mode's rules, without their ids.
 */
final class WP_Speculation_Rules implements JsonSerializable
{
    private $rules_by_mode = [];
    private static $mode_allowlist = ['prefetch' => true, 'prerender' => true];
    private static $eagerness_allowlist = ['immediate' => true, 'eager' => true, 'moderate' => true, 'conservative' => true];
    private static $source_allowlist = ['list' => true, 'document' => true];

    public function add_rule(string $mode, string $id, array $rule): bool
    {
        $refusal = $this->refusal($mode, $id, $rule);
        if ($refusal !== null) {
            _doing_it_wrong(__METHOD__, $refusal, '6.8.0');
            return false;
        }
        $this->rules_by_mode[$mode][$id] = $rule;
        return true;
    }

    public function has_rule(string $mode, string $id): bool
    {
        return isset($this->rules_by_mode[$mode][$id]);
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_map('array_values', $this->rules_by_mode);
    }

    private function is_valid_id(string $id): bool
    {
        return preg_match('/^[a-z][a-z0-9_-]+$/', $id) === 1;
    }

    public static function is_valid_mode($mode): bool
    {
        return is_string($mode) && isset(self::$mode_allowlist[$mode]);
    }

    public static function is_valid_eagerness($eagerness): bool
    {
        return is_string($eagerness) && isset(self::$eagerness_allowlist[$eagerness]);
    }

    public static function is_valid_source(string $source): bool
    {
        return isset(self::$source_allowlist[$source]);
    }

    /** Why a rule cannot be added, as the reference's notice says it, or null. */
    private function refusal(string $mode, string $id, array $rule): ?string
    {
        $taken = array_filter($this->rules_by_mode, static fn (array $rules) => isset($rules[$id])) !== [];
        return match (true) {
            !self::is_valid_mode($mode) => sprintf('The value "%s" is not a valid speculation rules mode.', $mode),
            !$this->is_valid_id($id) => sprintf('The value "%s" is not a valid ID for a speculation rule.', $id),
            $taken => sprintf('A speculation rule with ID "%s" already exists.', $id),
            isset($rule['where']) === isset($rule['urls']) => 'A speculation rule must include either a "where" key or a "urls" key, but not both.',
            isset($rule['source']) && !self::is_valid_source((string) $rule['source']) => sprintf('The value "%s" is not a valid source for a speculation rule.', $rule['source']),
            ($rule['source'] ?? '') === 'list' && isset($rule['where']) => 'A speculation rule of source "list" must not include a "where" key.',
            ($rule['source'] ?? '') === 'document' && isset($rule['urls']) => 'A speculation rule of source "document" must not include a "urls" key.',
            isset($rule['eagerness']) && !self::is_valid_eagerness($rule['eagerness']) => sprintf('The value "%s" is not a valid eagerness for a speculation rule.', $rule['eagerness']),
            default => null,
        };
    }
}
