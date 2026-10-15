<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Ops\Directory;
use Minn\Ops\Packages;
use Minn\Rest\Reply;
use Minn\RestError;
use Minn\Runtime\LegacyPack;
use Minn\Runtime\PluginActivation;
use Minn\Runtime\Runtime;

/**
 * The Minn Legacy Pack installed for an editor that needs it
 * (Runtime\LegacyPack): the latest release the Minn update service follows
 * for the pack's repository, its package checked against the sha256 the
 * service recorded, unpacked as any plugin is, then activated. A site that
 * has the pack only activates it. Minn asks Minn: the package comes from the
 * update service, never from GitHub directly.
 */
final readonly class LegacyPackController
{
    public function __construct(private Packages $packages, private Http $http = new Http())
    {
    }

    /** Installs the pack when the site lacks it, and activates it; the pack's state after. */
    #[Route(Method::Post, '/minn-admin/v1/legacy-pack/install', policy: new Policy(Access::Floor, caps: ['install_plugins', 'activate_plugins']))]
    public function install(Request $request): Response
    {
        $version = null;
        if (!LegacyPack::status()['installed']) {
            $release = $this->release();
            $zip = $this->packages->fetch($release['package'], Directory::ORIGIN);
            if (!hash_equals($release['sha256'], hash('sha256', $zip))) {
                throw new RestError('checksum_mismatch', 'The Minn Legacy Pack did not match the checksum the update service recorded.', 502);
            }
            $this->packages->unpack($zip, 'extension');
            $version = $release['version'];
        }
        $refusal = PluginActivation::activate(LegacyPack::PLUGIN, Runtime::hooks());
        if ($refusal !== null && $refusal->code !== 'unexpected_output') {
            throw new RestError($refusal->code, $refusal->message, 500);
        }
        return Reply::answer($request, ['installed' => $version, 'legacyPack' => LegacyPack::status()]);
    }

    /**
     * The pack's latest release as the update service describes it: a
     * package it serves and the sha256 it recorded.
     *
     * @return array{version: string, package: string, sha256: string}
     */
    private function release(): array
    {
        $reply = $this->http->get(Directory::url('github/' . LegacyPack::REPOSITORY . '/releases/latest'), headers: ['Accept: application/json'], timeout: 10.0, hosts: [Directory::ORIGIN], maxBytes: 1048576);
        $answer = $reply->json();
        if (!$reply->ok() || !is_array($answer)) {
            $reason = is_array($answer) && is_string($answer['reason'] ?? null) ? ' (' . $answer['reason'] . ')' : '';
            throw new RestError('no_release', 'The Minn update service has no release of the Minn Legacy Pack to offer' . $reason . '.', $reply->code === 404 ? 404 : 502);
        }
        $package = (string) ($answer['package'] ?? '');
        $sha256 = strtolower((string) ($answer['sha256'] ?? ''));
        if (!str_starts_with($package, Directory::PACKAGES) || preg_match('/^[0-9a-f]{64}$/', $sha256) !== 1) {
            throw new RestError('bad_release', 'The Minn update service described the Minn Legacy Pack without a package or a checksum.', 502);
        }
        return ['version' => (string) ($answer['version'] ?? ''), 'package' => $package, 'sha256' => $sha256];
    }
}
