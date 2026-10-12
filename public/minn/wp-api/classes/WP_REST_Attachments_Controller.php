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
 * are WordPress-shaped here, as are the client-side processing endpoints
 * (sideload and finalize).
 */
class WP_REST_Attachments_Controller extends WP_REST_Posts_Controller
{
    const IMAGE_SIZE_SOURCE_ORIGINAL = 'source_original';
    const META_KEY_SOURCE_IMAGE = 'source_image';
    const META_KEY_SIDELOAD_FILE_NAME = '_wp_sideloaded_file';
    protected $allow_batch = false;

    public function register_routes()
    {
        // The posts routes, then the media actions.
        parent::register_routes();
        $this->minn_register_actions();
    }

    /**
     * @internal The media actions alone: post-process and edit, and sideload
     * and finalize only where images are processed in the browser. The
     * engine registers just these (create_initial_rest_routes); it answers
     * the media routes themselves on its own (Rest\MediaController).
     */
    public function minn_register_actions(): void
    {
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
                'image_size' => ['description' => __('Image size. Can be a single size name or an array of size names to register the same file under multiple sizes.')] + $sizes + ['required' => true, 'validate_callback' => static fn ($value, $request, $param) => self::validate_image_size_names($value, $param)],
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
                'sub_sizes' => ['description' => __('Array of sub-size metadata collected from sideload responses.'), 'type' => 'array', 'default' => [], 'maxItems' => 100, 'validate_callback' => static fn ($value, $request, $param) => self::minn_sub_sizes_checked($value, $param), 'items' => ['type' => 'object', 'properties' => $sub_size]],
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
        return $this->edit_media_item_permissions_check($request);
    }

