<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Json;
use Minn\RestError;
use Minn\Runtime\Refusal;
use Minn\Runtime\SafeUrls;
use Minn\Support\Escape;
use Minn\Support\Sanitize;
use Minn\Theme\FontNames;

/**
 * The settings a font family or face is written with, judged and cleaned as
 * the reference does (probe font-writes). A POST sends them as a JSON
 * string: one that is not a string is refused by type, one that is not
 * JSON (or is null) as not "a valid JSON string", and what it holds is held
 * to the settings' schema (required keys first, then each key in the order
 * sent: its type, or that it is not a property), then each key that must
 * not be empty. A family's update names no slug and needs none of the
 * rest; a face's sources must each be an address the site may fetch or a
 * file the request uploads, and every file it uploads must be one of
 * them. Only the first refusal is answered, under the settings' name, its
 * own data moved under additional_data beside a 400 status. A PUT or a
 * PATCH reads the settings as the object itself, which the reference
 * cannot save (it fails with a fatal error); here such an update saves as
 * a POST would.
 */
final class FontSettings
{
    private const FAMILY = 'font_family_settings';
    private const FACE = 'font_face_settings';

    private const FAMILY_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'fontFamily' => ['type' => 'string'],
            'preview' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ];

    private const FACE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'fontFamily' => ['type' => 'string'],
            'fontStyle' => ['type' => 'string'],
            'fontWeight' => ['type' => ['string', 'integer']],
            'fontDisplay' => ['type' => 'string', 'enum' => ['auto', 'block', 'fallback', 'swap', 'optional']],
            'src' => ['anyOf' => [['type' => 'string'], ['type' => 'array', 'items' => ['type' => 'string']]]],
            'fontStretch' => ['type' => 'string'],
            'ascentOverride' => ['type' => 'string'],
            'descentOverride' => ['type' => 'string'],
            'fontVariant' => ['type' => 'string'],
            'fontFeatureSettings' => ['type' => 'string'],
            'fontVariationSettings' => ['type' => 'string'],
            'lineGapOverride' => ['type' => 'string'],
            'sizeAdjust' => ['type' => 'string'],
            'unicodeRange' => ['type' => 'string'],
            'preview' => ['type' => 'string'],
        ],
        'required' => ['fontFamily', 'src'],
        'additionalProperties' => false,
    ];

    /** A new family's settings, judged. @return array<string, mixed> */
    public static function newFamily(Request $request): array
    {
        $settings = self::decoded($request, self::FAMILY, self::FAMILY_SCHEMA + ['required' => ['name', 'slug', 'fontFamily']]);
        self::filled($settings, self::FAMILY, ['name', 'slug', 'fontFamily'], 'font_family_settings[ %s ] cannot be empty.');
        return $settings;
    }

    /** An update's settings, judged: what it names replaces what is stored, and the slug cannot change. @return array<string, mixed> */
    public static function familyChanges(Request $request): array
    {
        $settings = self::decoded($request, self::FAMILY, self::FAMILY_SCHEMA);
        if (array_key_exists('slug', $settings)) {
            throw self::refused(self::FAMILY, new Refusal('rest_invalid_param', 'font_family_settings[slug] cannot be updated.'));
        }
        self::filled($settings, self::FAMILY, ['name', 'slug', 'fontFamily'], 'font_family_settings[ %s ] cannot be empty.');
        return $settings;
    }

    /** A new face's settings, judged, its sources against the files the request uploads. @return array<string, mixed> */
    public static function newFace(Request $request): array
    {
        $settings = self::decoded($request, self::FACE, self::FACE_SCHEMA);
        self::filled($settings, self::FACE, ['fontFamily', 'src'], 'font_face_setting[ %s ] cannot be empty.');
        $sources = self::sources($settings['src']);
        foreach ($sources as $source) {
            if (!isset($request->files[$source]) && SafeUrls::validated($source) === false) {
                throw self::refused(self::FACE, new Refusal('rest_invalid_param', sprintf('font_face_settings[src] value "%s" must be a valid URL or file reference.', $source)));
            }
        }
        foreach (array_keys($request->files) as $field) {
            if (!in_array((string) $field, $sources, true)) {
                throw self::refused(self::FACE, new Refusal('rest_invalid_param', sprintf('File %s must be used in font_face_settings[src].', $field)));
            }
        }
        return $settings;
    }

    /** The theme.json version a write names, when it names one: an integer from 2 to 3, refused as any argument is. */
    public static function version(Request $request): void
    {
        $values = self::values($request);
        if (!array_key_exists('theme_json_version', $values)) {
            return;
        }
        $verdict = SiteSchema::get()->validate($values['theme_json_version'], ['type' => 'integer', 'minimum' => 2, 'maximum' => 3], 'theme_json_version');
        if ($verdict instanceof Refusal) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): theme_json_version', 400, [
                'params' => ['theme_json_version' => $verdict->message],
                'details' => ['theme_json_version' => ['code' => $verdict->code, 'message' => $verdict->message, 'data' => $verdict->data]],
            ]);
        }
    }

    /** The sources a face names, each as text, in order. @return list<string> */
    public static function sources(mixed $src): array
    {
        return array_map(static fn (mixed $source): string => is_scalar($source) ? (string) $source : '', is_array($src) ? array_values($src) : [$src]);
    }

    /**
     * A family's settings as stored: the name as plain text, the slug as a
     * slug, the font-family list quoted name by name, the preview a clean
     * address; in the order sent.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function cleanFamily(array $settings): array
    {
        $clean = [];
        foreach ($settings as $key => $value) {
            $clean[$key] = match ($key) {
                'slug' => Sanitize::title($value),
                'fontFamily' => FontNames::family($value),
                'preview' => Escape::url($value, null, 'db'),
                default => Sanitize::textField($value),
            };
        }
        return $clean;
    }

    /**
     * A face's settings as stored, in the order sent: the font-family list
     * quoted name by name, the preview a clean address, the sources left for
     * the upload to resolve, every other value as plain text (a weight given
     * as a number becomes its text).
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function cleanFace(array $settings): array
    {
        $clean = [];
        foreach ($settings as $key => $value) {
            $clean[$key] = match ($key) {
                'src' => $value,
                'fontFamily' => FontNames::family($value),
                'preview' => Escape::url($value, null, 'db'),
                default => Sanitize::textField($value),
            };
        }
        return $clean;
    }

    /**
     * The settings a write sends, decoded and held to the schema.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function decoded(Request $request, string $param, array $schema): array
    {
        $value = self::values($request)[$param] ?? null;
        if ($request->method !== Method::Post) {
            return self::object($value, $param, $schema);
        }
        $text = SiteSchema::get()->validate($value, ['type' => 'string'], $param);
        if ($text instanceof Refusal) {
            throw self::topLevel($param, $text);
        }
        $decoded = Json::decode(is_scalar($value) ? (string) $value : '', or: null);
        if ($decoded === null) {
            throw self::refused($param, new Refusal('rest_invalid_param', "{$param} parameter must be a valid JSON string."));
        }
        $verdict = SiteSchema::get()->validate($decoded, $schema, $param);
        if ($verdict instanceof Refusal) {
            throw self::refused($param, $verdict);
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Settings a PUT or a PATCH sends, read as the object itself and held to
     * the schema as any argument is (none of its keys required).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function object(mixed $value, string $param, array $schema): array
    {
        $verdict = SiteSchema::get()->validate($value, $schema, $param);
        if ($verdict instanceof Refusal) {
            throw self::topLevel($param, $verdict);
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Each key the settings name that must not be empty, in order.
     *
     * @param array<string, mixed> $settings
     * @param list<string> $keys
     */
    private static function filled(array $settings, string $param, array $keys, string $message): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings) && empty($settings[$key])) {
                throw self::refused($param, new Refusal('rest_invalid_param', sprintf($message, $key)));
            }
        }
    }

    /** The settings' refusal as the reference answers it: a 400 status, any data of the refusal's own under additional_data. */
    private static function refused(string $param, Refusal $refusal): RestError
    {
        $detail = ['code' => $refusal->code, 'message' => $refusal->message, 'data' => ['status' => 400]] + ($refusal->data === null ? [] : ['additional_data' => [$refusal->data]]);
        return new RestError('rest_invalid_param', "Invalid parameter(s): {$param}", 400, ['params' => [$param => $refusal->message], 'details' => [$param => $detail]]);
    }

    /** A refusal of the argument itself, as any argument is refused. */
    private static function topLevel(string $param, Refusal $refusal): RestError
    {
        return new RestError('rest_invalid_param', "Invalid parameter(s): {$param}", 400, ['params' => [$param => $refusal->message], 'details' => [$param => ['code' => $refusal->code, 'message' => $refusal->message, 'data' => $refusal->data]]]);
    }

    /** What a write names: its body's fields over the query's. @return array<string, mixed> */
    private static function values(Request $request): array
    {
        return $request->json() + $request->query;
    }
}
