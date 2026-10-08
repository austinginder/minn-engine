<?php

declare(strict_types=1);

use Minn\Json;

/** The reference's own answer for one fixture row, read from the captured file. */
$row = static function (string $file, string $label): mixed {
    foreach ((array) Json::file(dirname(__DIR__, 2) . "/contracts/fixtures/api/{$file}.json") as $entry) {
        if (is_array($entry) && ($entry[0] ?? null) === $label) {
            return $entry[1];
        }
    }
    return null;
};

/** What a closure throws, as class and code, or null when it returns. */
$throws = static function (Closure $call): ?array {
    try {
        $call();
        return null;
    } catch (Throwable $e) {
        return [get_class($e), $e->getCode(), $e->getMessage()];
    }
};

$scratch = sys_get_temp_dir() . '/minn-json-unit-' . getmypid();
@mkdir($scratch);
file_put_contents("{$scratch}/good.json", '{"a":{"b":1}}');
file_put_contents("{$scratch}/broken.json", '{"a":');
register_shutdown_function(static fn () => array_map('unlink', glob("{$scratch}/*.json")) && rmdir($scratch));

/** Minn\Json: the one door for JSON, with wp_json_encode()'s repair behind encode(). */
return [
    'encode writes slashes and characters as themselves by default' => static fn () => Json::encode(['url' => 'https://example.com/a', 'name' => 'Café']) === '{"url":"https://example.com/a","name":"Café"}',
    'ESCAPED writes what the reference writes for the same value' => static fn () => Json::encode(['a' => 'é', 'b' => '/', 'c' => "bad\xB1"], Json::ESCAPED) === $row('functions', 'wp_json_encode'),
    'a string cut mid-character encodes with "?" for the bad byte' => static fn () => Json::encode(['title' => substr('Café au lait', 0, 4)]) === '{"title":"Caf?"}',
    'keys are repaired as well as values' => static fn () => Json::encode(["k\xE9y" => "x \xE9 y"]) === '{"k?y":"x ? y"}',
    'JSON_THROW_ON_ERROR skips the repair, as the reference does' => static fn () => ($throws(static fn () => Json::encode(["x\xC3"], JSON_THROW_ON_ERROR))[0] ?? null) === JsonException::class,
    'NAN cannot be encoded even after the repair' => static fn () => ($throws(static fn () => Json::encode(['a' => NAN])) ?? [])[0] === JsonException::class,
    'nesting past the depth throws a JsonException with the depth code' => static fn () => array_slice($throws(static fn () => Json::encode([[["x\xC3"]]], Json::READABLE, 1)) ?? [], 0, 2) === [JsonException::class, JSON_ERROR_DEPTH],
    'decode reads objects as arrays' => static fn () => Json::decode('{"a":{"b":[1,2]}}') === ['a' => ['b' => [1, 2]]],
    'decode throws on text that is not JSON' => static fn () => array_slice($throws(static fn () => Json::decode('{"a":')) ?? [], 0, 2) === [JsonException::class, JSON_ERROR_SYNTAX],
    'decode hands back the fallback named at the call' => static fn () => Json::decode('{"a":', or: []) === [] && Json::decode('', or: ['none']) === ['none'] && Json::decode('{"a":', or: null) === null,
    'a fallback is only for broken text: the JSON null is a value' => static fn () => Json::decode('null', or: 5) === null && Json::decode('0', or: 5) === 0,
    'file reads and decodes' => static fn () => Json::file("{$scratch}/good.json") === ['a' => ['b' => 1]],
    'a missing file throws naming it, or gives the fallback' => static fn () => ($throws(static fn () => Json::file("{$scratch}/missing.json")) ?? [])[2] === "{$scratch}/missing.json could not be read." && Json::file("{$scratch}/missing.json", or: []) === [],
    'a broken file throws naming it, or gives the fallback' => static fn () => ($throws(static fn () => Json::file("{$scratch}/broken.json")) ?? [])[2] === "{$scratch}/broken.json is not JSON: Syntax error" && Json::file("{$scratch}/broken.json", or: null) === null,
    'repair matches the reference string by string' => static fn () => array_map(static fn (string $s) => bin2hex(Json::repair($s)), ['plain', "caf\xC3\xA9", "bad \xE9 byte", '', "\xFF\xFE"]) === $row('functions-utils', 'json convert string'),
    'repair past the depth throws the reference\'s plain Exception' => static fn () => $throws(static fn () => Json::repair([[1]], 0)) === [Exception::class, 0, 'Reached depth limit'],
];
