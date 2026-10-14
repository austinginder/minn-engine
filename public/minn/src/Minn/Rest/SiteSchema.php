<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\I18n\Numbers;
use Minn\Runtime\Handoff;
use Minn\Runtime\Refusal;
use Minn\Support\Escape;
use Minn\Support\Sanitize;

/**
 * The site's JSON-schema handler: one Schema whose email test, number
 * formatter and string-format sanitizers carry the filters plugins hook
 * (is_email, number_format_i18n, the sanitizers), and the answers the REST
 * helpers give with it (true or a list of errors).
 */
final class SiteSchema
{
    private static ?Schema $schema = null;

    /** The site's schema handler. */
    public static function get(): Schema
    {
        return self::$schema ??= new Schema(
            static fn (string $email): bool => (bool) Sanitize::isEmail($email),
            static fn (int|float $n): string => (string) Numbers::format($n),
            static fn (string $format, mixed $value): mixed => match ($format) {
                'hex-color' => (string) Sanitize::hexColor($value),
                'email' => Sanitize::email($value),
                'uri' => Escape::url($value, null, 'db'),
                'textarea-field' => Sanitize::textareaField($value),
                default => Sanitize::textField($value),
            },
        );
    }

    /** Whether a value fits a schema (rest_validate_value_from_schema): true, or the errors saying why not. */
    public static function validate(mixed $value, mixed $args, mixed $param = ''): mixed
    {
        return self::result(self::get()->validate($value, (array) $args, (string) $param));
    }

    /** A value made to fit a schema (rest_sanitize_value_from_schema). */
    public static function sanitize(mixed $value, mixed $args, mixed $param = ''): mixed
    {
        return self::get()->sanitize($value, (array) $args, (string) $param);
    }

    /** True, or the list of errors a refusal reads as. */
    public static function result(true|Refusal $result): mixed
    {
        return $result === true ? true : Handoff::newError($result->code, $result->message, $result->data);
    }
}
