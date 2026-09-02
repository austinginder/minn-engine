<?php

declare(strict_types=1);

use Minn\Http\Failure;

/**
 * What the engine answers when it cannot answer: an HTML page for a page
 * request, and the reference's error object for one that asked in REST, so
 * a client that speaks JSON is never handed markup to parse.
 */
return [
    'a page failure is the HTML error page, and says nothing about the cause' => static function (): bool|string {
        $response = Failure::report(new RuntimeException('the cause nobody outside should read'));
        return $response->status === 500
            && str_contains($response->headers['Content-Type'] ?? '', 'text/html')
            && $response->headers['Cache-Control'] === 'no-store'
            && !str_contains($response->body, 'the cause nobody outside should read')
            ? true : ($response->headers['Content-Type'] ?? '') . ' ' . $response->status;
    },
    'a REST failure is the error object, at the same status' => static function (): bool|string {
        $response = Failure::reportJson(new RuntimeException('the cause nobody outside should read'));
        $payload = json_decode($response->body, true);
        return $response->status === 500
            && str_contains($response->headers['Content-Type'] ?? '', 'application/json')
            && $payload['code'] === 'internal_server_error'
            && ($payload['data']['status'] ?? 0) === 500
            && !str_contains($response->body, 'the cause nobody outside should read')
            ? true : $response->body;
    },
];
