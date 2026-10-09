<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * One translation file's contents: its headers as written, its messages
 * keyed the gettext way (the context, a 0x04 byte, then the original; the
 * original alone without a context), each with its translated forms and,
 * where the file says, the original plural, and the file's plural rule.
 * An empty translation is still a translation: it is what the file says.
 */
final readonly class Catalog
{
    /**
     * @param array<string, string> $headers
     * @param array<string, array{translations: list<string>, plural: ?string}> $entries
     */
    public function __construct(public array $headers, public array $entries, public PluralRule $plural)
    {
    }

    /** A catalog from headers and entries, its plural rule taken from the headers. */
    public static function from(array $headers, array $entries): self
    {
        $catalog = new self($headers, $entries, PluralRule::english());
        return new self($headers, $entries, PluralRule::fromHeader($catalog->headerValue('Plural-Forms')));
    }

    /** The lookup key for an original in a context; an empty or missing context is no context. */
    public static function key(string $original, ?string $context): string
    {
        return $context === null || $context === '' ? $original : $context . "\x04" . $original;
    }

    /** A header's value, its name matched without case. */
    public function headerValue(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp((string) $header, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /** The translation of a message, empty when the file says so, or null when the file has no entry. */
    public function translate(string $key): ?string
    {
        return isset($this->entries[$key]) ? (string) ($this->entries[$key]['translations'][0] ?? '') : null;
    }

    /** The form of a message a count takes under this file's rule (the first form when the file has fewer), or null when the file has no entry. */
    public function translatePlural(string $key, int $count): ?string
    {
        if (!isset($this->entries[$key])) {
            return null;
        }
        $forms = $this->entries[$key]['translations'];
        return (string) ($forms[$this->plural->index($count)] ?? $forms[0] ?? '');
    }

    /** Whether the file has an entry for the message. */
    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }
}