    /**
     * A size the browser made, sideloaded into the attachment (probe
     * rest-attachments-sideload): beside the attachment's file, under the
     * name asked for (one that reads like one of its own sizes keeps it
     * while it is free); then the size names known and each size's
     * dimensions within one pixel of its maximum, the file removed when
     * not. A size is recorded by name for finalize; "scaled" or "original"
     * becomes the attached file, the one before it recorded too;
     * "source_original" is recorded alone.
     */
    public function sideload_item(WP_REST_Request $request)
    {
        $attachment_id = (int) $request['id'];
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
        }
        $file = $this->minn_sideloaded_file($request, (string) get_attached_file($attachment_id));
        if (is_wp_error($file)) {
            return $file;
        }
        $size = $request['image_size'];
        $refused = $this->minn_size_refusal($size, $file['file']);
        if ($refused !== null) {
            wp_delete_file($file['file']);
            return $refused;
        }
        return rest_ensure_response($this->minn_sideload_recorded($attachment_id, $size, $file));
    }

    /** @internal the request's file uploaded beside the attachment's own, its name kept when it reads like one of the attachment's sizes and is free */
    private function minn_sideloaded_file(WP_REST_Request $request, string $attached)
    {
        $base = pathinfo($attached, PATHINFO_FILENAME);
        $keep = static function ($filename, $ext, $dir, $callback, $alternatives, $number) use ($base) {
            $plain = $number === '' || $number === null ? null : preg_replace('/-' . preg_quote((string) $number, '/') . '(\.[^.]*)?$/', '$1', (string) $filename);
            $stem = $plain === null ? '' : (string) preg_replace('/-(?:\d+x\d+|scaled|rotated)$/', '', pathinfo($plain, PATHINFO_FILENAME));
            return $plain !== null && $stem === $base && !file_exists($dir . '/' . $plain) ? $plain : $filename;
        };
        $folder = $this->get_attachment_upload_subdir($attached);
        $beside = static fn (array $dirs) => $folder === null ? $dirs : array_merge($dirs, ['subdir' => $folder, 'path' => $dirs['basedir'] . $folder, 'url' => $dirs['baseurl'] . $folder]);
        add_filter('wp_unique_filename', $keep, 10, 6);
        add_filter('upload_dir', $beside);
        $files = $request->get_file_params();
        $file = !empty($files) ? $this->upload_from_file($files, $request->get_headers()) : $this->upload_from_data($request->get_body(), $request->get_headers());
        remove_filter('wp_unique_filename', $keep, 10);
        remove_filter('upload_dir', $beside);
        return $file;
    }

    /** @internal why a sideloaded file cannot be taken for the sizes named: an unknown name, or dimensions past a size's maximum by more than a pixel */
    private function minn_size_refusal($size, string $path): ?WP_Error
    {
        $registered = wp_get_registered_image_subsizes();
        $known = array_merge(array_keys($registered), ['original', 'scaled', 'full', 'source_original']);
        $names = (array) $size;
        if ($names === [] || array_diff($names, $known) !== []) {
            return new WP_Error('rest_upload_unknown_size', __('Unknown image size.'), ['status' => 400]);
        }
        $dimensions = wp_getimagesize($path);
        foreach (array_intersect($names, array_keys($registered)) as $name) {
            foreach (['width' => 0, 'height' => 1] as $side => $at) {
                $max = (int) $registered[$name][$side];
                if ($max > 0 && (int) ($dimensions[$at] ?? 0) > $max + 1) {
                    /* translators: 1: The image's width or height, 2: Image size name, 3: The size's maximum. */
                    return new WP_Error('rest_upload_dimension_mismatch', sprintf($side === 'width' ? __('Uploaded image width (%1$d) exceeds maximum for "%2$s" size (%3$d).') : __('Uploaded image height (%1$d) exceeds maximum for "%2$s" size (%3$d).'), (int) $dimensions[$at], $name, $max), ['status' => 400]);
                }
            }
        }
        return null;
    }

    /** @internal a sideloaded file recorded for finalize, and the answer that describes it */
    private function minn_sideload_recorded(int $attachment_id, $size, array $file): array
    {
        $name = wp_basename($file['file']);
        if ($size === 'source_original') {
            add_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME, $name);
            return ['image_size' => $size, 'file' => $name];
        }
        $dimensions = wp_getimagesize($file['file']);
        if ($size === 'scaled' || $size === 'original') {
            $before = wp_basename((string) get_attached_file($attachment_id));
            $relative = _wp_relative_upload_path($file['file']);
            update_attached_file($attachment_id, $file['file']);
            add_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME, $relative);
            add_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME, $before);
            return ['image_size' => $size, 'original_image' => $before, 'width' => (int) $dimensions[0], 'height' => (int) $dimensions[1], 'filesize' => (int) wp_filesize($file['file']), 'file' => $relative];
        }
        add_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME, $name);
        return ['image_size' => $size, 'width' => (int) $dimensions[0], 'height' => (int) $dimensions[1], 'file' => $name, 'mime_type' => $file['type'], 'filesize' => (int) wp_filesize($file['file'])];
    }

    /** The image_size argument's check: one name, a special one included, or a list of registered names. */
    private static function validate_image_size_names($value, string $param)
    {
        $registered = array_keys(wp_get_registered_image_subsizes());
        if (is_string($value)) {
            $names = [$value];
            $registered = array_merge($registered, ['original', 'scaled', 'full', 'source_original']);
        } elseif (is_array($value)) {
            $names = $value;
        } else {
            /* translators: %s: Parameter name. */
            return new WP_Error('rest_invalid_type', sprintf(__('%s must be a string or an array of strings.'), $param));
        }
        foreach ($names as $name) {
            if (!in_array($name, $registered, true)) {
                /* translators: %s: Parameter name. */
                return new WP_Error('rest_not_in_enum', sprintf(__('%s contains an invalid image size.'), $param));
            }
        }
        return true;
    }

    /** @internal the sub_sizes argument's check: each entry's image_size, named by its place */
    private static function minn_sub_sizes_checked($value, string $param)
    {
        foreach ((array) $value as $index => $sub_size) {
            $checked = self::validate_image_size_names(is_array($sub_size) ? ($sub_size['image_size'] ?? null) : null, "{$param}[{$index}][image_size]");
            if (is_wp_error($checked)) {
                return $checked;
            }
        }
        return true;
    }

    protected function validate_sub_size_provenance(int $attachment_id, array $sub_sizes)
    {
        // Every file and original image named must be one a sideload made for the attachment, or one of its own.
        $names = $this->get_sideloaded_file_names($attachment_id);
        foreach ($sub_sizes as $sub_size) {
            foreach (is_array($sub_size) ? ['file', 'original_image'] : [] as $key) {
                if (isset($sub_size[$key]) && !in_array($sub_size[$key], $names, true)) {
                    return new WP_Error('rest_invalid_sub_size_file', __('Invalid sub-size file name. File names must have been produced by a prior sideload for this attachment.'), ['status' => 400]);
                }
            }
        }
        return true;
    }

    protected function get_sideloaded_file_names(int $attachment_id, bool $include_provenance = true): array
    {
        // The names sideloads recorded, then the attached file and the metadata's file (each as a path and a name), its original and source images, and its sizes.
        $names = $include_provenance ? array_map('strval', get_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME)) : [];
        $meta = wp_get_attachment_metadata($attachment_id);
        $meta = is_array($meta) ? $meta : [];
        foreach ([(string) get_post_meta($attachment_id, '_wp_attached_file', true), (string) ($meta['file'] ?? '')] as $file) {
            array_push($names, ...($file === '' ? [] : [$file, wp_basename($file)]));
        }
        $names = array_merge($names, array_filter([$meta['original_image'] ?? null, $meta['source_image'] ?? null]), array_filter(array_column((array) ($meta['sizes'] ?? []), 'file')));
        return array_values(array_unique($names));
    }

    protected function get_attachment_upload_subdir(string $attached_file): ?string
    {
        // The folder under uploads a file lies in ("" at the top), or null for one outside it.
        $basedir = wp_get_upload_dir()['basedir'];
        if (!str_starts_with($attached_file, $basedir . '/')) {
            return null;
        }
        $subdir = dirname(substr($attached_file, strlen($basedir)));
        return $subdir === '/' ? '' : $subdir;
    }

    /**
     * The sideloaded files made the attachment's (probe
     * rest-attachments-sideload): once they are known to be its, each size
     * goes into the metadata under each of its names, "scaled" becomes the
     * image itself with its original named, "source_original" is the
     * source image ("original" is passed over); then
     * wp_generate_attachment_metadata (context update), the metadata
     * stored, the records of the files used dropped, and its edit view.
     */
    public function finalize_item(WP_REST_Request $request)
    {
        $attachment_id = (int) $request['id'];
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
        }
        $sub_sizes = (array) $request['sub_sizes'];
        $valid = $this->validate_sub_size_provenance($attachment_id, $sub_sizes);
        if (is_wp_error($valid)) {
            return $valid;
        }
        $meta = wp_get_attachment_metadata($attachment_id);
        [$meta, $used] = $this->minn_finalized_meta(is_array($meta) ? $meta : [], $sub_sizes);
        $meta = apply_filters('wp_generate_attachment_metadata', $meta, $attachment_id, 'update');
        wp_update_attachment_metadata($attachment_id, $meta);
        foreach ($used as $name) {
            delete_post_meta($attachment_id, self::META_KEY_SIDELOAD_FILE_NAME, $name);
        }
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_post($attachment_id), $request));
    }

    /** @internal the metadata with the sub sizes in it, and the sideloaded names they used */
    private function minn_finalized_meta(array $meta, array $sub_sizes): array
    {
        $used = [];
        foreach (array_filter($sub_sizes, static fn ($sub_size) => is_array($sub_size) && ($sub_size['image_size'] ?? null) !== 'original') as $sub_size) {
            $size = $sub_size['image_size'] ?? null;
            $used[] = $sub_size['file'] ?? '';
            if ($size === 'scaled') {
                $meta = array_replace($meta, array_intersect_key($sub_size, array_flip(['width', 'height', 'file', 'filesize'])), ['original_image' => $sub_size['original_image'] ?? '']);
                $used[] = $sub_size['original_image'] ?? '';
            } elseif ($size === 'source_original') {
                $meta['source_image'] = $sub_size['file'] ?? '';
            } else {
                $meta['sizes'] = array_replace((array) ($meta['sizes'] ?? []), array_fill_keys((array) $size, self::minn_size_entry($sub_size)));
            }
        }
        return [$meta, $used];
    }

    /** @internal one sideloaded size as the metadata stores it */
    private static function minn_size_entry(array $sub_size): array
    {
        return array_filter(['width' => $sub_size['width'] ?? null, 'height' => $sub_size['height'] ?? null, 'file' => $sub_size['file'] ?? null, 'mime-type' => $sub_size['mime_type'] ?? null, 'filesize' => $sub_size['filesize'] ?? null], static fn ($value) => $value !== null);
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
