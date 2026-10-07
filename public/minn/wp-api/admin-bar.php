<?php
/** The toolbar's functions: setting it up, printing it, whether it shows, and WordPress's own menus on it (probe admin-bar). */

use Minn\Front\ToolbarMenus;

/** @internal The bar for this request, when it shows: the class wp_admin_bar_class names, initialized, its menus hooked. */
function _wp_admin_bar_init()
{
    global $wp_admin_bar;
    if (!is_admin_bar_showing()) {
        return false;
    }
    $class = apply_filters('wp_admin_bar_class', 'WP_Admin_Bar');
    if (!class_exists($class)) {
        return false;
    }
    $wp_admin_bar = new $class();
    $wp_admin_bar->initialize();
    $wp_admin_bar->add_menus();
    return true;
}

/** The bar, once a request, when it shows: admin_bar_menu, wp_before_admin_bar_render, the markup, wp_after_admin_bar_render. */
function wp_admin_bar_render()
{
    global $wp_admin_bar;
    static $rendered = false;
    if ($rendered || !is_admin_bar_showing() || !is_object($wp_admin_bar)) {
        return;
    }
    do_action_ref_array('admin_bar_menu', [&$wp_admin_bar]);
    do_action('wp_before_admin_bar_render');
    $wp_admin_bar->render();
    do_action('wp_after_admin_bar_render');
    $rendered = true;
}

/**
 * Whether the bar shows: never for XML-RPC, AJAX, iframe or JSON requests
 * or embeds, always in the admin; otherwise as show_admin_bar() left it
 * or, failing that, for a signed-in user (off the sign-in page) who has
 * not turned it off. The show_admin_bar filter has the last word, and
 * what it says is kept for the rest of the request.
 */
function is_admin_bar_showing()
{
    global $show_admin_bar, $pagenow;
    if (defined('XMLRPC_REQUEST') || defined('DOING_AJAX') || defined('IFRAME_REQUEST') || wp_is_json_request() || is_embed()) {
        return false;
    }
    if (is_admin()) {
        return true;
    }
    if (!isset($show_admin_bar)) {
        $show_admin_bar = is_user_logged_in() && ($pagenow ?? '') !== 'wp-login.php' ? _get_admin_bar_pref() : false;
    }
    $show_admin_bar = apply_filters('show_admin_bar', $show_admin_bar);
    return $show_admin_bar;
}

/** @internal WordPress's toolbar is not shown on a page the engine serves: the Minn bar, or nothing, takes its place. */
function _minn_front_toolbar($show)
{
    return Minn\Runtime\Runtime::current()->request === null ? $show : false;
}

function show_admin_bar($show)
{
    global $show_admin_bar;
    $show_admin_bar = (bool) $show;
}

/** A user's choice to see the bar in a context ('front'): true unless they turned it off. */
function _get_admin_bar_pref($context = 'front', $user = 0)
{
    $pref = get_user_option("show_admin_bar_{$context}", $user);
    return $pref === false ? true : $pref === 'true';
}

function wp_admin_bar_header()
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_enqueue_admin_bar_header_styles');
    $type = current_theme_supports('html5', 'style') ? '' : ' type="text/css"';
    echo "\t<style{$type} media=\"print\">#wpadminbar { display:none; }</style>\n\t";
}

function _admin_bar_bump_cb()
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_enqueue_admin_bar_bump_styles');
    $type = current_theme_supports('html5', 'style') ? '' : ' type="text/css"';
    echo "\t<style{$type} media=\"screen\">\n\thtml { margin-top: 32px !important; }\n\t@media screen and ( max-width: 782px ) {\n\t  html { margin-top: 46px !important; }\n\t}\n\t</style>\n\t";
}

/** The bar hidden when printing, as an inline style, in place of the old head printer (left alone if a plugin unhooked it). */
function wp_enqueue_admin_bar_header_styles()
{
    $head = is_admin() ? 'admin_head' : 'wp_head';
    if (!has_action($head, 'wp_admin_bar_header')) {
        return;
    }
    remove_action($head, 'wp_admin_bar_header');
    wp_add_inline_style('admin-bar', '@media print { #wpadminbar { display:none; } }');
}

