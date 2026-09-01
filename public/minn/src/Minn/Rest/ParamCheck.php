<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Refusal;

/**
 * The required / validate / sanitize pass over a request's declared arguments.
 * The callbacks are plugin code and may answer with a WP_Error; the caller
 * hands them in already normalised to true, false, or [message, details].
 */
final class ParamCheck
{
    /**
     * The first refusal among the request's parameters, or null when all pass.
     *
     * @param array<string, array<string, mixed>> $args
     * @param callable(string): mixed $param the request's value for one argument
     * @param callable(string, mixed): (bool|array{0: string, 1: mixed}) $validate true, false, or [message, details]
     */
    public static function validate(array $args, callable $param, callable $validate): ?Refusal
    {
        $missing = [];
        foreach ($args as $key => $arg) {
            if (($arg['required'] ?? false) === true && $param($key) === null) {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            return new Refusal('rest_missing_callback_param', sprintf('Missing parameter(s): %s', implode(', ', $missing)), ['status' => 400, 'params' => $missing]);
        }
        $invalid = [];
        $details = [];
        foreach ($args as $key => $arg) {
            $value = $param($key);
            if ($value === null || empty($arg['validate_callback'])) {
                continue;
            }
            $verdict = $validate($key, $value);
            if ($verdict === false) {
                $invalid[$key] = 'Invalid parameter.';
                $details[$key] = ['code' => 'rest_invalid_param', 'message' => 'Invalid parameter.', 'data' => null];
            } elseif (is_array($verdict)) {
                [$invalid[$key], $details[$key]] = $verdict;
            }
        }
        return self::refusal($invalid, $details);
    }

    /**
     * The parameters after sanitising, or the refusal listing every invalid one.
     *
     * @param array<string, array<string, mixed>> $params the request's parameter groups, by source, in precedence order
     * @param array<string, array<string, mixed>> $args
     * @param callable(string, mixed): (array{value: mixed}|array{error: array{0: string, 1: mixed}}) $sanitize
     * @return array{0: array<string, array<string, mixed>>, 1: Refusal|null} the parameters after sanitising, and the refusal if any failed
     */
    public static function sanitize(array $params, array $args, callable $sanitize): array
    {
        $invalid = [];
        $details = [];
        foreach ($params as $source => $values) {
            foreach ($values as $key => $value) {
                $callback = $args[$key]['sanitize_callback'] ?? null;
                if (!isset($args[$key]) || $callback === null || $callback === false) {
                    continue;
                }
                $result = $sanitize($key, $value);
                if (isset($result['error'])) {
                    [$invalid[$key], $details[$key]] = $result['error'];
                } else {
                    $params[$source][$key] = $result['value'];
                }
            }
        }
        return [$params, self::refusal($invalid, $details)];
    }

    private static function refusal(array $invalid, array $details): ?Refusal
    {
        if ($invalid === []) {
            return null;
        }
        return new Refusal('rest_invalid_param', sprintf('Invalid parameter(s): %s', implode(', ', array_keys($invalid))), ['status' => 400, 'params' => $invalid, 'details' => $details]);
    }
}
