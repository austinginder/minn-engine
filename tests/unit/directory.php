<?php

declare(strict_types=1);

use Minn\Http\Fake;
use Minn\Ops\Directory;
use Minn\Ops\Releases;

/**
 * How the engine introduces itself to the update service: Minn and its
 * version, and "(lab)" from a development install so the service counts it
 * apart from real sites. Nothing else about the site leaves it.
 */
return [
    'a development install is lab: its environment type, or a development host in its address' => static function () {
        $lab = ['local' => ['https://shop.example.com', 'local'], 'development' => ['https://shop.example.com', 'Development']];
        foreach (['http://localhost', 'https://minn.localhost/', 'https://site.test', 'http://box.local:8080', 'https://x.example', 'https://a.internal', 'http://127.0.0.1:8123', 'http://10.0.0.5', 'http://192.168.1.20', 'http://172.20.0.3', 'http://[::1]/'] as $home) {
            $lab[$home] = [$home, ''];
        }
        foreach ($lab as $label => [$home, $environment]) {
            if (!Directory::lab($home, $environment)) {
                return "not lab: {$label}";
            }
        }
        return true;
    },
    'a real site is not: a public address, staging, production, or no address at all' => static function () {
        foreach ([['https://shop.example.com', ''], ['https://minn.run', 'production'], ['https://staging.shop.com', 'staging'], ['http://8.8.8.8', ''], ['https://localhost.com', ''], ['https://testing.com', ''], ['', '']] as [$home, $environment]) {
            if (Directory::lab($home, $environment)) {
                return "lab: {$home} {$environment}";
            }
        }
        return true;
    },
    'the release check introduces itself as Minn, like every other request to the service' => static function () {
        $fake = new Fake(['updates.minn.run/*' => ['version' => '0.2.0']]);
        $stored = null;
        $releases = new Releases(static fn () => $stored, static function (string $json) use (&$stored): void {
            $stored = $json;
        }, '0.1.0', http: $fake->http());
        $releases->refresh();
        $agents = array_map(static fn ($request) => $request->userAgent, $fake->sent());
        return count($agents) === 1 && preg_match('#^Minn/[0-9][^ ]*( \(lab\))?$#', $agents[0]) === 1 && $agents[0] === Directory::userAgent() ?: json_encode($agents);
    },
];
