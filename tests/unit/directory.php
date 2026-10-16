<?php

declare(strict_types=1);

use Minn\Cron\Cron;
use Minn\Http\Fake;
use Minn\Ops\Asking;
use Minn\Ops\Directory;
use Minn\Ops\Releases;

/**
 * How the engine introduces itself to the update service: Minn and its
 * version, and "(lab)" from a development install so the service counts it
 * apart from real sites. Nothing else about the site leaves it. The daily
 * pass marks its checks (X-Minn-Check: daily), once every 24 hours on slots
 * that do not drift, so the service can count one check a site a day.
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
    'the daily pass marks its release check; any other check goes unmarked' => static function () {
        $sent = [];
        foreach ([Asking::Daily, Asking::Now] as $asking) {
            $fake = new Fake(['updates.minn.run/*' => ['version' => '0.2.0']]);
            $stored = null;
            (new Releases(static fn () => $stored, static function (string $json) use (&$stored): void {
                $stored = $json;
            }, '0.1.0', http: $fake->http()))->refresh($asking);
            $sent[] = in_array('X-Minn-Check: daily', $fake->sent()[0]->headers, true);
        }
        return $sent === [true, false] && Asking::Daily->headers(['A: b']) === ['A: b', 'X-Minn-Check: daily'] && Asking::Now->headers(['A: b']) === ['A: b'] ?: json_encode($sent);
    },
    'the daily slots stay 24 hours apart however late a run is, and a site that slept checks once' => static function () {
        $day = 86400;
        $first = 1_000_000;
        $cases = [
            [0, $first, $first + $day],                         // the first run anchors the slots
            [$first + $day, $first + $day + 600, $first + 2 * $day],      // ten minutes late: the next slot is not pushed
            [$first + $day, $first + 2 * $day - 1, $first + 2 * $day],    // a second short of a day late
            [$first + $day, $first + 8 * $day + 5, $first + 9 * $day],    // a week asleep: one run, the next slot ahead
        ];
        foreach ($cases as [$slot, $now, $want]) {
            if (Cron::nextSlot($slot, $now) !== $want) {
                return "slot {$slot} at {$now}: " . Cron::nextSlot($slot, $now) . " (want {$want})";
            }
        }
        return true;
    },
];
