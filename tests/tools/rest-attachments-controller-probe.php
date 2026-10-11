<?php
/**
 * The attachments controller class (probe rest-attachments-controller):
 * its routes (the client-side processing ones over HTTPS); its schema,
 * collection, endpoint and edit arguments; the media types; file names
 * read from a Content-Disposition header; the list query; the permission
 * checks for a visitor, a contributor, an author and an administrator;
 * attachments read, listed and prepared for the database; uploads from a
 * body and from a form; a featured image on audio; and an image uploaded,
 * updated, given its sizes, edited and deleted through the class.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// The reference would pick Imagick, which the engine still owes; both write images with GD here.
add_filter('wp_image_editors', static fn () => ['WP_Image_Editor_GD'], 99);
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$named = static function ($value) use (&$named) {
    if ($value instanceof Closure) {
        return '@closure';
    }
    if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
        return '@method:' . get_class($value[0]) . '::' . $value[1];
    }
    return is_array($value) ? array_map($named, $value) : $value;
};
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links(), 'headers' => $response->get_headers()] : $response);
$request = static function (string $method, string $route, array $params = [], array $defaults = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $request->set_default_params($defaults);
    return $request;
};
$jpeg = static function (int $width, int $height): string {
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 90, 160));
    imagefilledrectangle($image, 0, 0, intdiv($width, 3), intdiv($height, 2), imagecolorallocate($image, 220, 200, 40));
    ob_start();
    imagejpeg($image, null, 80);
    return (string) ob_get_clean();
};
$body = static function (string $method, string $route, string $data, array $headers, array $params = []) use ($request) {
    $sent = $request($method, $route, $params);
    $sent->set_body($data);
    foreach ($headers as $name => $value) {
        $sent->set_header($name, $value);
    }
    return $sent;
};
wp_set_current_user(0);
rest_get_server();

// The posts, attachments, files and users made here go on the way out.
$uploads = wp_get_upload_dir();
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made, $uploads): void {
    wp_set_current_user(1);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    foreach (glob($uploads['path'] . '/zz-ratt*') ?: [] as $file) {
        if (is_file($file) && str_starts_with(basename($file), 'zz-ratt')) {
            unlink($file);
        }
    }
});
$contributor = wp_insert_user(['user_login' => 'zz_ratt_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-ratt-c@example.com', 'role' => 'contributor']);
$author = wp_insert_user(['user_login' => 'zz_ratt_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-ratt-a@example.com', 'role' => 'author']);
$post = wp_insert_post(['post_title' => 'ZZ ratt post', 'post_status' => 'publish', 'post_author' => 1]);
$saved = wp_upload_bits('zz-ratt-image.jpg', null, $jpeg(800, 600));
$image = empty($saved['error']) ? wp_insert_attachment(['post_title' => 'ZZ ratt image', 'post_excerpt' => 'A caption', 'post_content' => 'A description', 'post_mime_type' => 'image/jpeg', 'post_author' => 1], $saved['file'], $post) : 0;
$audio = wp_insert_attachment(['post_title' => 'ZZ ratt audio', 'post_mime_type' => 'audio/mpeg', 'post_author' => 1], $uploads['path'] . '/zz-ratt-audio.mp3', $post);
foreach ([[$contributor, 'users'], [$author, 'users'], [$post, 'posts'], [$image, 'posts'], [$audio, 'posts']] as [$id, $kind]) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[$kind][] = $id;
}
wp_update_attachment_metadata($image, wp_generate_attachment_metadata($image, $saved['file']));
update_post_meta($image, '_wp_attachment_image_alt', 'An alt');
wp_update_attachment_metadata($audio, ['filesize' => 4321, 'mime_type' => 'audio/mpeg', 'length' => 3]);
$ids = [$contributor, $author, $post, $image, $audio];
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
$reflection = new ReflectionClass($controller);
$say('the controller', [
    'base' => Closure::bind(fn () => [$this->namespace, $this->rest_base, $this->post_type, $this->allow_batch], $controller, WP_REST_Attachments_Controller::class)(),
    'constants' => $reflection->getConstants(),
]);

// Routes on a server of their own: without HTTPS, then with it (where client-side processing adds sideload and finalize).
$routes = static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Attachments_Controller('attachment'))->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/media')) {
            continue;
        }
        foreach ($endpoints as $endpoint) {
            $found[$route][] = ['methods' => array_keys($endpoint['methods']), 'callback' => $named($endpoint['callback']), 'permission' => $named($endpoint['permission_callback'] ?? null), 'args' => array_keys($endpoint['args']), 'required' => array_keys(array_filter($endpoint['args'], static fn ($arg) => !empty($arg['required'])))];
        }
    }
    $GLOBALS['wp_rest_server'] = $server;
    return $found;
};
$say('routes', ['plain' => $routes(), 'over https' => (static function () use ($routes) {
    $_SERVER['HTTPS'] = 'on';
    $found = $routes();
    unset($_SERVER['HTTPS']);
    return $found;
})()]);

$say('schema', $named($controller->get_item_schema()));
$say('collection parameters', $named($controller->get_collection_params()));
$createArgs = $controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE);
$say('a url checked', array_map(static fn ($url) => $shown(call_user_func($createArgs['url']['validate_callback'], $url, $request('POST', '/wp/v2/media'), 'url')), ['an image' => 'https://example.com/a.jpg', 'text' => 'https://example.com/a.txt', 'no extension' => 'https://example.com/a', 'a query' => 'https://example.com/a.png?x=1', 'not a url' => 'not a url', 'ftp' => 'ftp://example.com/a.jpg', 'local' => 'http://127.0.0.1/a.jpg', 'empty' => '', 'upper case' => 'https://example.com/A.JPG', 'webp' => 'https://example.com/a.webp', 'a pdf' => 'https://example.com/a.pdf']));
$say('endpoint arguments', ['create' => $named($createArgs), 'edit' => array_keys($controller->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)), 'default' => array_keys($controller->get_endpoint_args_for_item_schema())]);
$say('edit arguments', $named($call($controller, 'get_edit_media_item_args')));
$zzMimes = static fn (array $mimes) => $mimes + ['zzx' => 'application/x-zz', 'zzi' => 'image/x-zz'];
$say('media types', [
    'plain' => $call($controller, 'get_media_types'),
    'a plugin adds two' => (static function () use ($call, $controller, $zzMimes) {
        add_filter('upload_mimes', $zzMimes);
        $types = $call($controller, 'get_media_types');
        remove_filter('upload_mimes', $zzMimes);
        return $types;
    })(),
]);
$say('file names from a disposition', array_map(static fn ($header) => WP_REST_Attachments_Controller::get_filename_from_disposition($header), [
    'none' => [],
    'quoted' => ['attachment; filename="a.jpg"'],
    'bare' => ['attachment; filename=b.png'],
    'extended' => ["attachment; filename*=UTF-8''c%20d.jpg"],
    'inline' => ['inline'],
    'form data' => ['form-data; name="file"; filename="e.gif"'],
    'a path' => ['attachment; filename="../x/f.jpg"'],
    'empty' => ['attachment; filename=""'],
    'no space' => ['attachment;filename="g.jpg"'],
    'two headers' => ['inline', 'attachment; filename="h.jpg"'],
    'single quotes' => ["attachment; filename='i.jpg'"],
    'upper case' => ['ATTACHMENT; FILENAME="J.JPG"'],
    'spaces' => ['attachment; filename = "k l.jpg" '],
    'two file names' => ['attachment; filename="m.jpg"; filename="n.jpg"'],
    'no type' => ['filename="o.jpg"'],
    'empty, unquoted' => ['attachment; filename='],
    'a semicolon inside quotes' => ['attachment; filename="p;q.jpg"'],
    'a name before it' => ['attachment; name="r.jpg"'],
    'two headers, two names' => ['attachment; filename="s.jpg"', 'attachment; filename="t.jpg"'],
]));
// Client-side processing is on in a secure context: HTTPS, or a localhost host.
$flag = static function (array $server) {
    $saved = $_SERVER;
    unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT']);
    $_SERVER = $server + $_SERVER;
    $on = wp_is_client_side_media_processing_enabled();
    $_SERVER = $saved;
    return $on;
};
$heardDefault = null;
$listen = static function ($value) use (&$heardDefault) {
    $heardDefault = $value;
    return $value;
};
add_filter('wp_client_side_media_processing_enabled', $listen);
$say('client-side processing', [
    'by request' => array_map($flag, ['nothing' => [], 'https' => ['HTTPS' => 'on'], 'https off' => ['HTTPS' => 'off'], 'https 1' => ['HTTPS' => '1'], 'port 443' => ['SERVER_PORT' => '443'], 'localhost' => ['HTTP_HOST' => 'localhost'], 'localhost with a port' => ['HTTP_HOST' => 'localhost:8080'], 'under localhost' => ['HTTP_HOST' => 'a.b.localhost'], 'upper case' => ['HTTP_HOST' => 'Minn.Localhost'], 'loopback' => ['HTTP_HOST' => '127.0.0.1'], 'ipv6 loopback' => ['HTTP_HOST' => '[::1]'], 'a name ending in localhost' => ['HTTP_HOST' => 'notlocalhost'], 'localhost first' => ['HTTP_HOST' => 'localhost.example.com'], 'elsewhere' => ['HTTP_HOST' => 'example.com']]),
    'the filter hears' => [$flag(['HTTPS' => 'on']) ? $heardDefault : null, $flag(['HTTP_HOST' => 'example.com']) === false ? $heardDefault : null],
]);
remove_filter('wp_client_side_media_processing_enabled', $listen);
$say('upload size', [$call($controller, 'check_upload_size', ['size' => 10]), $call($controller, 'check_upload_size', ['size' => PHP_INT_MAX])]);
$say('the query', $masked([
    'plain' => $call($controller, 'prepare_items_query', ['posts_per_page' => 5], $request('GET', '/wp/v2/media')),
    'images' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['media_type' => 'image'])),
    'a mime type' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['mime_type' => 'image/jpeg'])),
    'both' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['media_type' => 'audio', 'mime_type' => 'image/jpeg'])),
    'an unknown type' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['media_type' => 'zz'])),
    'a status' => $call($controller, 'prepare_items_query', ['post_status' => ['inherit', 'private']], $request('GET', '/wp/v2/media')),
    'a parent' => $call($controller, 'prepare_items_query', ['post_parent__in' => [$post]], $request('GET', '/wp/v2/media', ['parent' => [$post]])),
    'media types' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['media_type' => ['image', 'audio'], 'mime_type' => ['image/png']])),
    'mime types' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['mime_type' => ['image/png', 'audio/mpeg']])),
    'an unknown media type' => $call($controller, 'prepare_items_query', [], $request('GET', '/wp/v2/media', ['media_type' => ['zz']])),
    'supports' => [get_all_post_type_supports('attachment'), get_all_post_type_supports('attachment:audio'), post_type_supports('attachment:video', 'thumbnail'), post_type_supports('attachment:image', 'thumbnail')],
]));

$authorPost = 0;
$checks = static function () use ($controller, $call, $shown, $request, $image, $post) {
    $one = ['id' => $image];
    return [
        'list' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/media'))),
        'list, edit' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/media', ['context' => 'edit']))),
        'an image' => $shown($controller->get_item_permissions_check($request('GET', '/wp/v2/media/' . $image, $one))),
        'create' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/media'))),
        'create on a post' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/media', ['post' => $post]))),
        'create on a missing post' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/media', ['post' => 999999]))),
        'create with an id' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/media', ['id' => $image]))),
        'update' => $shown($controller->update_item_permissions_check($request('PUT', '/wp/v2/media/' . $image, $one))),
        'delete' => $shown($controller->delete_item_permissions_check($request('DELETE', '/wp/v2/media/' . $image, $one))),
        'post-process' => $shown($controller->post_process_item_permissions_check($request('POST', '/wp/v2/media/' . $image . '/post-process', $one + ['action' => 'create-image-subsizes']))),
        'edit' => $shown($controller->edit_media_item_permissions_check($request('POST', '/wp/v2/media/' . $image . '/edit', $one))),
        'edit a missing one' => $shown($controller->edit_media_item_permissions_check($request('POST', '/wp/v2/media/999999/edit', ['id' => 999999]))),
    ];
};
$visitor = $checks();
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user($author);
$asAuthor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'a contributor' => $asContributor, 'an author' => $asAuthor, 'an administrator' => $administrator]));

// Reading, listing and writing as the administrator (the reference's methods do not check, the engine's routes do).
wp_set_current_user(1);
$read = static fn (array $params, int $id = 0) => $shown($controller->get_item($request('GET', '/wp/v2/media/' . ($id ?: $image), ['id' => $id ?: $image] + $params)));
$say('reading', $masked([
    'an image' => $read([]),
    'an image, edit' => $read(['context' => 'edit']),
    'an image, embedded' => $read(['context' => 'embed']),
    'an image, fields' => $read(['_fields' => 'id,alt_text,media_details,source_url']),
    'audio' => $read([], $audio),
    'audio, edit' => $read(['context' => 'edit'], $audio),
    'links' => $call($controller, 'prepare_links', get_post($image)),
    'audio links' => $call($controller, 'prepare_links', get_post($audio)),
    'prepared' => $shown($controller->prepare_item_for_response(get_post($image), $request('GET', '/wp/v2/media'))),
    'file names' => [$call($controller, 'get_attachment_filename', $image), $call($controller, 'get_attachment_filename', $audio), $call($controller, 'get_attachment_filename', 999999)],
    'file sizes' => [$call($controller, 'get_attachment_filesize', $image), $call($controller, 'get_attachment_filesize', $audio), $call($controller, 'get_attachment_filesize', 999999)],
]));

$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null);
$say('listing', $masked([
    'images' => $shown($controller->get_items($request('GET', '/wp/v2/media', ['media_type' => ['image'], 'include' => [$image, $audio], 'page' => 1], $defaults))),
    'a mime type' => $shown($controller->get_items($request('GET', '/wp/v2/media', ['mime_type' => ['audio/mpeg'], 'include' => [$image, $audio], 'page' => 1], $defaults))),
    'a parent' => $shown($controller->get_items($request('GET', '/wp/v2/media', ['parent' => [$post], 'page' => 1, 'orderby' => 'id', 'order' => 'asc', '_fields' => 'id,title'], $defaults))),
]));

$prepared = static fn (array $params, string $method = 'POST') => $try(static function () use ($params, $method, $controller, $call, $request, $image) {
    $result = $call($controller, 'prepare_item_for_database', $request($method, '/wp/v2/media' . ($method === 'POST' ? '' : '/' . $image), $params + ($method === 'POST' ? [] : ['id' => $image])));
    return is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message()] : (array) $result;
});
$say('prepared for the database', $masked([
    'everything' => $prepared(['title' => 'T', 'caption' => 'C', 'description' => 'D', 'alt_text' => 'A', 'post' => $post, 'slug' => 'zz-s', 'status' => 'inherit']),
    'raw objects' => $prepared(['caption' => ['raw' => 'C raw'], 'description' => ['raw' => 'D raw'], 'title' => ['raw' => 'T raw']]),
    'an update' => $prepared(['title' => 'T2', 'post' => 0], 'PUT'),
    'nothing' => $prepared([]),
    'a missing one' => $shown($call($controller, 'prepare_item_for_database', $request('PUT', '/wp/v2/media/999999', ['id' => 999999]))),
]));

$file = static fn ($result) => is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : (is_array($result) ? array_map(static fn ($value) => basename((string) $value), array_intersect_key($result, ['file' => true, 'url' => true])) + $result : $result);
$uploaded = $call($controller, 'upload_from_data', $jpeg(40, 30), ['content_type' => ['image/jpeg'], 'content_disposition' => ['attachment; filename="zz-ratt-data.jpg"']]);
$say('uploads', $masked([
    'from data' => $file($uploaded),
    'no data' => $file($call($controller, 'upload_from_data', '', ['content_type' => ['image/jpeg']])),
    'no type' => $file($call($controller, 'upload_from_data', 'x', [])),
    'no disposition' => $file($call($controller, 'upload_from_data', 'x', ['content_type' => ['image/jpeg']])),
    'no file name' => $file($call($controller, 'upload_from_data', 'x', ['content_type' => ['image/jpeg'], 'content_disposition' => ['inline']])),
    'a checksum that differs' => $file($call($controller, 'upload_from_data', 'x', ['content_type' => ['image/jpeg'], 'content_disposition' => ['attachment; filename="zz-ratt-md5.jpg"'], 'content_md5' => [md5('y')]])),
    'a type not allowed' => $file($call($controller, 'upload_from_data', '<?php echo 1;', ['content_type' => ['application/x-php'], 'content_disposition' => ['attachment; filename="zz-ratt-bad.php"']])),
    'from no files' => $file($call($controller, 'upload_from_file', [], [])),
    'from a file not uploaded' => $file($call($controller, 'upload_from_file', ['file' => ['name' => 'zz-ratt-form.jpg', 'type' => 'image/jpeg', 'tmp_name' => __FILE__, 'error' => 0, 'size' => 10]], [])),
    'from a file that failed' => $file($call($controller, 'upload_from_file', ['file' => ['name' => 'zz-ratt-form.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]], [])),
]));
if (is_array($uploaded) && isset($uploaded['file']) && is_file($uploaded['file'])) {
    unlink($uploaded['file']);
}

$say('a featured image', $masked([
    'on audio' => [$shown($call($controller, 'handle_featured_media', $image, $audio)), get_post_thumbnail_id($audio)],
    'again' => $shown($call($controller, 'handle_featured_media', $image, $audio)),
    'removed' => [$shown($call($controller, 'handle_featured_media', 0, $audio)), get_post_thumbnail_id($audio)],
    'removed again' => $shown($call($controller, 'handle_featured_media', 0, $audio)),
    'on an image' => [$shown($call($controller, 'handle_featured_media', $audio, $image)), get_post_thumbnail_id($image)],
    'a missing one' => $shown($call($controller, 'handle_featured_media', 999999, $audio)),
]));

// The save hooks each step runs, in order.
$steps = [];
$watch = ['rest_pre_insert_attachment', 'wp_handle_sideload_prefilter', 'wp_handle_upload_prefilter', 'wp_handle_upload', 'add_attachment', 'edit_attachment', 'attachment_updated', 'rest_insert_attachment', 'rest_after_insert_attachment', 'wp_after_insert_post', 'wp_generate_attachment_metadata', 'wp_update_attachment_metadata', 'intermediate_image_sizes_advanced', 'wp_edited_image_metadata', 'image_editor_save_pre', 'wp_save_image_editor_file', 'delete_attachment', 'rest_delete_attachment'];
foreach ($watch as $hook) {
    add_filter($hook, static function ($value = null) use (&$steps) {
        $steps[] = current_filter();
        return $value;
    }, 10, 1);
}
$heard = static function () use (&$steps): array {
    $heard = $steps;
    $steps = [];
    return $heard;
};
$life = [];
$created = $controller->create_item($body('POST', '/wp/v2/media', $jpeg(1600, 1200), ['content-type' => 'image/jpeg', 'content-disposition' => 'attachment; filename="zz-ratt-life.jpg"'], ['title' => 'ZZ ratt life', 'caption' => 'Life caption', 'alt_text' => 'Life alt', 'post' => $post, 'generate_sub_sizes' => false]));
$createdId = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if ($createdId > 1) {
    $made['posts'][] = $createdId;
    $ids[] = $createdId;
}
$life['created'] = $shown($created);
$life['created, heard'] = $heard();
$life['no data'] = $shown($controller->create_item($request('POST', '/wp/v2/media')));
$life['a bad url'] = $shown($controller->create_item($request('POST', '/wp/v2/media', ['url' => 'not a url'])));
$life['a url to text'] = $shown($controller->create_item($request('POST', '/wp/v2/media', ['url' => 'https://example.com/zz.txt'])));
$life['a url not allowed'] = $shown($controller->create_item($request('POST', '/wp/v2/media', ['url' => 'http://127.0.0.1/zz-ratt.jpg'])));
// Which names pass the type check: those that do go on to a fetch the local address refuses.
$life['urls by type'] = array_map(static fn ($url) => $shown($call($controller, 'create_item_from_url', $request('POST', '/wp/v2/media', ['url' => $url]))), ['upper case' => 'http://127.0.0.1/a.JPG', 'a pdf' => 'http://127.0.0.1/a.pdf', 'no extension' => 'http://127.0.0.1/a', 'webp' => 'http://127.0.0.1/a.webp', 'svg' => 'http://127.0.0.1/a.svg', 'a query' => 'http://127.0.0.1/a.png?x=1', 'a fragment' => 'http://127.0.0.1/a.gif#x', 'avif' => 'http://127.0.0.1/a.avif', 'heic' => 'http://127.0.0.1/a.heic', 'mp3' => 'http://127.0.0.1/a.mp3', 'a path' => 'http://127.0.0.1/a.jpg/b', 'empty' => '']);
$life['updated'] = $shown($controller->update_item($request('PUT', '/wp/v2/media/' . $createdId, ['id' => $createdId, 'title' => 'ZZ ratt life renamed', 'alt_text' => 'New alt', 'description' => 'Life description', 'post' => 0])));
$life['updated, heard'] = $heard();
$life['an update of a missing one'] = $shown($controller->update_item($request('PUT', '/wp/v2/media/999999', ['id' => 999999, 'title' => 'x'])));
$life['sizes made'] = $shown($controller->post_process_item($request('POST', '/wp/v2/media/' . $createdId . '/post-process', ['id' => $createdId, 'action' => 'create-image-subsizes'])));
$life['sizes made, heard'] = $heard();
$life['sizes on disk'] = array_map('basename', glob($uploads['path'] . '/zz-ratt-life*') ?: []);
$editedRow = static function ($response) {
    $id = $response instanceof WP_REST_Response ? (int) ($response->get_data()['id'] ?? 0) : 0;
    if ($id <= 1) {
        return null;
    }
    $row = get_post($id);
    $meta = wp_get_attachment_metadata($id);
    // Which corners the yellow block landed in shows the flip and the direction of a rotation.
    $pixels = null;
    $path = get_attached_file($id);
    $gd = $path && is_file($path) ? imagecreatefromjpeg($path) : false;
    if ($gd) {
        $w = imagesx($gd);
        $h = imagesy($gd);
        $pixels = array_map(static function (array $at) use ($gd) {
            $rgb = imagecolorat($gd, $at[0], $at[1]);
            return (($rgb >> 16) & 255) > 150 && (($rgb >> 8) & 255) > 150 ? 'yellow' : 'blue';
        }, ['top left' => [3, 3], 'top right' => [$w - 4, 3], 'bottom left' => [3, $h - 4], 'bottom right' => [$w - 4, $h - 4]]);
    }
    return ['pixels' => $pixels, 'row' => [$row->post_title, $row->post_excerpt, $row->post_content, $row->post_parent, $row->post_author, $row->post_name, $row->post_mime_type], 'meta keys' => array_keys(get_post_meta($id)), 'alt' => get_post_meta($id, '_wp_attachment_image_alt', true), 'metadata' => is_array($meta) ? array_intersect_key($meta, ['width' => true, 'height' => true, 'file' => true, 'parent_image' => true]) + ['sizes' => array_keys((array) ($meta['sizes'] ?? []))] : $meta];
};
$edit = static function (int $id, array $params) use ($controller, $request, $shown, $editedRow, $heard, &$made, &$ids) {
    $response = $controller->edit_media_item($request('POST', '/wp/v2/media/' . $id . '/edit', ['id' => $id] + $params));
    $newId = $response instanceof WP_REST_Response ? (int) ($response->get_data()['id'] ?? 0) : 0;
    if ($newId > 1) {
        $made['posts'][] = $newId;
        $ids[] = $newId;
    }
    return ['response' => $shown($response), 'saved' => $editedRow($response), 'heard' => $heard()];
};
$imageUrl = wp_get_attachment_url($image);
$life['edited'] = $edit($image, ['src' => $imageUrl, 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]], ['type' => 'crop', 'args' => ['left' => 10, 'top' => 10, 'width' => 50, 'height' => 50]]]]);
$life['edited, given fields'] = $edit($image, ['src' => $imageUrl, 'modifiers' => [['type' => 'flip', 'args' => ['flip' => ['horizontal' => true, 'vertical' => false]]]], 'title' => 'ZZ ratt edited title', 'caption' => 'Edited caption', 'description' => 'Edited description', 'alt_text' => 'Edited alt', 'post' => 0]);
$life['edited from a size'] = $edit($image, ['src' => wp_get_attachment_image_url($image, 'medium'), 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 180]]]]);
$life['a crop that rounds'] = $edit($image, ['src' => $imageUrl, 'modifiers' => [['type' => 'crop', 'args' => ['left' => 0.1, 'top' => 0, 'width' => 50.1, 'height' => 50.1]]]]);
$life['a rotation the other way'] = $edit($image, ['src' => $imageUrl, 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 270]]]]);
$life['an edit with nothing to do'] = $edit($image, ['src' => $imageUrl]);
$life['an edit of another file with nothing to do'] = $edit($image, ['src' => 'https://example.com/zz.jpg']);
$life['an edit of another file'] = $edit($image, ['src' => 'https://example.com/zz.jpg', 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]]]]);
$life['an edit of audio'] = $edit($audio, ['src' => wp_get_attachment_url($audio), 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]]]]);
$life['an edit, the old way'] = $edit($createdId, ['src' => wp_get_attachment_url($createdId), 'rotation' => 180, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 50]);
$life['an edit, the old way, a rotation alone'] = $edit($createdId, ['src' => wp_get_attachment_url($createdId), 'rotation' => 90]);
// The sizes brought up to date directly: an attachment with none, one with all, audio, and one with no file.
$bare = wp_insert_attachment(['post_title' => 'ZZ ratt bare', 'post_mime_type' => 'image/jpeg'], (string) wp_upload_bits('zz-ratt-bare.jpg', null, $jpeg(500, 400))['file']);
$fileless = wp_insert_attachment(['post_title' => 'ZZ ratt fileless', 'post_mime_type' => 'image/jpeg']);
foreach ([$bare, $fileless] as $id) {
    if (is_int($id) && $id > 1) {
        $made['posts'][] = $id;
        $ids[] = $id;
    }
}
$sizesOf = static fn ($meta) => is_array($meta) ? ['width' => $meta['width'] ?? null, 'height' => $meta['height'] ?? null, 'sizes' => array_keys((array) ($meta['sizes'] ?? []))] : $meta;
$life['sizes updated'] = [
    'none yet' => [$sizesOf($shown(wp_update_image_subsizes($bare))), $heard()],
    'all there' => [$sizesOf($shown(wp_update_image_subsizes($image))), $heard()],
    'audio' => [$shown(wp_update_image_subsizes($audio)), $heard()],
    'no file' => [$shown(wp_update_image_subsizes($fileless)), $heard()],
];
$life['deleted, not forced'] = $shown($controller->delete_item($request('DELETE', '/wp/v2/media/' . $createdId, ['id' => $createdId])));
$life['deleted'] = $shown($controller->delete_item($request('DELETE', '/wp/v2/media/' . $createdId, ['id' => $createdId, 'force' => true])));
$life['deleted, heard'] = $heard();
$life['gone'] = [get_post($createdId) === null, array_map('basename', glob($uploads['path'] . '/zz-ratt-life.*') ?: [])];
$inserted = $call($controller, 'insert_attachment', $body('POST', '/wp/v2/media', $jpeg(20, 20), ['content-type' => 'image/jpeg', 'content-disposition' => 'attachment; filename="zz-ratt-insert.jpg"'], ['title' => 'ZZ ratt inserted']));
$insertedId = is_array($inserted) ? (int) ($inserted['attachment_id'] ?? 0) : 0;
if ($insertedId > 1) {
    $made['posts'][] = $insertedId;
    $ids[] = $insertedId;
}
$life['inserted'] = is_array($inserted) ? array_map(static fn ($value) => is_string($value) ? basename($value) : $value, $inserted) : $shown($inserted);
$life['inserted, heard'] = $heard();
$life['inserted row'] = $insertedId > 1 ? [get_post($insertedId)->post_title, get_post($insertedId)->post_mime_type, get_post_meta($insertedId, '_wp_attachment_metadata', true) !== ''] : null;
wp_set_current_user(0);
$say('an image through its life', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
