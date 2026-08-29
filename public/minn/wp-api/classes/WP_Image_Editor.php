<?php

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

    public function set_quality($quality = null)
    {
        $this->quality = $quality === null ? $this->default_quality : (int) $quality;
        return true;
    }

    public function get_default_quality($mime_type = '')
    {
        return $this->default_quality;
    }

    protected function get_output_format($filename = null, $mime_type = null)
    {
        $mime_type = $mime_type ?: $this->mime_type;
        $extension = match ($mime_type) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'jpg',
        };
        if ($filename === null) {
            $filename = $this->generate_filename(null, null, $extension);
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

    public function maybe_exif_rotate()
    {
        return false;
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
            default => false,
        };
    }
}

final class WP_Image_Editor_GD extends WP_Image_Editor
{
    protected $image;

    public static function test($args = [])
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    public static function supports_mime_type($mime_type)
    {
        return in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public function load()
    {
        if ($this->image) {
            return true;
        }
        if (!is_file($this->file)) {
            return new WP_Error('error_loading_image', 'File doesn&#8217;t exist?', $this->file);
        }
        $data = @file_get_contents($this->file);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if (!$image) {
            return new WP_Error('invalid_image', 'File is not an image.', $this->file);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $this->image = $image;
        $this->mime_type = wp_get_image_mime($this->file) ?: 'image/png';
        $this->update_size(imagesx($image), imagesy($image));
        return true;
    }

    public function resize($max_w, $max_h, $crop = false)
    {
        if ($this->size['width'] === (int) $max_w && $this->size['height'] === (int) $max_h) {
            return true;
        }
        $dims = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if (!$dims) {
            return new WP_Error('error_getting_dimensions', 'Could not calculate resized image dimensions', $this->file);
        }
        [$dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h] = $dims;
        $resized = imagecreatetruecolor($dst_w, $dst_h);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $this->image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
        $this->image = $resized;
        $this->update_size($dst_w, $dst_h);
        return true;
    }

    public function multi_resize($sizes)
    {
        $metadata = [];
        $original = $this->image;
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
            $this->image = $original;
            $this->size = $originalSize;
        }
        return $metadata;
    }

    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        $dst_w = $dst_w ?? $src_w;
        $dst_h = $dst_h ?? $src_h;
        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }
        $dst = imagecreatetruecolor((int) $dst_w, (int) $dst_h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $this->image, 0, 0, (int) $src_x, (int) $src_y, (int) $dst_w, (int) $dst_h, (int) $src_w, (int) $src_h);
        $this->image = $dst;
        $this->update_size((int) $dst_w, (int) $dst_h);
        return true;
    }

    public function rotate($angle)
    {
        $rotated = imagerotate($this->image, (float) $angle, 0);
        if (!$rotated) {
            return new WP_Error('image_rotate_error', 'Image rotate failed.', $this->file);
        }
        $this->image = $rotated;
        $this->update_size(imagesx($rotated), imagesy($rotated));
        return true;
    }

    public function flip($horz, $vert)
    {
        if ($horz) {
            imageflip($this->image, IMG_FLIP_VERTICAL);
        }
        if ($vert) {
            imageflip($this->image, IMG_FLIP_HORIZONTAL);
        }
        return true;
    }

    public function save($destfilename = null, $mime_type = null)
    {
        [$filename, $extension, $mime_type] = $this->get_output_format($destfilename, $mime_type);
        $written = match ($mime_type) {
            'image/png' => $this->make_image($filename, 'imagepng', [$this->image, $filename]),
            'image/gif' => $this->make_image($filename, 'imagegif', [$this->image, $filename]),
            'image/webp' => $this->make_image($filename, 'imagewebp', [$this->image, $filename, $this->get_quality()]),
            default => $this->make_image($filename, 'imagejpeg', [$this->image, $filename, $this->get_quality()]),
        };
        if (!$written) {
            return new WP_Error('image_save_error', 'Image Editor Save Failed');
        }
        return ['path' => $filename, 'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)), 'width' => $this->size['width'], 'height' => $this->size['height'], 'mime-type' => $mime_type, 'filesize' => (int) filesize($filename)];
    }

    public function stream($mime_type = null)
    {
        [, , $mime_type] = $this->get_output_format(null, $mime_type);
        header("Content-Type: {$mime_type}");
        return match ($mime_type) {
            'image/png' => imagepng($this->image),
            'image/gif' => imagegif($this->image),
            'image/webp' => imagewebp($this->image, null, $this->get_quality()),
            default => imagejpeg($this->image, null, $this->get_quality()),
        };
    }
}
