<?php

use Minn\Media\Canvas;

/** The image editor contract and its GD implementation, from the observed sizes and file names. */
abstract class WP_Image_Editor
{
    protected $file = null;
    protected $size = null;
    protected $mime_type = null;
    protected $output_mime_type = null;
    protected $default_mime_type = 'image/jpeg';
    protected $quality = false;
    protected $default_quality = 82;

    public function __construct($file)
    {
        $this->file = $file;
    }

    abstract public function load();
    abstract public function save($destfilename = null, $mime_type = null);
    abstract public function resize($max_w, $max_h, $crop = false);
    abstract public function multi_resize($sizes);
    abstract public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false);
    abstract public function rotate($angle);
    abstract public function flip($horz, $vert);
    abstract public function stream($mime_type = null);

    public static function test($args = [])
    {
        return false;
    }

    public static function supports_mime_type($mime_type)
    {
        return false;
    }

    public function get_size()
    {
        return $this->size;
    }

    protected function update_size($width = null, $height = null)
    {
        $this->size = ['width' => (int) $width, 'height' => (int) $height];
        return true;
    }

    public function get_quality()
    {
        return $this->quality === false ? $this->default_quality : $this->quality;
    }

    /**
     * The quality the next save uses. Without one given, the default for the
     * type being written (86 for WebP, 82 otherwise) through
     * wp_editor_set_quality with that type and the current size, and for a
     * JPEG then jpeg_quality (captured: on load and after each resize, and
     * again when a save writes another type).
     */
    public function set_quality($quality = null, $dims = [])
    {
        $mime_type = $this->output_mime_type ?: $this->mime_type;
        if ($quality === null) {
            $quality = wp_get_image_encode_quality($mime_type, $dims ?: $this->size, $this->get_default_quality($mime_type));
            if ($quality < 0 || $quality > 100) {
                $quality = $this->default_quality;
            }
        }
        // Zero means the lowest quality; any other value is kept as given ("60" stays a string).
        $quality = (int) $quality === 0 ? 1 : $quality;
        if ($quality < 1 || $quality > 100) {
            return new WP_Error('invalid_image_quality', 'Attempted to set image quality outside of the range [1,100].');
        }
        $this->quality = $quality;
        return true;
    }

    public function get_default_quality($mime_type = '')
    {
        return $mime_type === 'image/webp' ? 86 : $this->default_quality;
    }

    /**
     * The file name, extension and type a save writes: the type asked for or
     * the image's own, through image_editor_output_format (handed the file
     * name, or null for a size, and the image's type), falling back to the
     * default when the editor cannot write it. A name given keeps its stem
     * and takes the new extension; writing another type than the image's
     * resets the quality for it.
     */
    protected function get_output_format($filename = null, $mime_type = null)
    {
        // A file name with no mime type names its format by extension; the source's own format keeps the source's extension.
        if (!$mime_type && $filename !== null) {
            $mime_type = static::get_mime_type(strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION))) ?: null;
        }
        $mime_type = $mime_type ?: $this->mime_type;
        $map = wp_get_image_editor_output_format($filename, $this->mime_type);
        if (isset($map[$mime_type]) && static::supports_mime_type($map[$mime_type])) {
            $mime_type = $map[$mime_type];
        }
        if (!static::supports_mime_type($mime_type)) {
            $mime_type = apply_filters('image_editor_default_mime_type', $this->default_mime_type);
        }
        $extension = $mime_type === $this->mime_type ? strtolower((string) pathinfo((string) $this->file, PATHINFO_EXTENSION)) : (string) static::get_extension($mime_type);
        $extension = $extension !== '' ? $extension : (string) static::get_extension($mime_type);
        if ($filename !== null) {
            $filename = trailingslashit(pathinfo((string) $filename, PATHINFO_DIRNAME)) . pathinfo((string) $filename, PATHINFO_FILENAME) . '.' . $extension;
        }
        if ($mime_type !== $this->mime_type) {
            $this->output_mime_type = $mime_type;
            $this->set_quality();
        } elseif ($this->output_mime_type !== null) {
            $this->output_mime_type = null;
            $this->set_quality();
        }
        return [$filename, $extension, $mime_type];
    }

    public function generate_filename($suffix = null, $dest_path = null, $extension = null)
    {
        $suffix = $suffix ?: $this->get_suffix();
        $dir = pathinfo($this->file, PATHINFO_DIRNAME);
        $ext = pathinfo($this->file, PATHINFO_EXTENSION);
        $name = wp_basename($this->file, ".{$ext}");
        $new_ext = strtolower($extension ?: $ext);
        if ($dest_path !== null && is_dir($dest_path)) {
            $dir = rtrim($dest_path, '/');
        }
        return trailingslashit($dir) . "{$name}-{$suffix}.{$new_ext}";
    }

    public function get_suffix()
    {
        if (!$this->get_size()) {
            return false;
        }
        return "{$this->size['width']}x{$this->size['height']}";
    }

    /**
     * Turns the image upright when its EXIF says it was taken turned: the
     * orientation a JPEG records, through wp_image_maybe_exif_rotate (asked
     * for any image), then the rotation or mirroring that orientation
     * means. False when there was nothing to do.
     */
    public function maybe_exif_rotate()
    {
        $orientation = null;
        if (is_callable('exif_read_data') && $this->mime_type === 'image/jpeg') {
            $exif = @exif_read_data((string) $this->file);
            $orientation = is_array($exif) && !empty($exif['Orientation']) ? (int) $exif['Orientation'] : null;
        }
        $orientation = apply_filters('wp_image_maybe_exif_rotate', $orientation, $this->file);
        if (!$orientation || (int) $orientation === 1) {
            return false;
        }
        // Rotation counter-clockwise, then a mirror along the vertical axis.
        [$angle, $mirror] = match ((int) $orientation) {
            2 => [0, true],
            3 => [180, false],
            4 => [180, true],
            5 => [270, true],
            6 => [270, false],
            7 => [90, true],
            8 => [90, false],
            default => [0, false],
        };
        $result = $angle ? $this->rotate($angle) : true;
        if (!is_wp_error($result) && $mirror) {
            $result = $this->flip(false, true);
        }
        return $result;
    }

    /**
     * One size cut from the image as it stands and saved, the image left as
     * it was; the size's metadata without its path, or the error.
     */
    public function make_subsize($size_data)
    {
        if (!isset($size_data['width']) && !isset($size_data['height'])) {
            return new WP_Error('image_subsize_create_error', 'Cannot resize the image. Both width and height are not set.');
        }
        $state = $this->keep();
        $resized = $this->resize($size_data['width'] ?? null, $size_data['height'] ?? null, !empty($size_data['crop']) ? $size_data['crop'] : false);
        $saved = is_wp_error($resized) ? $resized : $this->save();
        $this->restore($state);
        if (!is_wp_error($saved)) {
            unset($saved['path']);
        }
        return $saved;
    }

    /** What make_subsize puts back after a size: the editor's size, and the file and format a save moves it to. */
    protected function keep()
    {
        return ['size' => $this->size, 'file' => $this->file, 'mime_type' => $this->mime_type];
    }

    protected function restore($state)
    {
        [$this->size, $this->file, $this->mime_type] = [$state['size'], $state['file'], $state['mime_type']];
    }

    protected function make_image($filename, $callback, $arguments)
    {
        wp_mkdir_p(dirname($filename));
        return (bool) call_user_func_array($callback, $arguments);
    }

    /** The type the site's MIME list gives an extension (image/jpeg for jpg); false for one it does not list. */
    protected static function get_mime_type($extension = null)
    {
        if (empty($extension)) {
            return false;
        }
        foreach (wp_get_mime_types() as $extensions => $type) {
            if (in_array(strtolower((string) $extension), explode('|', (string) $extensions), true)) {
                return $type;
            }
        }
        return false;
    }

    /** The first extension the site's MIME list gives the type (jpg for image/jpeg); false for a type it does not list. */
    protected static function get_extension($mime_type = null)
    {
        if (empty($mime_type)) {
            return false;
        }
        $extensions = array_search($mime_type, wp_get_mime_types(), true);
        return $extensions === false ? false : explode('|', (string) $extensions)[0];
    }
}

