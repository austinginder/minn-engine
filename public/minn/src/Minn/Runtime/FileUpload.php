<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A file a plugin hands to wp_handle_upload or wp_handle_sideload, taken in
 * the reference's order (probe upload-filters, contracts/runtime.md
 * "Uploads"): the action's prefilter, where a plugin sanitizes the file or
 * refuses it with an error; its overrides; the upload's own errors, an
 * empty file and, for a form upload, the form and the upload itself; the
 * type, from the file's content as much as its name
 * (wp_check_filetype_and_ext), refused unless the site allows it or the
 * user may upload anything; the uploads folder; a unique, clean name
 * (wp_unique_filename); pre_move_uploaded_file, where a plugin may move it
 * itself; the move; and wp_handle_upload over the result.
 */
final class FileUpload
{
    private const UPLOAD_ERRORS = [
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        3 => 'The uploaded file was only partially uploaded.',
        4 => 'No file was uploaded.',
        6 => 'Missing a temporary folder.',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    ];

    /**
     * The stored file (file, url, type) or ['error' => message].
     *
     * @param array<string, mixed> $file name, type, tmp_name, size, error
     * @param array<string, mixed>|false $overrides
     * @param string $action wp_handle_upload or wp_handle_sideload
     * @return array<string, mixed>
     */
    public static function handle(array &$file, array|false $overrides, ?string $time, string $action): array
    {
        $file = (array) \apply_filters("{$action}_prefilter", $file);
        $overrides = \apply_filters("{$action}_overrides", $overrides, $file);
        $options = (is_array($overrides) ? $overrides : []) + ['test_form' => $action === 'wp_handle_upload', 'test_size' => true, 'test_type' => true, 'mimes' => null, 'unique_filename_callback' => null, 'action' => $action];
        $refusal = self::refusal($file, $options, $action);
        if ($refusal !== null) {
            return ['error' => $refusal];
        }
        $type = (string) ($file['type'] ?? '');
        if ($options['test_type']) {
            $checked = \wp_check_filetype_and_ext((string) $file['tmp_name'], (string) $file['name'], $options['mimes']);
            if (!empty($checked['proper_filename'])) {
                $file['name'] = (string) $checked['proper_filename'];
            }
            if ((!$checked['type'] || !$checked['ext']) && !\current_user_can('unfiltered_upload')) {
                return ['error' => 'Sorry, you are not allowed to upload this file type.'];
            }
            $type = $checked['type'] ? (string) $checked['type'] : $type;
        }
        $uploads = \wp_upload_dir($time);
        if (($uploads['error'] ?? false) !== false) {
            return ['error' => (string) $uploads['error']];
        }
        $filename = \wp_unique_filename((string) $uploads['path'], (string) $file['name'], $options['unique_filename_callback']);
        $target = rtrim((string) $uploads['path'], '/') . '/' . $filename;
        return self::move($file, $target, $type, (string) $uploads['url'] . '/' . $filename, $action);
    }

    /**
     * Why the upload stops before its type is looked at: a plugin's error
     * from the prefilter, PHP's upload error, a form that is not the
     * action's, an empty file, a form upload that did not arrive by POST.
     *
     * @param array<string, mixed> $file
     * @param array<string, mixed> $options
     */
    private static function refusal(array $file, array $options, string $action): ?string
    {
        $error = $file['error'] ?? 0;
        if (is_string($error) && $error !== '' && !is_numeric($error)) {
            return $error;
        }
        if ((int) $error > 0) {
            return self::UPLOAD_ERRORS[(int) $error] ?? 'File upload error.';
        }
        $request = Runtime::current()->request;
        if ($options['test_form'] && (string) ($request?->form['action'] ?? '') !== (string) $options['action']) {
            return 'Invalid form submission.';
        }
        $size = (int) ($file['size'] ?? (is_file((string) ($file['tmp_name'] ?? '')) ? filesize((string) $file['tmp_name']) : 0));
        if ($options['test_size'] && $size <= 0) {
            return 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.';
        }
        if ($action === 'wp_handle_upload' && !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return 'Specified file failed upload test.';
        }
        return null;
    }

    /**
     * pre_move_uploaded_file, then the move (a form upload's own move, a
     * sideload's copy), the folder's permissions, and wp_handle_upload.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private static function move(array $file, string $target, string $type, string $url, string $action): array
    {
        $moved = \apply_filters('pre_move_uploaded_file', null, $file, $target, $type);
        if ($moved === null) {
            $tmp = (string) $file['tmp_name'];
            $moved = $action === 'wp_handle_upload' ? @move_uploaded_file($tmp, $target) : @copy($tmp, $target);
            if ($action !== 'wp_handle_upload') {
                @unlink($tmp);
            }
            if ($moved === false) {
                return ['error' => sprintf('The uploaded file could not be moved to %s.', dirname($target))];
            }
        }
        $perms = (stat(dirname($target))['mode'] ?? 0755) & 0000666;
        @chmod($target, $perms);
        return \apply_filters('wp_handle_upload', ['file' => $target, 'url' => $url, 'type' => $type], $action === 'wp_handle_sideload' ? 'sideload' : 'upload');
    }
}
