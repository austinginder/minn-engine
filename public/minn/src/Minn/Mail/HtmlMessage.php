<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * What a mailer reads out of an HTML message: the images it can carry
 * inline (a src or background naming a file under the base directory, or
 * a data: URI of a raster image), the content id each one gets (the first
 * 32 hex digits of a SHA-256 of the URL, or of a data URI's bytes, at
 * "phpmailer.0"), and the plain-text version (head, style and script
 * dropped, tags stripped, entities decoded into the charset).
 */
final class HtmlMessage
{
    public const NO_TEXT = 'This is an HTML-only message. To view it, activate HTML in your email application.';

    /**
     * Every quoted src or background value, in document order.
     *
     * @return list<array{attribute: string, url: string}>
     */
    public static function images(string $html): array
    {
        preg_match_all('/\b(src|background)=(["\'])(.*?)\2/i', $html, $matches, PREG_SET_ORDER);
        return array_map(static fn (array $m): array => ['attribute' => $m[1], 'url' => $m[3]], $matches);
    }

    /**
     * A data: URI's bytes and type when it is a raster image, else null.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function dataImage(string $url): ?array
    {
        if (!preg_match('#^data:([^,]*),(.*)$#is', $url, $m)) {
            return null;
        }
        $meta = explode(';', strtolower($m[1]));
        if (!preg_match('#^image/[a-z0-9.+-]+$#', $meta[0]) || str_contains($meta[0], 'svg')) {
            return null;
        }
        $data = end($meta) === 'base64' ? base64_decode($m[2], true) : rawurldecode($m[2]);
        return $data === false ? null : [$data, $meta[0]];
    }

    /** A relative path with no scheme and no step up out of the base directory, or null. */
    public static function localPath(string $url): ?string
    {
        if ($url === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//|/|\\\\)#i', $url) || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $url)) {
            return null;
        }
        return $url;
    }

    /** The content id the mailer gives an image. */
    public static function cid(string $source): string
    {
        return substr(hash('sha256', $source), 0, 32) . '@phpmailer.0';
    }

    /** The plain-text version of an HTML message. */
    public static function text(string $html, string $charset): string
    {
        $html = (string) preg_replace('#<(head|title|style|script)\b[^>]*>.*?</\1\s*>#si', '', $html);
        return html_entity_decode(trim(strip_tags($html)), ENT_QUOTES, $charset);
    }
}
