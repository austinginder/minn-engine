<?php
/**
 * The Imagick image editor (probe imagick-editor), from what the reference
 * writes. Picked before GD whenever the extension is loaded.
 *
 * - Loading reads the image (a PDF's first page at 128 dpi, flattened onto
 *   white) and takes its type from the format Imagick reports.
 * - A resample, for a resize or a crop to a size, offers image_strip_meta and
 *   image_max_bit_depth. The profiles a site keeps survive it (EXIF, ICC,
 *   IPTC, XMP, Photoshop's). A PNG loses its other chunks (resolution, text),
 *   and an indexed PNG is brought back to the colours it had.
 * - A crop with no width or height keeps that side whole; a crop past the
 *   edge stops at it.
 * - rotate() turns counter-clockwise; flip() turns top to bottom (horz) and
 *   side to side (vert).
 */
class WP_Image_Editor_Imagick extends WP_Image_Editor
{
    /** Profiles a resample keeps. */
    private const KEPT_PROFILES = ['icc', 'icm', 'iptc', 'exif', 'xmp', '8bim'];

    protected $image = null;
    private $stream_file_data = null;
    private $image_given_name = null;
    /** The colours an indexed PNG had when loaded, which a resample brings it back to; 0 for any other image. */
    private int $indexed_colours = 0;

    public function __destruct()
    {
        if ($this->image instanceof Imagick) {
            $this->image->clear();
            $this->image->destroy();
        }
    }

    /** Whether Imagick is there to use; the methods and type asked for are not checked (as the reference answers). */
    public static function test($args = [])
    {
        return extension_loaded('imagick') && class_exists('Imagick', false) && class_exists('ImagickPixel', false);
    }

    /** Whether Imagick can write the type: the site knows an extension for it and Imagick knows that format. */
    public static function supports_mime_type($mime_type)
    {
        $extension = strtoupper((string) static::get_extension($mime_type));
        if ($extension === '' || !self::test()) {
            return false;
        }
        try {
            return (bool) @Imagick::queryFormats($extension);
        } catch (Exception) {
            return false;
        }
    }

    public function load()
    {
        if (!is_file((string) $this->file) && !wp_is_stream((string) $this->file)) {
            return new WP_Error('error_loading_image', __('File does not exist?'), $this->file);
        }
        $this->indexed_colours = 0;
        try {
            $this->image = new Imagick();
            if (strtolower(pathinfo((string) $this->file, PATHINFO_EXTENSION)) === 'pdf') {
                $loaded = $this->pdf_load_source();
                if (is_wp_error($loaded)) {
                    return $loaded;
                }
            } else {
                $this->image->readImage((string) $this->file);
            }
            if (!$this->image->valid()) {
                return new WP_Error('invalid_image', __('File is not an image.'), $this->file);
            }
            $this->mime_type = static::get_mime_type(strtolower($this->image->getImageFormat()));
            if ($this->mime_type === 'image/png' && str_starts_with((string) $this->image->getImageProperty('png:IHDR.color_type'), '3')) {
                $this->indexed_colours = $this->image->getImageColors();
            }
        } catch (Exception $e) {
            return new WP_Error('invalid_image', $e->getMessage(), $this->file);
        }
        $updated = $this->update_size();
        return is_wp_error($updated) ? $updated : $this->set_quality();
    }

    /** The quality, and Imagick's own setting for the next write. */
    public function set_quality($quality = null, $dims = [])
    {
        $set = parent::set_quality($quality, $dims);
        if ($set === true && $this->image instanceof Imagick) {
            try {
                $this->image->setImageCompressionQuality((int) $this->get_quality());
            } catch (Exception $e) {
                return new WP_Error('image_quality_error', $e->getMessage());
            }
        }
        return $set;
    }

    protected function update_size($width = null, $height = null)
    {
        if ($width === null || $height === null) {
            try {
                $geometry = $this->image->getImageGeometry();
            } catch (Exception) {
                return new WP_Error('invalid_image', __('Could not read image size.'), $this->file);
            }
            $width ??= $geometry['width'];
            $height ??= $geometry['height'];
        }
        return parent::update_size($width, $height);
    }

    /** Deprecated since 6.3.0: Imagick's time limit is left as it is. */
    public static function set_imagick_time_limit()
    {
        _deprecated_function(__METHOD__, '6.3.0');
        return null;
    }

    public function resize($max_w, $max_h, $crop = false)
    {
        if ($this->size['width'] === (int) $max_w && $this->size['height'] === (int) $max_h) {
            return true;
        }
        $box = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if (!$box) {
            return new WP_Error('error_getting_dimensions', __('Could not calculate resized image dimensions'));
        }
        [, , $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h] = $box;
        if ($crop) {
            $resized = $this->crop($src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h);
        } else {
            $resized = $this->thumbnail_image($dst_w, $dst_h);
            $resized = is_wp_error($resized) ? $resized : $this->update_size($dst_w, $dst_h);
        }
        return is_wp_error($resized) ? $resized : $this->set_quality();
    }

