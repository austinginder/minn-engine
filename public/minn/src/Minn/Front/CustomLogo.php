<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The site logo a theme prints, as get_custom_logo builds it (probe
 * plugin-helpers): the custom_logo theme mod's image at full size, never
 * lazy, its alt its own or else the site's name, linked home (marked the
 * current page on the front page). A theme that unlinks the home page logo
 * gets a plain span there, the image then decorative (an empty alt). The
 * image's attributes go through get_custom_logo_image_attributes and the
 * whole through get_custom_logo; no logo is an empty string, or in the
 * Customizer a hidden placeholder it can fill in.
 */
final class CustomLogo
{
    /** The logo's markup, through get_custom_logo; empty when the site has none. */
    public static function html(int $blogId): string
    {
        $logo = (int) \get_theme_mod('custom_logo');
        $html = '';
        if ($logo > 0) {
            $front = \is_front_page() && !\is_paged();
            $unlinked = $front && \get_theme_support('custom-logo', 'unlink-homepage-logo');
            $attrs = ['class' => 'custom-logo', 'loading' => false];
            if ($unlinked) {
                $attrs['alt'] = '';
            } elseif (empty(\get_post_meta($logo, '_wp_attachment_image_alt', true))) {
                $attrs['alt'] = \get_bloginfo('name', 'display');
            }
            $attrs = \apply_filters('get_custom_logo_image_attributes', $attrs, $logo, $blogId);
            $image = (string) \wp_get_attachment_image($logo, 'full', false, $attrs);
            $html = $unlinked
                ? sprintf('<span class="custom-logo-link">%s</span>', $image)
                : sprintf('<a href="%1$s" class="custom-logo-link" rel="home"%2$s>%3$s</a>', \esc_url(\home_url('/')), $front ? ' aria-current="page"' : '', $image);
        } elseif (\is_customize_preview()) {
            $html = sprintf('<a href="%1$s" class="custom-logo-link" style="display:none;"><img class="custom-logo" alt="" /></a>', \esc_url(\home_url('/')));
        }
        return (string) \apply_filters('get_custom_logo', $html, $blogId);
    }
}
