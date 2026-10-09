<?php

// HTTPS (probe https-helpers): whether the site's URLs use it, whether its home answers over it, the move of its URLs
// to it, and the migration flag that has content's own http links made https afterwards.

use Minn\Content\TextFilters;

/** Whether the site and home URLs both use https. */
function wp_is_using_https()
{
    return wp_is_home_url_using_https() && wp_is_site_url_using_https();
}

/** Whether the home URL, as stored and filtered (not as the request reached it), uses https. */
function wp_is_home_url_using_https()
{
    return wp_parse_url(apply_filters('home_url', get_option('home'), '', null, null), PHP_URL_SCHEME) === 'https';
}

/** Whether the site URL, as stored and filtered (not as the request reached it), uses https. */
function wp_is_site_url_using_https()
{
    return wp_parse_url(apply_filters('site_url', get_option('siteurl'), '', null, null), PHP_URL_SCHEME) === 'https';
}

/** Whether the https home answers as this site, with no errors found. */
function wp_is_https_supported()
{
    return empty(wp_get_https_detection_errors());
}

/**
 * The errors a request for the https home finds, by code. A WP_Error from
 * pre_wp_get_https_detection_errors stands for them; any other answer is
 * ignored. A request that fails is tried once more without verifying the
 * certificate: failing again is https_request_failed alone, answering is
 * ssl_verification_failed. Then a status other than 200 is
 * bad_response_code (with the status message), and a page this site did
 * not print is bad_response_source.
 */
function wp_get_https_detection_errors()
{
    $pre = apply_filters('pre_wp_get_https_detection_errors', null);
    if (is_wp_error($pre)) {
        return $pre->errors;
    }
    $args = ['headers' => ['Cache-Control' => 'no-cache'], 'sslverify' => true];
    $response = wp_remote_request(home_url('/', 'https'), $args);
    $errors = [];
    if (is_wp_error($response)) {
        $response = wp_remote_request(home_url('/', 'https'), ['sslverify' => false] + $args);
        if (is_wp_error($response)) {
            return ['https_request_failed' => [__('HTTPS request failed.')]];
        }
        $errors['ssl_verification_failed'] = [__('SSL verification failed.')];
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        $errors['bad_response_code'] = [wp_remote_retrieve_response_message($response)];
    } elseif (wp_is_local_html_output(wp_remote_retrieve_body($response)) === false) {
        $errors['bad_response_source'] = [__('It looks like the response did not come from this site.')];
    }
    return $errors;
}

/**
 * Whether a page was printed by this site: it carries the RSD link's
 * address while wp_head prints that link, else the REST API link's while
 * wp_head prints that one, matched from the "//" on (any scheme, case
 * kept). Null when wp_head prints neither.
 */
function wp_is_local_html_output($html)
{
    if (has_action('wp_head', 'rsd_link')) {
        $address = site_url('xmlrpc.php?rsd', 'rpc');
    } elseif (has_action('wp_head', 'rest_output_link_wp_head')) {
        $address = get_rest_url();
    } else {
        return null;
    }
    return str_contains($html, strstr($address, '//') ?: $address);
}

/**
 * Keeps the migration flag as the home URL changes (on update_option_home):
 * a new home that is the old one on https (or the same, trailing slashes
 * aside) sets it, true unless the site is fresh; any other change deletes
 * it. Nothing while WordPress installs.
 */
function wp_update_https_migration_required($old_url, $new_url)
{
    if (wp_installing()) {
        return;
    }
    if (untrailingslashit((string) $old_url) !== untrailingslashit(str_replace('https://', 'http://', (string) $new_url))) {
        delete_option('https_migration_required');
        return;
    }
    update_option('https_migration_required', !get_option('fresh_site'));
}

/**
 * Moves the home and site URLs to https (each http:// in them made
 * https://), and puts both back when the site still does not count as on
 * https afterwards. Whether the move held.
 */
function wp_update_urls_to_https()
{
    $home = get_option('home');
    $site = get_option('siteurl');
    update_option('home', str_replace('http://', 'https://', $home));
    update_option('siteurl', str_replace('http://', 'https://', $site));
    if (wp_is_using_https()) {
        return true;
    }
    update_option('home', $home);
    update_option('siteurl', $site);
    return false;
}

/** Whether content should have the site's http address made https: a site moved to https asked for it. */
function wp_should_replace_insecure_home_url()
{
    $should = wp_is_using_https() && get_option('https_migration_required') && wp_parse_url(home_url(), PHP_URL_HOST) === wp_parse_url(site_url(), PHP_URL_HOST);
    return (bool) apply_filters('wp_should_replace_insecure_home_url', $should);
}

/** The site's own http address made https in content, once the site moved to https. */
function wp_replace_insecure_home_url($content)
{
    if (!wp_should_replace_insecure_home_url()) {
        return $content;
    }
    $https = home_url('', 'https');
    return TextFilters::secureHome((string) $content, str_replace('https://', 'http://', $https), $https);
}