/** The page pushed down for the bar, as an inline style, in place of the default bump (unless the theme brings its own, or a plugin unhooked it). */
function wp_enqueue_admin_bar_bump_styles()
{
    $support = current_theme_supports('admin-bar') ? get_theme_support('admin-bar') : null;
    $bump = (is_array($support) ? ($support[0]['callback'] ?? null) : null) ?: '_admin_bar_bump_cb';
    if ($bump !== '_admin_bar_bump_cb' || !has_action('wp_head', $bump)) {
        return;
    }
    remove_action('wp_head', $bump);
    wp_add_inline_style('admin-bar', "\n\t\t@media screen { html { margin-top: 32px !important; } }\n\t\t@media screen and ( max-width: 782px ) { html { margin-top: 46px !important; } }\n\t");
}

/** The script that marks the body customize-support (or no-customize-support), for the customizer's links. */
function wp_customize_support_script()
{
    wp_print_inline_script_tag("\t\t(function() {\n\t\t\tvar request, b = document.body, c = 'className', cs = 'customize-support', rcs = new RegExp('(^|\\\\s+)(no-)?'+cs+'(\\\\s+|\$)');\n\n\t\t\t\trequest = true;\n\t\n\t\t\tb[c] = b[c].replace( rcs, ' ' );\n\t\t\t// The customizer requires postMessage and CORS (if the site is cross domain).\n\t\t\tb[c] += ( window.postMessage && request ? ' ' : ' no-' ) + cs;\n\t\t}());\n\t\n//# sourceURL=" . rawurlencode(__FUNCTION__));
}

function wp_admin_bar_my_account_item($wp_admin_bar)
{
    ToolbarMenus::myAccountItem($wp_admin_bar);
}

function wp_admin_bar_my_account_menu($wp_admin_bar)
{
    ToolbarMenus::myAccountMenu($wp_admin_bar);
}

function wp_admin_bar_sidebar_toggle($wp_admin_bar)
{
    ToolbarMenus::sidebarToggle($wp_admin_bar);
}

function wp_admin_bar_wp_menu($wp_admin_bar)
{
    ToolbarMenus::wpMenu($wp_admin_bar);
}

/** The network's sites; a single site has none. */
function wp_admin_bar_my_sites_menu($wp_admin_bar)
{
}

function wp_admin_bar_site_menu($wp_admin_bar)
{
    ToolbarMenus::siteMenu($wp_admin_bar);
}

function wp_admin_bar_appearance_menu($wp_admin_bar)
{
    ToolbarMenus::appearanceMenu($wp_admin_bar);
}

function wp_admin_bar_edit_site_menu($wp_admin_bar)
{
    ToolbarMenus::editSiteMenu($wp_admin_bar);
}

function wp_admin_bar_customize_menu($wp_admin_bar)
{
    ToolbarMenus::customizeMenu($wp_admin_bar);
}

function wp_admin_bar_updates_menu($wp_admin_bar)
{
    ToolbarMenus::updatesMenu($wp_admin_bar);
}

/** The command palette's button belongs to wp-admin, which the engine does not serve; the front end has none. */
function wp_admin_bar_command_palette_menu($wp_admin_bar)
{
}

function wp_admin_bar_comments_menu($wp_admin_bar)
{
    ToolbarMenus::commentsMenu($wp_admin_bar);
}

function wp_admin_bar_new_content_menu($wp_admin_bar)
{
    ToolbarMenus::newContentMenu($wp_admin_bar);
}

function wp_admin_bar_edit_menu($wp_admin_bar)
{
    ToolbarMenus::editMenu($wp_admin_bar);
}

function wp_admin_bar_shortlink_menu($wp_admin_bar)
{
    ToolbarMenus::shortlinkMenu($wp_admin_bar);
}

function wp_admin_bar_add_secondary_groups($wp_admin_bar)
{
    ToolbarMenus::secondaryGroups($wp_admin_bar);
}

function wp_admin_bar_recovery_mode_menu($wp_admin_bar)
{
    ToolbarMenus::recoveryModeMenu($wp_admin_bar);
}

function wp_admin_bar_search_menu($wp_admin_bar)
{
    ToolbarMenus::searchMenu($wp_admin_bar);
}

/** The old dashboard link: Visit Site in the admin, the dashboard elsewhere. */
function wp_admin_bar_dashboard_view_site_menu($wp_admin_bar)
{
    _deprecated_function(__FUNCTION__, '3.3.0');
    if (!get_current_user_id()) {
        return;
    }
    $wp_admin_bar->add_node(is_admin()
        ? ['id' => 'view-site', 'title' => __('Visit Site'), 'href' => home_url()]
        : ['id' => 'dashboard', 'title' => __('Dashboard'), 'href' => admin_url()]);
}
