<?php

declare(strict_types=1);

use Minn\Runtime\PackageDownload;
use Minn\Runtime\Refusal;

/**
 * The publisher's say over its own download, read from the reply the
 * reference's upgrader_pre_download filter gives: a readable file it
 * verified, a refusal, or nothing at all. Reading the reply is pure; the
 * filter itself is proven where a runtime is booted.
 */
$reply = new ReflectionMethod(PackageDownload::class, 'refusal');
$reply->setAccessible(true);

final class PackageDownloadError
{
    public function __construct(private string $code, private string $message)
    {
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

return [
    'nothing is asked when no runtime is booted' => static fn (): bool|string => PackageDownload::verified('https://example.test/p.zip', ['plugin' => 'x/x.php']) === null ? true : 'asked anyway',
    'an error reply becomes a refusal that carries its reason' => static function () use ($reply): bool|string {
        $out = $reply->invoke(null, new PackageDownloadError('minn_admin_bad_package_hash', 'does not match the sha256'));
        return $out instanceof Refusal && $out->code === 'minn_admin_bad_package_hash' && $out->message === 'does not match the sha256' ? true : json_encode($out);
    },
    'false, a code-less error, and a plain value are no answer' => static function () use ($reply): bool|string {
        return $reply->invoke(null, false) === null
            && $reply->invoke(null, null) === null
            && $reply->invoke(null, 'not a file') === null
            && $reply->invoke(null, new PackageDownloadError('', '')) === null
            ? true : 'an answer was read where there was none';
    },
];
