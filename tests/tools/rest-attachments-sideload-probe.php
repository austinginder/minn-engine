<?php
/**
 * The attachments controller's client-side processing (probe
 * rest-attachments-sideload): the route arguments' checks; who may
 * sideload; the folder an attachment's files go in; the file names a
 * sideload may finalize; a browser's sizes, original, scaled image and
 * source sideloaded into an attachment (names, sizes allowed, the
 * dimensions checked); and the attachment finalized with them. Also the
 * numbers wp_unique_filename gives names that read like an image's sizes.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('wp_image_editors', static fn () => ['WP_Image_Editor_GD'], 99);
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data()] : $response);
$jpeg = static function (int $width, int $height): string {
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 160, 60, 40));
    ob_start();
    imagejpeg($image, null, 80);
    return (string) ob_get_clean();
};
wp_set_current_user(0);
rest_get_server();

$uploads = wp_get_upload_dir();
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made, $uploads): void {
    wp_set_current_user(1);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_attachment($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    foreach (glob($uploads['path'] . '/zz-rasl*') ?: [] as $file) {
        if (is_file($file) && str_starts_with(basename($file), 'zz-rasl')) {
            unlink($file);
        }
    }
});
$attachment = static function (string $name, int $width, int $height, bool $sizes) use ($jpeg, &$made): int {
    $saved = wp_upload_bits($name, null, $jpeg($width, $height));
    $id = empty($saved['error']) ? wp_insert_attachment(['post_title' => 'ZZ rasl ' . $name, 'post_mime_type' => 'image/jpeg', 'post_author' => 1], $saved['file']) : 0;
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $meta = $sizes ? wp_generate_attachment_metadata($id, $saved['file']) : ['width' => $width, 'height' => $height, 'file' => _wp_relative_upload_path($saved['file']), 'filesize' => (int) filesize($saved['file']), 'sizes' => [], 'image_meta' => []];
    wp_update_attachment_metadata($id, $meta);
    return $id;
};
$author = wp_insert_user(['user_login' => 'zz_rasl_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rasl@example.com', 'role' => 'author']);
if (!is_int($author) || $author <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['users'][] = $author;
wp_set_current_user(1);
$plain = $attachment('zz-rasl-plain.jpg', 1600, 1200, false);
$sized = $attachment('zz-rasl-sized.jpg', 800, 600, true);
wp_set_current_user(0);
$ids = [$author, $plain, $sized];
$masked = static function ($value) use (&$ids, $uploads) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $json = str_replace([$uploads['basedir'], $uploads['baseurl'], $uploads['subdir']], ['{uploads}', '{uploads-url}', '/{yyyy}/{mm}'], $json);
    $json = str_replace(ltrim($uploads['subdir'], '/'), '{yyyy}/{mm}', $json);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    $json = preg_replace('/\b20\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d\b/', '{date}', $json);
    return json_decode($json, true);
};

$controller = new WP_REST_Attachments_Controller('attachment');
$side = static function (int $id, string $data, string $name, $size, array $more = []) use ($controller) {
    $request = new WP_REST_Request('POST', '/wp/v2/media/' . $id . '/sideload');
    foreach (['id' => $id, 'image_size' => $size] + $more as $key => $value) {
        $request->set_param($key, $value);
    }
    $request->set_body($data);
    $request->set_header('content-type', 'image/jpeg');
    $request->set_header('content-disposition', 'attachment; filename="' . $name . '"');
    return $request;
};

// The route arguments, over HTTPS where the routes are registered, and their checks.
$_SERVER['HTTPS'] = 'on';
$server = $GLOBALS['wp_rest_server'];
$GLOBALS['wp_rest_server'] = new WP_REST_Server();
$controller->register_routes();
$routes = $GLOBALS['wp_rest_server']->get_routes('wp/v2');
$GLOBALS['wp_rest_server'] = $server;
unset($_SERVER['HTTPS']);
$sideloadArgs = $routes['/wp/v2/media/(?P<id>[\d]+)/sideload'][0]['args'] ?? [];
$finalizeArgs = $routes['/wp/v2/media/(?P<id>[\d]+)/finalize'][0]['args'] ?? [];
$definition = static fn (array $arg) => array_map(static fn ($value) => $value instanceof Closure ? '@closure' : $value, $arg);
$check = static fn (array $arg, $value, string $param) => $shown(call_user_func($arg['validate_callback'], $value, new WP_REST_Request('POST', '/wp/v2/media/1/sideload'), $param));
$say('route arguments', [
    'sideload' => array_map($definition, $sideloadArgs),
    'finalize' => array_map($definition, $finalizeArgs),
    'image size checked' => array_map(static fn ($value) => $check($sideloadArgs['image_size'], $value, 'image_size'), ['a size' => 'thumbnail', 'two' => ['medium', 'large'], 'scaled' => 'scaled', 'original' => 'original', 'full' => 'full', 'source' => 'source_original', 'unknown' => 'zz', 'one unknown' => ['medium', 'zz'], 'empty' => '', 'empty list' => [], 'a number' => 5, 'special and size' => ['scaled', 'thumbnail'], 'a list with a number' => ['medium', 5], 'null' => null]),
    'sub sizes checked' => array_map(static fn ($value) => $check($finalizeArgs['sub_sizes'], $value, 'sub_sizes'), ['none' => [], 'one' => [['image_size' => 'thumbnail', 'width' => 150, 'height' => 150, 'file' => 'a.jpg', 'mime_type' => 'image/jpeg', 'filesize' => 10]], 'an unknown size' => [['image_size' => 'zz', 'file' => 'a.jpg']], 'a bad type' => [['image_size' => 'thumbnail', 'file' => 'a.jpg', 'mime_type' => 'text/plain']], 'no size' => [['file' => 'a.jpg']], 'not a list' => 'x', 'a special one' => [['image_size' => 'scaled', 'file' => 'a.jpg']], 'a special one in a list' => [['image_size' => ['scaled', 'thumbnail'], 'file' => 'a.jpg']], 'the second bad' => [['image_size' => 'thumbnail'], ['image_size' => 'zz']], 'a list with a number' => [['image_size' => ['medium', 5]]]]),
]);

$checks = static function () use ($controller, $shown, $side, $plain) {
    return [
        'sideload' => $shown($controller->sideload_item_permissions_check($side($plain, 'x', 'zz-rasl-p.jpg', 'thumbnail'))),
        'a missing one' => $shown($controller->sideload_item_permissions_check($side(999999, 'x', 'zz-rasl-p.jpg', 'thumbnail'))),
    ];
};
$visitor = $checks();
wp_set_current_user($author);
$asAuthor = $checks();
wp_set_current_user(1);
$say('permissions', $masked(['a visitor' => $visitor, 'an author' => $asAuthor, 'an administrator' => $checks()]));

$say('upload folders', array_map(static fn ($path) => $call($controller, 'get_attachment_upload_subdir', $path), [
    'a dated file' => $uploads['basedir'] . '/2026/10/a.jpg',
    'this month' => $uploads['path'] . '/a.jpg',
    'the top' => $uploads['basedir'] . '/a.jpg',
    'deeper' => $uploads['basedir'] . '/a/b/c.jpg',
    'a folder' => $uploads['basedir'] . '/2026/10/',
    'the uploads folder' => $uploads['basedir'],
    'elsewhere' => '/tmp/a.jpg',
    'relative' => '2026/10/a.jpg',
    'empty' => '',
]));

// Names that read like an image's sizes take a number even when free.
file_put_contents($uploads['path'] . '/zz-rasl-uq.jpg', 'x');
file_put_contents($uploads['path'] . '/zz-rasl-uq-300x225.jpg', 'x');
$numbers = [];
$listen = static function ($filename, $ext, $dir, $callback, $alternatives, $number) use (&$numbers) {
    $numbers[] = $number;
    return $filename;
};
add_filter('wp_unique_filename', $listen, 10, 6);
$say('unique names', ['names' => array_map(static fn ($name) => wp_unique_filename($uploads['path'], $name), ['a size' => 'zz-rasl-uq-150x150.jpg', 'scaled' => 'zz-rasl-uq-scaled.jpg', 'rotated' => 'zz-rasl-uq-rotated.jpg', 'a size there' => 'zz-rasl-uq-300x225.jpg', 'the image' => 'zz-rasl-uq.jpg', 'another image' => 'zz-rasl-zz-150x150.jpg', 'a new one' => 'zz-rasl-new.jpg', 'a size in the middle' => 'zz-rasl-150x150-a.jpg', 'upper case' => 'zz-rasl-uq-150X150.jpg', 'text' => 'zz-rasl-uq-1x1.txt', 'edited' => 'zz-rasl-uq-edited.jpg', 'scaled then a number' => 'zz-rasl-uq-scaled-2.jpg']), 'numbers' => $numbers]);
remove_filter('wp_unique_filename', $listen, 10);
unlink($uploads['path'] . '/zz-rasl-uq.jpg');
unlink($uploads['path'] . '/zz-rasl-uq-300x225.jpg');

$say('file names', $masked([
    'nothing sideloaded' => [$call($controller, 'get_sideloaded_file_names', $plain), $call($controller, 'get_sideloaded_file_names', $plain, false)],
    'with sizes' => [$call($controller, 'get_sideloaded_file_names', $sized), $call($controller, 'get_sideloaded_file_names', $sized, false)],
    'a missing one' => $call($controller, 'get_sideloaded_file_names', 999999),
]));

// Sideloading as the administrator (the reference's methods do not check, the engine's routes would).
wp_set_current_user(1);
$steps = [];
foreach (['wp_handle_sideload_prefilter', 'wp_handle_upload', 'update_attached_file', 'add_post_metadata', 'delete_post_metadata', 'wp_generate_attachment_metadata', 'wp_update_attachment_metadata'] as $hook) {
    add_filter($hook, static function ($value = null) use (&$steps) {
        $steps[] = current_filter();
        return $value;
    }, 10, 1);
}
$contexts = [];
add_filter('wp_generate_attachment_metadata', static function ($meta, $id, $context = null) use (&$contexts) {
    $contexts[] = $context;
    return $meta;
}, 10, 3);
$results = [];
$sideloaded = static function (string $label, int $id, string $data, string $name, $size, array $more = []) use ($controller, $side, $shown, &$steps, &$results, $uploads) {
    $response = $controller->sideload_item($side($id, $data, $name, $size, $more));
    $results[$label] = $response instanceof WP_REST_Response ? $response->get_data() : null;
    $heard = $steps;
    $steps = [];
    return ['response' => $shown($response), 'heard' => $heard, 'files' => array_map('basename', glob($uploads['path'] . '/zz-rasl-plain*') ?: [])];
};
$life = [];
$life['a thumbnail'] = $sideloaded('thumbnail', $plain, $jpeg(150, 150), 'zz-rasl-plain-150x150.jpg', 'thumbnail');
$life['the same name again'] = $sideloaded('again', $plain, $jpeg(150, 150), 'zz-rasl-plain-150x150.jpg', 'thumbnail');
$life['another name like a size'] = $sideloaded('another', $plain, $jpeg(150, 150), 'zz-rasl-other-150x150.jpg', 'thumbnail');
$life['a thumbnail a little off'] = $sideloaded('off', $plain, $jpeg(140, 150), 'zz-rasl-plain-off.jpg', 'thumbnail');
$life['medium'] = $sideloaded('medium', $plain, $jpeg(300, 225), 'zz-rasl-plain-300x225.jpg', 'medium');
$life['medium, smaller'] = $sideloaded('smaller', $plain, $jpeg(150, 150), 'zz-rasl-plain-small.jpg', 'medium');
foreach ([1, 2, 3, 5] as $over) {
    $life["medium, {$over} wider"] = $sideloaded("wide{$over}", $plain, $jpeg(300 + $over, 225), "zz-rasl-plain-w{$over}.jpg", 'medium');
}
$life['medium, taller'] = $sideloaded('taller', $plain, $jpeg(300, 320), 'zz-rasl-plain-tall.jpg', 'medium');
$life['two sizes'] = $sideloaded('two', $plain, $jpeg(768, 576), 'zz-rasl-plain-768x576.jpg', ['medium_large', 'large']);
$life['two sizes, one too small'] = $sideloaded('two small', $plain, $jpeg(768, 576), 'zz-rasl-plain-two.jpg', ['medium', 'large']);
$life['an unknown size'] = $sideloaded('unknown', $plain, $jpeg(150, 150), 'zz-rasl-plain-u.jpg', 'zz');
$life['a size and a special one'] = $sideloaded('mixed', $plain, $jpeg(150, 150), 'zz-rasl-plain-mx.jpg', ['scaled', 'thumbnail']);
$life['no data'] = $sideloaded('no data', $plain, '', 'zz-rasl-plain-n.jpg', 'thumbnail');
$life['a missing attachment'] = $sideloaded('missing', 999999, $jpeg(150, 150), 'zz-rasl-plain-x.jpg', 'thumbnail');
$life['a missing attachment, an unknown size'] = $sideloaded('missing unknown', 999999, $jpeg(150, 150), 'zz-rasl-plain-x.jpg', 'zz');
$life['full'] = $sideloaded('full', $plain, $jpeg(1600, 1200), 'zz-rasl-plain-full.jpg', 'full');
$life['the source'] = $sideloaded('source', $plain, $jpeg(1600, 1200), 'zz-rasl-plain-src.jpg', 'source_original');
$life['the scaled image'] = $sideloaded('scaled', $plain, $jpeg(1200, 900), 'zz-rasl-plain-scaled.jpg', 'scaled');
$life['after it'] = [get_post_meta($plain, '_wp_attached_file', true), get_post_meta($plain, '_wp_sideloaded_file'), $call($controller, 'get_sideloaded_file_names', $plain), $call($controller, 'get_sideloaded_file_names', $plain, false)];
$life['the scaled name again'] = $sideloaded('scaled again', $plain, $jpeg(1200, 900), 'zz-rasl-plain-scaled.jpg', 'scaled');
$life['an original'] = $sideloaded('original', $sized, $jpeg(800, 600), 'zz-rasl-sized-orig.jpg', 'original');
$life['a scaled name after it'] = $sideloaded('scaled after original', $sized, $jpeg(700, 525), 'zz-rasl-sized-scaled.jpg', 'scaled');
$life['after them'] = [get_post_meta($sized, '_wp_attached_file', true), get_post_meta($sized, '_wp_sideloaded_file')];
$say('sideloaded', $masked($life));

$provenance = static fn (int $id, array $subs) => $shown($call($controller, 'validate_sub_size_provenance', $id, $subs));
$say('provenance', $masked([
    'nothing' => $provenance($plain, []),
    'sideloaded' => $provenance($plain, [$results['thumbnail'], $results['medium']]),
    'the attached file' => $provenance($plain, [['image_size' => 'thumbnail', 'file' => basename(get_attached_file($plain))]]),
    'a size of the image' => $provenance($sized, [['image_size' => 'thumbnail', 'file' => 'zz-rasl-sized-150x150.jpg']]),
    'another file' => $provenance($plain, [['image_size' => 'thumbnail', 'file' => 'zz-rasl-nope.jpg']]),
    'a path' => $provenance($plain, [['image_size' => 'thumbnail', 'file' => '../' . $results['thumbnail']['file']]]),
    'an original image not sideloaded' => $provenance($plain, [['image_size' => 'scaled', 'file' => $results['scaled']['file'], 'original_image' => 'zz-rasl-nope.jpg']]),
    'no file' => $provenance($plain, [['image_size' => 'thumbnail']]),
    'not a list of objects' => $provenance($plain, ['x', 5]),
]));

$finalize = static function (int $id, $subs) use ($controller, $shown, &$steps, &$contexts) {
    $request = new WP_REST_Request('POST', '/wp/v2/media/' . $id . '/finalize');
    $request->set_param('id', $id);
    $request->set_param('sub_sizes', $subs);
    $response = $controller->finalize_item($request);
    $shownResponse = $shown($response);
    if (is_array($shownResponse) && isset($shownResponse['data']['description'])) {
        $shownResponse['data'] = array_intersect_key($shownResponse['data'], array_flip(['id', 'media_details', 'source_url', 'missing_image_sizes', 'filename', 'filesize']));
    }
    $heard = [$steps, $contexts];
    $steps = [];
    $contexts = [];
    return ['response' => $shownResponse, 'heard' => $heard, 'metadata' => wp_get_attachment_metadata($id), 'sideloaded' => get_post_meta($id, '_wp_sideloaded_file'), 'attached' => get_post_meta($id, '_wp_attached_file', true)];
};
$done = [];
$done['a file not sideloaded'] = $finalize($plain, [['image_size' => 'thumbnail', 'file' => 'zz-rasl-nope.jpg', 'width' => 150, 'height' => 150]]);
$done['a missing attachment'] = $finalize(999999, []);
$done['finalized'] = $finalize($plain, [$results['thumbnail'], $results['medium'], $results['two'], $results['full'], $results['source'], $results['scaled']]);
$done['finalized again, nothing'] = $finalize($plain, []);
$done['an original finalized'] = $finalize($sized, [$results['original'], $results['scaled after original']]);
$done['names after'] = [$call($controller, 'get_sideloaded_file_names', $plain), $call($controller, 'get_sideloaded_file_names', $sized, false)];
$done['missing sizes'] = [array_keys(wp_get_missing_image_subsizes($plain)), array_keys(wp_get_missing_image_subsizes($sized))];
$say('finalized', $masked($done));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
