<?php

require_once __DIR__ . '/WP_Http_Streams.php';

/** The socket transport under its older name: WP_Http_Streams in every respect (probe http-transports). */
#[AllowDynamicProperties]
class WP_HTTP_Fsockopen extends WP_Http_Streams
{
}