class WP_Image_Editor_GD extends WP_Image_Editor
{
    private ?Canvas $canvas = null;

    public static function test($args = [])
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    public static function supports_mime_type($mime_type)
    {
        return in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) || ($mime_type === 'image/avif' && function_exists('imageavif'));
    }

    public function load()
    {
        if ($this->canvas !== null) {
            return true;
        }
        if (!is_file($this->file)) {
            return new WP_Error('error_loading_image', __('File does not exist?'), $this->file);
        }
        $canvas = Canvas::open($this->file);
        if ($canvas === null) {
            return new WP_Error('invalid_image', 'File is not an image.', $this->file);
        }
        $this->mime_type = wp_get_image_mime($this->file) ?: 'image/png';
        $this->adopt($canvas);
        return $this->set_quality();
    }

    private function adopt(Canvas $canvas): bool
    {
        $this->canvas = $canvas;
        return $this->update_size($canvas->width, $canvas->height);
    }

    public function resize($max_w, $max_h, $crop = false)
    {
        if ($this->size['width'] === (int) $max_w && $this->size['height'] === (int) $max_h) {
            return true;
        }
        $resized = $this->_resize($max_w, $max_h, $crop);
        if (is_wp_error($resized)) {
            return $resized;
        }
        $this->adopt(Canvas::of($resized));
        return $this->set_quality();
    }

    /** The image resampled to fit (or fill, cropped) the box, as a new GD image; an error when no box fits. */
    protected function _resize($max_w, $max_h, $crop = false)
    {
        $box = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if (!$box) {
            return new WP_Error('error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file);
        }
        return $this->canvas->resample($box)->image();
    }

    protected function keep()
    {
        return parent::keep() + ['canvas' => $this->canvas];
    }

    protected function restore($state)
    {
        parent::restore($state);
        $this->canvas = $state['canvas'];
    }

    public function multi_resize($sizes)
    {
        $metadata = [];
        $original = $this->canvas;
        $originalSize = $this->size;
        foreach ($sizes as $name => $size) {
            if (!isset($size['width']) && !isset($size['height'])) {
                continue;
            }
            $size += ['width' => null, 'height' => null, 'crop' => false];
            $resized = $this->resize($size['width'], $size['height'], $size['crop']);
            if (!is_wp_error($resized) && ($this->size['width'] !== $originalSize['width'] || $this->size['height'] !== $originalSize['height'])) {
                $saved = $this->_save($this->canvas->image());
                if (!is_wp_error($saved)) {
                    unset($saved['path']);
                    $metadata[$name] = $saved;
                }
            }
            $this->canvas = $original;
            $this->size = $originalSize;
        }
        return $metadata;
    }

    /** The image cut to a box (scaled to a destination size when one is given); an error for a box with no area. */
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }
        if ((int) $src_w < 1 || (int) $src_h < 1 || ($dst_w !== null && (int) $dst_w < 1) || ($dst_h !== null && (int) $dst_h < 1)) {
            return new WP_Error('image_crop_error', __('Image crop failed.'), $this->file);
        }
        return $this->adopt($this->canvas->crop((int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h, $dst_w === null ? null : (int) $dst_w, $dst_h === null ? null : (int) $dst_h));
    }

    public function rotate($angle)
    {
        $rotated = $this->canvas->rotate((float) $angle);
        if ($rotated === null) {
            return new WP_Error('image_rotate_error', 'Image rotate failed.', $this->file);
        }
        return $this->adopt($rotated);
    }

    /** Flips along the horizontal axis (top to bottom) and/or the vertical one (left to right). */
    public function flip($horz, $vert)
    {
        $canvas = $this->canvas->copy();
        $canvas = $horz ? $canvas->flipVertical() : $canvas;
        $canvas = $vert ? $canvas->flipHorizontal() : $canvas;
        return $this->adopt($canvas);
    }

    /** The image written (_save); the editor then stands for that file in its format, so the next name grows from it. */
    public function save($destfilename = null, $mime_type = null)
    {
        $saved = $this->_save($this->canvas->image(), $destfilename, $mime_type);
        if (!is_wp_error($saved)) {
            [$this->file, $this->mime_type] = [$saved['path'], $saved['mime-type']];
        }
        return $saved;
    }

    /** A GD image written in the output format, under the name given or one the editor makes: its path, file, size, type and bytes. */
    protected function _save($image, $filename = null, $mime_type = null)
    {
        [$filename, $extension, $mime_type] = $this->get_output_format($filename, $mime_type);
        $filename = $filename ?? $this->generate_filename(null, null, $extension);
        $canvas = Canvas::of($image);
        apply_filters('image_save_progressive', false, $mime_type) && $mime_type === 'image/jpeg' ? $canvas->progressive() : $canvas->baseline();
        if (!$canvas->write($filename, $mime_type, $this->get_quality())) {
            return new WP_Error('image_save_error', __('Image Editor Save Failed'));
        }
        return ['path' => $filename, 'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)), 'width' => $canvas->width, 'height' => $canvas->height, 'mime-type' => $mime_type, 'filesize' => (int) filesize($filename)];
    }

    /** Nothing to let go: PHP frees the GD image with the editor. */
    public function __destruct()
    {
    }

    public function stream($mime_type = null)
    {
        [, , $mime_type] = $this->get_output_format(null, $mime_type);
        header("Content-Type: {$mime_type}");
        return $this->canvas->stream($mime_type, $this->get_quality());
    }
}
