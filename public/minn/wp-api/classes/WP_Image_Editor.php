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
            $quality = apply_filters('wp_editor_set_quality', $this->get_default_quality($mime_type), $mime_type, $dims ?: $this->size);
            if ($mime_type === 'image/jpeg') {
                $quality = apply_filters('jpeg_quality', $quality, 'image_resize');
            }
            if ($quality < 0 || $quality > 100) {
                $quality = $this->default_quality;
            }
        }
        $quality = (int) $quality === 0 ? 1 : (int) $quality;
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
        $mime_type = $mime_type ?: $this->mime_type;
        $map = wp_get_image_editor_output_format($filename, $this->mime_type);
        if (isset($map[$mime_type]) && static::supports_mime_type($map[$mime_type])) {
            $mime_type = $map[$mime_type];
        }
        if (!static::supports_mime_type($mime_type)) {
            $mime_type = $this->default_mime_type;
        }
        $source = $filename ?? $this->file;
        $extension = $mime_type === $this->mime_type ? strtolower((string) pathinfo((string) $source, PATHINFO_EXTENSION)) : (string) static::get_extension($mime_type);
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

    /** What make_subsize puts back after a size: the editor's own state. */
    protected function keep()
    {
        return ['size' => $this->size];
    }

    protected function restore($state)
    {
        $this->size = $state['size'];
    }

    protected function make_image($filename, $callback, $arguments)
    {
        wp_mkdir_p(dirname($filename));
        return (bool) call_user_func_array($callback, $arguments);
    }

    protected static function get_mime_type($extension = null)
    {
        return $extension ? (wp_check_filetype('x.' . $extension)['type'] ?: false) : false;
    }

    protected static function get_extension($mime_type = null)
    {
        return match ($mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => false,
        };
    }
}

final class WP_Image_Editor_GD extends WP_Image_Editor
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
            return new WP_Error('error_loading_image', 'File doesn&#8217;t exist?', $this->file);
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
        $box = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if (!$box) {
            return new WP_Error('error_getting_dimensions', 'Could not calculate resized image dimensions', $this->file);
        }
        $this->adopt($this->canvas->resample($box));
        return $this->set_quality();
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
                $saved = $this->save();
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

    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
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

    public function save($destfilename = null, $mime_type = null)
    {
        [$filename, $extension, $mime_type] = $this->get_output_format($destfilename, $mime_type);
        $filename = $filename ?? $this->generate_filename(null, null, $extension);
        $this->canvas->interlaced((bool) apply_filters('image_save_progressive', false, $mime_type) && $mime_type === 'image/jpeg');
        if (!$this->canvas->write($filename, $mime_type, $this->get_quality())) {
            return new WP_Error('image_save_error', 'Image Editor Save Failed');
        }
        return ['path' => $filename, 'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)), 'width' => $this->size['width'], 'height' => $this->size['height'], 'mime-type' => $mime_type, 'filesize' => (int) filesize($filename)];
    }

    public function stream($mime_type = null)
    {
        [, , $mime_type] = $this->get_output_format(null, $mime_type);
        header("Content-Type: {$mime_type}");
        return $this->canvas->stream($mime_type, $this->get_quality());
    }
}