    /** The image resampled to the size: what a site keeps stays, the bit depth offered to image_max_bit_depth. */
    protected function thumbnail_image($dst_w, $dst_h, $filter_name = 'FILTER_TRIANGLE', $strip_meta = true)
    {
        $filter = defined('Imagick::' . $filter_name) ? constant('Imagick::' . $filter_name) : Imagick::FILTER_TRIANGLE;
        if (apply_filters('image_strip_meta', $strip_meta)) {
            $this->strip_meta();
        }
        try {
            $this->image->resizeImage((int) $dst_w, (int) $dst_h, $filter, 1);
            if ($this->mime_type === 'image/png') {
                $this->image->setOption('png:exclude-chunk', 'all');
                if ($this->indexed_colours > 0) {
                    $this->image->quantizeImage($this->indexed_colours, Imagick::COLORSPACE_SRGB, 0, false, false);
                }
            }
            $depth = $this->image->getImageDepth();
            $max = (int) apply_filters('image_max_bit_depth', $depth, $depth);
            if ($max > 0 && $depth > $max) {
                $this->image->setImageDepth($max);
            }
        } catch (Exception $e) {
            return new WP_Error('image_resize_error', $e->getMessage());
        }
        return true;
    }

    /** Every size asked for, cut from the image as it stands; a size it could not make, or that leaves the image as it was, is left out. */
    public function multi_resize($sizes)
    {
        $metadata = [];
        foreach ($sizes as $name => $size_data) {
            if (!isset($size_data['width']) && !isset($size_data['height'])) {
                continue;
            }
            $original = clone $this->image;
            $state = $this->keep();
            $resized = $this->resize($size_data['width'] ?? null, $size_data['height'] ?? null, !empty($size_data['crop']) ? $size_data['crop'] : false);
            if (!is_wp_error($resized) && $this->size !== $state['size']) {
                $saved = $this->_save($this->image);
                if (!is_wp_error($saved)) {
                    unset($saved['path']);
                    $metadata[$name] = $saved;
                }
            }
            $this->put_back($original, $state);
        }
        return $metadata;
    }

    public function make_subsize($size_data)
    {
        if (!isset($size_data['width']) && !isset($size_data['height'])) {
            return new WP_Error('image_subsize_create_error', __('Cannot resize the image. Both width and height are not set.'));
        }
        $original = clone $this->image;
        $state = $this->keep();
        $resized = $this->resize($size_data['width'] ?? null, $size_data['height'] ?? null, !empty($size_data['crop']) ? $size_data['crop'] : false);
        $saved = is_wp_error($resized) ? $resized : $this->_save($this->image);
        $this->put_back($original, $state);
        if (is_wp_error($saved)) {
            return $saved;
        }
        unset($saved['path']);
        return $saved;
    }

    /** The image and state as they were before a sub-size was cut. */
    private function put_back(Imagick $original, array $state): void
    {
        $this->image->clear();
        $this->image->destroy();
        $this->image = $original;
        $this->restore($state);
    }

    /** What a sub-size puts back: the base's state and the quality, which a resize resets. */
    protected function keep()
    {
        return parent::keep() + ['quality' => $this->quality];
    }

