<?php

/**
 * The AVIF parser's stream and byte helpers (probe avif-connectors-helpers).
 * The engine reads AVIF files itself (Minn\Media\AvifInfo); these are the
 * reference parser's helpers, for code that calls them.
 */

namespace Avifinfo;

/** The next $num_bytes of a stream, or false when it holds fewer (the position moves either way). */
function read($handle, $num_bytes)
{
    $data = fread($handle, $num_bytes);
    return $data !== false && strlen($data) >= $num_bytes ? $data : false;
}

/** The position moved on $num_bytes; true even past the end, as a stream allows. */
function skip($handle, $num_bytes)
{
    return fseek($handle, $num_bytes, SEEK_CUR) === 0;
}

/** The first $num_bytes of $input read as one unsigned big-endian number. */
function read_big_endian($input, $num_bytes)
{
    $value = 0;
    for ($i = 0; $i < $num_bytes; $i++) {
        $value = ($value << 8) | ord($input[$i]);
    }
    return $value;
}
