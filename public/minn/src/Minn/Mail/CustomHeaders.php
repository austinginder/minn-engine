<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * A mailer's custom headers changed in place (PHPMailer::replaceCustomHeader,
 * probe db-mail-rewrite-doubts): the first header of the name takes the new
 * value and the others of that name go; a name nothing has is not added. A
 * name or value with a line break, or an empty name, changes nothing and
 * refuses without an error when a header would have taken it.
 */
final class CustomHeaders
{
    /**
     * Whether the replacement held, and the headers after it.
     *
     * @param list<array{0: string, 1: string}>|array<int, array{0: string, 1: string}> $headers
     * @return array{0: bool, 1: array<int, array{0: string, 1: string}>}
     */
    public static function replace(array $headers, mixed $name, mixed $value): array
    {
        if ($value === null && str_contains((string) $name, ':')) {
            [$name, $value] = explode(':', (string) $name, 2);
        }
        [$name, $value] = [trim((string) $name), trim((string) $value)];
        $matches = array_keys(array_filter($headers, static fn (array $header): bool => $header[0] === $name));
        if ($matches === []) {
            return [true, $headers];
        }
        if ($name === '' || strpbrk($name . $value, "\r\n") !== false) {
            return [false, $headers];
        }
        $headers[array_shift($matches)] = [$name, $value];
        foreach ($matches as $index) {
            unset($headers[$index]);
        }
        return [true, $headers];
    }
}
