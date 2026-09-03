<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The publisher's say over its own download. Before fetching an update
 * package, the reference asks `upgrader_pre_download`: a plugin that hosts
 * itself answers there, and answering is how it proves the archive is the
 * one it published. Three answers, which are the reference's:
 *
 *   a file path  the publisher fetched and verified the package itself
 *   a refusal    the publisher rejects this download, with its reason
 *   nothing      nobody vouched for it
 *
 * The engine asks the same question, and installs a package from outside
 * the wordpress.org directory only on the first answer. That is stricter
 * than the reference, which downloads whatever the offer names; here an
 * archive nobody vouched for is never unpacked over a plugin folder.
 */
final class PackageDownload
{
    private const HOOK = 'upgrader_pre_download';

    /**
     * The publisher's verified copy of the package, its refusal, or null
     * when nothing answered.
     *
     * @param array<string, string> $hookExtra what is being updated, as the reference passes it ('plugin' or 'theme')
     */
    public static function verified(string $package, array $hookExtra): string|Refusal|null
    {
        if (!Runtime::booted() || Runtime::hooks()->has(self::HOOK) === false) {
            return null;
        }
        $reply = Runtime::hooks()->filter(self::HOOK, [false, $package, null, $hookExtra]);
        if (is_string($reply) && $reply !== '' && is_readable($reply)) {
            return $reply;
        }
        return self::refusal($reply);
    }

    /** A WP_Error answer as a Refusal; anything else is no answer. */
    private static function refusal(mixed $reply): ?Refusal
    {
        if (!is_object($reply) || !method_exists($reply, 'get_error_code')) {
            return null;
        }
        $code = (string) $reply->get_error_code();
        return $code === '' ? null : new Refusal($code, (string) $reply->get_error_message());
    }
}
