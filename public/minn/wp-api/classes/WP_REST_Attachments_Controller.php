<?php

use Minn\Media\Upload;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Posts_Controller.php';

/**
 * The media endpoints as a class (probe rest-attachments-controller). The
 * engine answers wp/v2/media itself; this is the class plugins construct,
 * extend or call. Uploads and edits of an attachment's fields go through
 * the engine's media routes, so the class saves exactly as the REST API
 * does; the image edit, the sizes made afterwards and the upload helpers
 * are WordPress-shaped here. The client-side processing endpoints
 * (sideload and finalize) are still owed.
 */
class WP_REST_Attachments_Controller extends WP_REST_Posts_Controller
{
    const IMAGE_SIZE_SOURCE_ORIGINAL = 'source_original';
    const META_KEY_SOURCE_IMAGE = 'source_image';
    const META_KEY_SIDELOAD_FILE_NAME = '_wp_sideloaded_file';
    protected $allow_batch = false;

    public function register_routes()
    {
        // The posts routes, then post-process and edit; sideload and finalize only where images are processed in the browser.
        parent::register_routes();
        $id = ['description' => __('Unique identifier for the attachment.'), 'type' => 'integer'];
        $route = '/' . $this->rest_base . '/(?P<id>[\d]+)';
        register_rest_route($this->namespace, $route . '/post-process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'post_process_item'],
            'permission_callback' => [$this, 'post_process_item_permissions_check'],
            'args' => ['id' => $id, 'action' => ['type' => 'string', 'enum' => ['create-image-subsizes'], 'required' => true]],
        ]);
        register_rest_route($this->namespace, $route . '/edit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'edit_media_item'],
            'permission_callback' => [$this, 'edit_media_item_permissions_check'],
            'args' => $this->get_edit_media_item_args(),
        ]);
        if (!wp_is_client_side_media_processing_enabled()) {
            return;
        }
        $sizes = ['type' => ['string', 'array'], 'items' => ['type' => 'string', 'minLength' => 1], 'minItems' => 1, 'minLength' => 1];
        register_rest_route($this->namespace, $route . '/sideload', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'sideload_item'],
            'permission_callback' => [$this, 'sideload_item_permissions_check'],
            'args' => [
                'id' => $id,
                'image_size' => ['description' => __('Image size. Can be a single size name or an array of size names to register the same file under multiple sizes.')] + $sizes + ['required' => true, 'validate_callback' => static fn ($value, $request, $param) => rest_validate_request_arg($value, $request, $param)],
                'convert_format' => ['type' => 'boolean', 'default' => true, 'description' => __('Whether to convert image formats.')],
            ],
        ]);
        $sub_size = ['image_size' => ['description' => __('Size name, or an array of size names when a single file is registered under multiple sizes with matching dimensions.')] + $sizes + ['required' => true], 'width' => ['type' => 'integer', 'minimum' => 1], 'height' => ['type' => 'integer', 'minimum' => 1], 'file' => ['type' => 'string', 'minLength' => 1], 'mime_type' => ['type' => 'string', 'pattern' => '^image/.*'], 'filesize' => ['type' => 'integer', 'minimum' => 1], 'original_image' => ['type' => 'string', 'minLength' => 1]];
        register_rest_route($this->namespace, $route . '/finalize', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'finalize_item'],
            'permission_callback' => [$this, 'edit_media_item_permissions_check'],
            'args' => [
                'id' => $id,
                'sub_sizes' => ['description' => __('Array of sub-size metadata collected from sideload responses.'), 'type' => 'array', 'default' => [], 'maxItems' => 100, 'validate_callback' => static fn ($value, $request, $param) => rest_validate_request_arg($value, $request, $param), 'items' => ['type' => 'object', 'properties' => $sub_size]],
            ],
        ]);
    }

    public function get_endpoint_args_for_item_schema($method = WP_REST_Server::CREATABLE)
    {
        // Creating also takes whether to make the sizes, whether to convert the type, and a URL to fetch instead of a file.
        $args = parent::get_endpoint_args_for_item_schema($method);
        if ($method !== WP_REST_Server::CREATABLE) {
            return $args;
        }
        foreach (SchemaData::of('media-create-args') as $name => $arg) {
            $args[$name] = $arg;
        }
        $args['url']['validate_callback'] = static fn ($url) => wp_http_validate_url($url) ? true : new WP_Error('rest_invalid_url', __('Invalid URL. Provide a valid, publicly reachable HTTP or HTTPS image URL.'), ['status' => 400]);
        return $args;
    }

    protected function prepare_items_query($prepared_args = [], $request = NULL)
    {
        // Attachments are listed in their own status unless the request names one; a list of media types stands for all their MIME types, else a list of MIME types for itself.
        $query_args = parent::prepare_items_query($prepared_args, $request);
        if (empty($query_args['post_status'])) {
            $query_args['post_status'] = 'inherit';
        }
        $media_types = $this->get_media_types();
        $types = !empty($request['media_type']) && is_array($request['media_type']) ? array_values($request['media_type']) : [];
        $mime_types = array_merge([], ...array_map(static fn ($type) => $media_types[$type] ?? [], $types));
        if ($mime_types !== []) {
            $query_args['post_mime_type'] = $mime_types;
        } elseif (!empty($request['mime_type']) && is_array($request['mime_type'])) {
            $query_args['post_mime_type'] = $request['mime_type'];
        }
        return $query_args;
    }

    public function create_item_permissions_check($request)
    {
        // The posts checks, uploading at all, then attaching to the post named.
        $checked = parent::create_item_permissions_check($request);
        if ($checked !== true) {
            return $checked;
        }
        if (!current_user_can('upload_files')) {
            return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to upload media on this site.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request['post']) && !current_user_can('edit_post', (int) $request['post'])) {
            return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to upload media to this post.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item($request)
    {
        // A URL is fetched first; a file in the body or the form goes through the engine's upload route (its checks, the file, the attachment, its fields and actions, then its sizes).
        if (!empty($request['id'])) {
            return new WP_Error('rest_post_exists', __('Cannot create existing post.'), ['status' => 400]);
        }
        if (!empty($request['url'])) {
            return $this->create_item_from_url($request);
        }
        $created = $this->minn_engine('POST', '/' . $this->namespace . '/' . $this->rest_base, $request);
        if (is_wp_error($created)) {
            return $created;
        }
        $attachment = get_post((int) ($created['id'] ?? 0));
        $request->set_param('context', 'edit');
        $response = rest_ensure_response($this->prepare_item_for_response($attachment, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(rest_get_route_for_post($attachment)));
        return $response;
    }

    protected function create_item_from_url($request)
    {
        // The URL must name an image type the site takes; the file fetched is uploaded as a body naming it would be.
        $filename = wp_basename((string) parse_url((string) $request['url'], PHP_URL_PATH));
        if ($filename === '') {
            return new WP_Error('rest_invalid_url', __('Could not determine a filename from the provided URL.'), ['status' => 400]);
        }
        $type = wp_check_filetype($filename)['type'];
        if (!$type || !str_starts_with($type, 'image/')) {
            return new WP_Error('rest_invalid_url', __('The provided URL does not point to a supported image file.'), ['status' => 400]);
        }
        $downloaded = download_url((string) $request['url']);
        if (is_wp_error($downloaded)) {
            return $downloaded;
        }
        $upload = clone $request;
        $upload->set_param('url', null);
        $upload->set_body((string) file_get_contents($downloaded));
        $upload->set_header('content-type', $type);
        $upload->set_header('content-disposition', 'attachment; filename="' . $filename . '"');
        @unlink($downloaded);
        return $this->create_item($upload);
    }

    protected function insert_attachment($request)
    {
        // The file from the form or the body, then its attachment without sizes: the photo's own title and caption unless the request names them, rest_pre_insert_attachment, the file's name when no title is left, wp_insert_attachment, rest_insert_attachment.
        $files = $request->get_file_params();
        $file = !empty($files) ? $this->upload_from_file($files, $request->get_headers()) : $this->upload_from_data($request->get_body(), $request->get_headers());
        if (is_wp_error($file)) {
            return $file;
        }
        $image_meta = wp_read_image_metadata($file['file']);
        if (is_array($image_meta) && empty($request['title']) && trim((string) $image_meta['title']) !== '' && !is_numeric(sanitize_title((string) $image_meta['title']))) {
            $request['title'] = $image_meta['title'];
        }
        if (is_array($image_meta) && empty($request['caption']) && trim((string) $image_meta['caption']) !== '') {
            $request['caption'] = $image_meta['caption'];
        }
        $attachment = $this->prepare_item_for_database($request);
        if (is_wp_error($attachment)) {
            return $attachment;
        }
        $attachment->post_mime_type = $file['type'];
        $attachment->guid = $file['url'];
        if (empty($attachment->post_title)) {
            $attachment->post_title = preg_replace('/\.[^.]+$/', '', wp_basename($file['file']));
        }
        $id = wp_insert_attachment(wp_slash((array) $attachment), $file['file'], 0, true, false);
        if (is_wp_error($id)) {
            $id->add_data(['status' => $id->get_error_code() === 'db_update_error' ? 500 : 400]);
            return $id;
        }
        do_action('rest_insert_attachment', get_post($id), $request, true);
        return ['attachment_id' => $id, 'file' => $file['file']];
    }

    protected function handle_featured_media($featured_media, $post_id)
    {
        // Only audio and video take a featured image, and only where the theme or the type supports one.
        $mime = (string) get_post_mime_type($post_id);
        $kind = wp_attachment_is('audio', $post_id) ? 'attachment:audio' : (wp_attachment_is('video', $post_id) ? 'attachment:video' : null);
        if ($kind !== null && (post_type_supports($kind, 'thumbnail') || current_theme_supports('post-thumbnails', $kind))) {
            return parent::handle_featured_media($featured_media, $post_id);
        }
        /* translators: %s: Attachment mime type. */
        return new WP_Error('rest_no_featured_media', sprintf(__('This site does not support post thumbnails on attachments with MIME type %s.'), $mime), ['status' => 400]);
    }

    public function post_process_item($request)
    {
        // The one action: make the sizes the attachment is missing; the answer is its edit view.
        if ($request['action'] === 'create-image-subsizes') {
            wp_update_image_subsizes((int) $request['id']);
        }
        $request['context'] = 'edit';
        return $this->prepare_item_for_response(get_post((int) $request['id']), $request);
    }

    public function post_process_item_permissions_check($request)
    {
        return $this->update_item_permissions_check($request);
    }

    public function edit_media_item_permissions_check($request)
    {
        // Uploading, then editing the attachment.
        if (!current_user_can('upload_files')) {
            return new WP_Error('rest_cannot_edit_image', __('Sorry, you are not allowed to upload media on this site.'), ['status' => rest_authorization_required_code()]);
        }
        return $this->update_item_permissions_check($request);
    }

    /**
     * An image edited into a new attachment (probe rest-attachments-controller):
     * the attachment's original file, flipped, rotated and cropped as the
     * modifiers (or the old rotation and crop fields) say, saved "-edited"
     * beside it; the new attachment takes the request's fields and the
     * original's title, caption, description and alt text where the
     * request gives none, its own sizes, and the original as parent_image
     * (through wp_edited_image_metadata). The answer is its view, a 201.
     */
    public function edit_media_item($request)
    {
        $attachment_id = (int) $request['id'];
        $image_file = wp_get_original_image_path($attachment_id);
        $image_meta = wp_get_attachment_metadata($attachment_id);
        if (!$image_file || !is_array($image_meta) || empty($image_meta['width']) || !wp_image_file_matches_image_meta((string) $request['src'], $image_meta, $attachment_id)) {
            return new WP_Error('rest_unknown_attachment', __('Unable to get meta information for file.'), ['status' => 404]);
        }
        $modifiers = $this->minn_modifiers($request);
        if ($modifiers === []) {
            return new WP_Error('rest_image_not_edited', __('The image was not edited. Edit the image before applying the changes.'), ['status' => 400]);
        }
        $editor = wp_get_image_editor($image_file);
        if (is_wp_error($editor)) {
            return new WP_Error('rest_unknown_image_file_type', __('Unable to edit this image.'), ['status' => 500]);
        }
        foreach ($modifiers as $modifier) {
            $applied = $this->minn_apply_modifier($editor, $modifier);
            if (is_wp_error($applied)) {
                return $applied;
            }
        }
        $info = pathinfo($image_file);
        $filename = wp_unique_filename($info['dirname'], $info['filename'] . '-edited.' . $info['extension']);
        $saved = $editor->save($info['dirname'] . '/' . $filename);
        if (is_wp_error($saved)) {
            return $saved;
        }
        $new_id = $this->minn_edited_attachment($request, $attachment_id, $saved);
        if (is_wp_error($new_id)) {
            return $new_id;
        }
        $new_meta = wp_generate_attachment_metadata($new_id, $saved['path']);
        $new_meta['parent_image'] = ['attachment_id' => $attachment_id, 'file' => _wp_relative_upload_path($image_file)];
        $new_meta = apply_filters('wp_edited_image_metadata', $new_meta, $new_id, $attachment_id);
        wp_update_attachment_metadata($new_id, $new_meta);
        $request->set_param('context', 'view');
        $response = rest_ensure_response($this->prepare_item_for_response(get_post($new_id), $request));
        $response->set_status(201);
        $response->header('Location', rest_url(rest_get_route_for_post($new_id)));
        return $response;
    }

    /** @internal the edits asked for: the modifiers, or the old rotation and crop fields made into them */
    private function minn_modifiers($request): array
    {
        if (isset($request['modifiers'])) {
            return (array) $request['modifiers'];
        }
        $modifiers = [];
        if (!empty($request['rotation'])) {
            $modifiers[] = ['type' => 'rotate', 'args' => ['angle' => $request['rotation']]];
        }
        if (isset($request['x'], $request['y'], $request['width'], $request['height'])) {
            $modifiers[] = ['type' => 'crop', 'args' => ['left' => $request['x'], 'top' => $request['y'], 'width' => $request['width'], 'height' => $request['height']]];
        }
        return $modifiers;
    }

    /** @internal one edit applied to the image: a flip, a clockwise rotation, or a crop given in percentages of its size now */
    private function minn_apply_modifier(WP_Image_Editor $editor, array $modifier)
    {
        $args = (array) ($modifier['args'] ?? []);
        switch ($modifier['type'] ?? '') {
            case 'flip':
                $done = $editor->flip(!empty($args['flip']['vertical']), !empty($args['flip']['horizontal']));
                return is_wp_error($done) ? new WP_Error('rest_image_flip_failed', __('Unable to flip this image.'), ['status' => 500]) : true;
            case 'rotate':
                $done = $editor->rotate(0 - (float) $args['angle']);
                return is_wp_error($done) ? new WP_Error('rest_image_rotation_failed', __('Unable to rotate this image.'), ['status' => 500]) : true;
            case 'crop':
                $size = $editor->get_size();
                $done = $editor->crop(round($size['width'] * (float) $args['left'] / 100.0), round($size['height'] * (float) $args['top'] / 100.0), round($size['width'] * (float) $args['width'] / 100.0), round($size['height'] * (float) $args['height'] / 100.0));
                return is_wp_error($done) ? new WP_Error('rest_image_crop_failed', __('Unable to crop this image.'), ['status' => 500]) : true;
        }
        return true;
    }

    /** @internal the edited file's attachment: the request's fields, the original's where it gives none, its alt text copied */
    private function minn_edited_attachment($request, int $attachment_id, array $saved)
    {
        $original = get_post($attachment_id);
        $prepared = $this->prepare_item_for_database($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        unset($prepared->ID);
        $uploads = wp_upload_dir();
        $prepared->post_mime_type = $saved['mime-type'];
        $prepared->guid = $uploads['url'] . '/' . $saved['file'];
        foreach (['post_title', 'post_excerpt', 'post_content'] as $field) {
            if (!isset($prepared->{$field})) {
                $prepared->{$field} = $original->{$field};
            }
        }
        $new_id = wp_insert_attachment(wp_slash((array) $prepared), $saved['path'], 0, true, false);
        if (is_wp_error($new_id)) {
            $new_id->add_data(['status' => $new_id->get_error_code() === 'db_update_error' ? 500 : 400]);
            return $new_id;
        }
        $alt = isset($request['alt_text']) ? (string) $request['alt_text'] : (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($alt !== '') {
            update_post_meta($new_id, '_wp_attachment_image_alt', wp_slash($alt));
        }
        return $new_id;
    }

    protected function prepare_item_for_database($request)
    {
        // The posts fields, then the caption, the description and the post it belongs to.
        $prepared = parent::prepare_item_for_database($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        foreach (['caption' => 'post_excerpt', 'description' => 'post_content'] as $field => $column) {
            if (isset($request[$field])) {
                $prepared->{$column} = is_string($request[$field]) ? $request[$field] : ($request[$field]['raw'] ?? null);
            }
        }
        if (isset($request['post'])) {
            $prepared->post_parent = (int) $request['post'];
        }
        return $prepared;
    }

    public function prepare_item_for_response($item, $request)
    {
        // The engine's media shape, then the fields asked for in the context asked for.
        $GLOBALS['post'] = $item;
        setup_postdata($item);
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->mediaView((int) $item->ID, $context);
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->minn_media_links($links));
        }
        return $response;
    }

    /** @internal the engine's links as add_links takes them: rels expanded, target hints left to the server */
    private function minn_media_links(array $links): array
    {
        $expanded = [];
        foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $items) {
            foreach ($items as $link) {
                $expanded[$rel][] = ['href' => $link['href']] + array_diff_key((array) $link['attributes'], ['targetHints' => true]);
            }
        }
        return $expanded;
    }

    protected function prepare_links($post)
    {
        // The engine's links for the attachment, one a rel: itself, its collection and type, its author, replies, and the post it is attached to.
        $links = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $post->ID))->mediaLinks((int) $post->ID, 'view');
        return array_map(static fn (array $items) => $items[0], $this->minn_media_links($links));
    }

    /** The posts schema without a password, then the media fields (captured, data/rest-schemas.json) with a quality for each registered size. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $schema = parent::get_item_schema();
        unset($schema['properties']['password']);
        foreach (SchemaData::of('media-properties') as $field => $definition) {
            $schema['properties'][$field] = $definition;
        }
        foreach (get_intermediate_image_sizes() as $size) {
            $schema['properties']['image_quality']['properties']['sizes']['properties'][$size] = ['type' => 'integer', 'minimum' => 1, 'maximum' => 100];
        }
        $this->schema = $schema;
        return $this->add_additional_fields_schema($this->schema);
    }

    protected function upload_from_data($data, $headers, $time = NULL)
    {
        // The body's checks (data, type, a named file, the checksum), then the file through wp_handle_sideload.
        $refused = Upload::refusal((string) $data, (array) $headers);
        if ($refused !== null) {
            return new WP_Error($refused->errorCode, $refused->getMessage(), ['status' => $refused->status]);
        }
        $tmp = wp_tempnam((string) self::get_filename_from_disposition((array) $headers['content_disposition']));
        file_put_contents($tmp, $data);
        $file = ['name' => self::get_filename_from_disposition((array) $headers['content_disposition']), 'type' => $headers['content_type'][0], 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)];
        $sideloaded = wp_handle_sideload($file, ['test_form' => false], $time);
        if (isset($sideloaded['error'])) {
            @unlink($tmp);
            return new WP_Error('rest_upload_sideload_error', $sideloaded['error'], ['status' => 500]);
        }
        return $sideloaded;
    }

    public static function get_filename_from_disposition($disposition_header)
    {
        return Upload::filenameFrom((array) $disposition_header);
    }

    public function get_collection_params()
    {
        // The posts parameters with the attachment statuses, then the media types and MIME types.
        $params = parent::get_collection_params();
        $params['status']['default'] = 'inherit';
        $params['status']['items']['enum'] = ['inherit', 'private', 'trash'];
        $params['media_type'] = ['default' => null, 'description' => __('Limit result set to attachments of a particular media type or media types.'), 'type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys($this->get_media_types())]];
        $params['mime_type'] = ['default' => null, 'description' => __('Limit result set to attachments of a particular MIME type or MIME types.'), 'type' => 'array', 'items' => ['type' => 'string']];
        return $params;
    }

    protected function upload_from_file($files, $headers, $time = NULL)
    {
        // The form's file through wp_handle_upload.
        if (empty($files)) {
            return new WP_Error('rest_upload_no_data', __('No data supplied.'), ['status' => 400]);
        }
        $uploaded = wp_handle_upload($files['file'], ['test_form' => false], $time);
        if (isset($uploaded['error'])) {
            return new WP_Error('rest_upload_unknown_error', $uploaded['error'], ['status' => 500]);
        }
        return $uploaded;
    }

    protected function get_media_types()
    {
        // The allowed MIME types grouped by their first part.
        $media_types = [];
        foreach (get_allowed_mime_types() as $mime_type) {
            $media_types[explode('/', $mime_type)[0]][] = $mime_type;
        }
        return $media_types;
    }

    protected function check_upload_size($file)
    {
        // A single site caps no upload by size (probed); a network's space check is owed.
        return true;
    }

    protected function get_edit_media_item_args()
    {
        // The edit's own fields (captured), then the attachment fields it may set.
        $args = SchemaData::of('media-edit-args');
        $fields = $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE);
        foreach (['caption', 'description', 'title', 'post', 'alt_text'] as $field) {
            if (isset($fields[$field])) {
                $args[$field] = $fields[$field];
            }
        }
        return $args;
    }

    protected function get_attachment_filename(int $attachment_id): ?string
    {
        $path = wp_get_original_image_path($attachment_id) ?: get_attached_file($attachment_id);
        return $path ? wp_basename($path) : null;
    }

    protected function get_attachment_filesize(int $attachment_id): ?int
    {
        // The size the metadata records, else the file's own.
        $meta = wp_get_attachment_metadata($attachment_id);
        if (isset($meta['filesize'])) {
            return (int) $meta['filesize'];
        }
        $path = wp_get_original_image_path($attachment_id) ?: get_attached_file($attachment_id);
        return $path && file_exists($path) ? (int) wp_filesize($path) : null;
    }

    public function sideload_item_permissions_check($request)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::sideload_item_permissions_check');
        return null;
    }

    public function sideload_item(WP_REST_Request $request)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::sideload_item');
        return null;
    }

    protected function validate_sub_size_provenance(int $attachment_id, array $sub_sizes)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::validate_sub_size_provenance');
        return null;
    }

    protected function get_sideloaded_file_names(int $attachment_id, bool $include_provenance = true): array
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::get_sideloaded_file_names');
        return [];
    }

    protected function get_attachment_upload_subdir(string $attached_file): ?string
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::get_attachment_upload_subdir');
        return null;
    }

    public function finalize_item(WP_REST_Request $request)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Attachments_Controller::finalize_item');
        return null;
    }

    /** @internal one of the engine's media routes run for this request; its answer, or its error as a WP_Error */
    private function minn_engine(string $method, string $route, $request)
    {
        $call = clone $request;
        $call->set_method($method);
        $call->set_route($route);
        $api = WP_REST_Server::minn_engine_api($call);
        $engine = Minn\Rest\RuntimePrepare::during($call, static fn () => $api->handleEngineOnly($route, $call));
        $body = json_decode((string) $engine?->body, true);
        if ($engine === null || $engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_no_route'), (string) ($body['message'] ?? __('No route was found matching the URL and request method.')), $body['data'] ?? ['status' => $engine->status ?? 404]);
        }
        clean_post_cache((int) ($body['id'] ?? 0));
        return (array) $body;
    }
}