    protected function restore($state)
    {
        parent::restore($state);
        $this->quality = $state['quality'];
    }

    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }
        try {
            $this->image->cropImage((int) $src_w, (int) $src_h, (int) $src_x, (int) $src_y);
            $this->image->setImagePage(0, 0, 0, 0);
            if ($dst_w || $dst_h) {
                $geometry = $this->image->getImageGeometry();
                $scaled = $this->thumbnail_image($dst_w ?: $geometry['width'], $dst_h ?: $geometry['height']);
                if (is_wp_error($scaled)) {
                    return $scaled;
                }
            }
        } catch (Exception $e) {
            return new WP_Error('image_crop_error', $e->getMessage());
        }
        return $this->update_size();
    }

    /** Turned counter-clockwise by the angle. */
    public function rotate($angle)
    {
        try {
            $this->image->rotateImage(new ImagickPixel('none'), 360 - (float) $angle);
            $this->image->setImagePage(0, 0, 0, 0);
        } catch (Exception $e) {
            return new WP_Error('image_rotate_error', $e->getMessage());
        }
        return $this->update_size();
    }

    /** Flipped top to bottom (horz) and/or side to side (vert). */
    public function flip($horz, $vert)
    {
        try {
            if ($horz) {
                $this->image->flipImage();
            }
            if ($vert) {
                $this->image->flopImage();
            }
        } catch (Exception $e) {
            return new WP_Error('image_flip_error', $e->getMessage());
        }
        return true;
    }

    /** Upright by the EXIF orientation, which then reads upright too. */
    public function maybe_exif_rotate()
    {
        $rotated = parent::maybe_exif_rotate();
        if ($rotated === true && $this->image instanceof Imagick) {
            try {
                $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            } catch (Exception $e) {
                return new WP_Error('image_exif_rotate_error', $e->getMessage());
            }
        }
        return $rotated;
    }

    /** The image written; the editor then stands for that file in its format. */
    public function save($destfilename = null, $mime_type = null)
    {
        $saved = $this->_save($this->image, $destfilename, $mime_type);
        if (!is_wp_error($saved)) {
            $this->file = $saved['path'];
            $this->mime_type = $saved['mime-type'];
            try {
                $this->image->setImageFormat(strtoupper((string) static::get_extension($this->mime_type)));
            } catch (Exception $e) {
                return new WP_Error('image_save_error', $e->getMessage(), $this->file);
            }
        }
        return $saved;
    }

    /** A PDF's transparency, flattened onto white. */
    protected function remove_pdf_alpha_channel()
    {
        try {
            $this->image->setImageBackgroundColor(new ImagickPixel('white'));
            $this->image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        } catch (Exception $e) {
            return new WP_Error('pdf_alpha_process_failed', $e->getMessage());
        }
        return true;
    }

    /** An Imagick image written in the output format, under the name given or one the editor makes: its path, file, size, type and bytes. */
    protected function _save($image, $filename = null, $mime_type = null)
    {
        [$filename, $extension, $mime_type] = $this->get_output_format($filename, $mime_type);
        $filename = $filename ?: $this->generate_filename(null, null, $extension);
        try {
            $format = $image->getImageFormat();
            $image->setImageFormat(strtoupper((string) static::get_extension($mime_type)));
            $image->setImageCompressionQuality((int) $this->get_quality());
        } catch (Exception $e) {
            return new WP_Error('image_save_error', $e->getMessage(), $filename);
        }
        if (apply_filters('image_save_progressive', false, $mime_type)) {
            $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        }
        $written = $this->write_image($image, $filename);
        if (is_wp_error($written)) {
            return $written;
        }
        try {
            $image->setImageFormat($format);
        } catch (Exception $e) {
            return new WP_Error('image_save_error', $e->getMessage(), $filename);
        }
        return ['path' => $filename, 'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)), 'width' => $this->size['width'], 'height' => $this->size['height'], 'mime-type' => $mime_type, 'filesize' => wp_filesize($filename)];
    }

    private function write_image($image, $filename)
    {
        if (wp_is_stream($filename)) {
            return file_put_contents($filename, $image->getImageBlob()) === false
                ? new WP_Error('image_save_error', sprintf(__('Could not write image to %s.'), $filename), $filename)
                : true;
        }
        $dir = dirname($filename);
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('image_save_error', sprintf(__('Unable to create directory %s. Is its parent directory writable by the server?'), esc_html($dir)));
        }
        try {
            return $image->writeImage($filename);
        } catch (Exception $e) {
            return new WP_Error('image_save_error', $e->getMessage(), $filename);
        }
    }

    public function stream($mime_type = null)
    {
        [, , $mime_type] = $this->get_output_format(null, $mime_type);
        try {
            $this->image->setImageFormat(strtoupper((string) static::get_extension($mime_type)));
            header("Content-Type: {$mime_type}");
            print $this->image->getImageBlob();
            $this->image->setImageFormat(strtoupper((string) static::get_extension($this->mime_type)));
        } catch (Exception $e) {
            return new WP_Error('image_stream_error', $e->getMessage());
        }
        return true;
    }

    /** Removes the profiles a site has no use for; the colour, rights and EXIF ones stay. */
    protected function strip_meta()
    {
        try {
            foreach (array_keys($this->image->getImageProfiles('*', true)) as $name) {
                if (!in_array(strtolower((string) $name), self::KEPT_PROFILES, true)) {
                    $this->image->removeImageProfile((string) $name);
                }
            }
        } catch (Exception $e) {
            return new WP_Error('image_strip_meta_error', $e->getMessage());
        }
        return true;
    }

    /** A PDF is read at 128 dpi, its first page only: the name to read. */
    protected function pdf_setup()
    {
        try {
            $this->image->setResolution(128, 128);
        } catch (Exception $e) {
            return new WP_Error('pdf_setup_failed', $e->getMessage(), $this->file);
        }
        return $this->file . '[0]';
    }

    /** A PDF's first page, read and flattened onto white. */
    protected function pdf_load_source()
    {
        $source = $this->pdf_setup();
        if (is_wp_error($source)) {
            return $source;
        }
        try {
            $this->image->readImage($source);
        } catch (Exception $e) {
            return new WP_Error('invalid_image', $e->getMessage(), $this->file);
        }
        return $this->remove_pdf_alpha_channel();
    }
}
