<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The heartbeat a signed-in page beats through admin-ajax.php, answered as
 * the reference answers it (suite ajax): the heartbeat nonce checked, and
 * when it is stale or wrong, wp_refresh_nonces asked for fresh ones (a
 * wrong one ends the beat there, marked expired); otherwise what the page
 * sent goes through heartbeat_received, the answer through heartbeat_send,
 * and heartbeat_tick hears it before the server's time is added.
 */
final class Heartbeat
{
    /**
     * The answer for a beat, from the posted fields (unslashed).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function answer(array $post): array
    {
        $state = \wp_verify_nonce((string) ($post['_nonce'] ?? ''), 'heartbeat-nonce');
        $screen = empty($post['screen_id']) ? 'front' : \sanitize_key((string) $post['screen_id']);
        $data = empty($post['data']) ? [] : (array) $post['data'];
        $response = [];
        if ($state !== 1) {
            $response = (array) \apply_filters('wp_refresh_nonces', $response, $data, $screen);
            if ($state === false) {
                $response['nonces_expired'] = true;
                return $response;
            }
        }
        if ($data !== []) {
            $response = (array) \apply_filters('heartbeat_received', $response, $data, $screen);
        }
        $response = (array) \apply_filters('heartbeat_send', $response, $screen);
        \do_action('heartbeat_tick', $response, $screen);
        $response['server_time'] = time();
        return $response;
    }
}
