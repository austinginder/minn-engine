<?php

/**
 * What the engine asks of this layer through Minn\Runtime\Handoff: the
 * records it makes and looks up, and what runs here by its own names. Kept
 * out of load.php, which a page cache's advanced-cache.php loads early
 * (bootstrap.php): a binding is there only once the classes it makes are,
 * so a check like Handoff::bound('sessionTokens') before boot reads false.
 */

// The engine makes and looks up this layer's records through Minn\Runtime\Handoff, never by their class names.
Minn\Runtime\Handoff::bind([
    'newError' => static fn (...$args) => new WP_Error(...$args),
    'newPost' => static fn ($post) => new WP_Post($post),
    'postInstance' => static fn ($id) => WP_Post::get_instance($id),
    'newTerm' => static fn ($term) => new WP_Term($term),
    'newComment' => static fn ($comment) => new WP_Comment($comment),
    'commentInstance' => static fn ($id) => WP_Comment::get_instance($id),
    'newUser' => static fn (...$args) => new WP_User(...$args),
    'newDependency' => static fn (...$args) => new _WP_Dependency(...$args),
]);

// The queries, made through Minn\Runtime\Handoff like the records.
Minn\Runtime\Handoff::bind([
    'newQuery' => static fn (...$args) => new WP_Query(...$args),
    'newCommentQuery' => static fn (...$args) => new WP_Comment_Query(...$args),
    'newUserQuery' => static fn (...$args) => new WP_User_Query(...$args),
    'newTermQuery' => static fn (...$args) => new WP_Term_Query(...$args),
    'newMetaQuery' => static fn (...$args) => new WP_Meta_Query(...$args),
    'newDateQuery' => static fn (...$args) => new WP_Date_Query(...$args),
]);

// REST requests, responses and the controllers a filter is handed, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'newRestRequest' => static fn (...$args) => new WP_REST_Request(...$args),
    'restRequestFromUrl' => static fn ($url) => WP_REST_Request::from_url($url),
    'newRestResponse' => static fn (...$args) => new WP_REST_Response(...$args),
    'restController' => static fn (string $kind, ...$args) => match ($kind) {
        'menus' => new WP_REST_Menus_Controller(...$args),
        'menu-items' => new WP_REST_Menu_Items_Controller(...$args),
        'menu-locations' => new WP_REST_Menu_Locations_Controller(...$args),
    },
]);

// Themes, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['newTheme' => static fn (...$args) => new WP_Theme(...$args)]);

// A registered type's or taxonomy's object, made empty through Minn\Runtime\Handoff for the registry to fill.
Minn\Runtime\Handoff::bind([
    'blankPostType' => static fn () => (new ReflectionClass(WP_Post_Type::class))->newInstanceWithoutConstructor(),
    'blankTaxonomy' => static fn () => (new ReflectionClass(WP_Taxonomy::class))->newInstanceWithoutConstructor(),
]);

// Sessions, the locale and the upgraders, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'sessionTokens' => static fn ($user_id) => WP_Session_Tokens::get_instance($user_id),
    'newLocale' => static fn () => new WP_Locale(),
    'newTextdomainRegistry' => static fn () => new WP_Textdomain_Registry(),
    'newUpgrader' => static fn ($kind, $skin) => $kind === 'theme' ? new Theme_Upgrader($skin) : new Plugin_Upgrader($skin),
    'newUpgraderSkin' => static fn (...$args) => new WP_Ajax_Upgrader_Skin(...$args),
]);

// Blocks and their registries, theme.json layers and the sitemap stylesheet, made and found through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'newBlock' => static fn (...$args) => new WP_Block(...$args),
    'newBlockList' => static fn (...$args) => new WP_Block_List(...$args),
    'blockChildContext' => static fn ($block) => Closure::bind(static fn (WP_Block $parent): array => $parent->child_context(), null, WP_Block::class)($block),
    'adoptBlock' => static fn ($block, $parsed, $context) => Closure::bind(static fn (WP_Block $inner) => $inner->adopt($parsed, $context), null, WP_Block::class)($block),
    'newBlockTemplate' => static fn (...$args) => new WP_Block_Template(...$args),
    'newTagProcessor' => static fn (...$args) => new WP_HTML_Tag_Processor(...$args),
    'newThemeJsonData' => static fn (...$args) => new WP_Theme_JSON_Data(...$args),
    'newSitemapStylesheet' => static fn (...$args) => new WP_Sitemaps_Stylesheet(...$args),
    'blockTypes' => static fn () => WP_Block_Type_Registry::get_instance(),
    'blockPatterns' => static fn () => WP_Block_Patterns_Registry::get_instance(),
    'blockStyles' => static fn () => WP_Block_Styles_Registry::get_instance(),
    'blockBindings' => static fn () => WP_Block_Bindings_Registry::get_instance(),
    'abilities' => static fn () => WP_Abilities_Registry::get_instance(),
    'abilityCategories' => static fn () => WP_Ability_Categories_Registry::get_instance(),
]);

// A comment block's template tags, as the engine's comment blocks ask them through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['commentTag' => static fn (string $tag, ...$args) => match ($tag) {
    'author' => get_comment_author(...$args),
    'authorUrl' => get_comment_author_url(...$args),
    'text' => apply_filters('comment_text', get_comment_text(...$args), get_comment($args[0] ?? null), $args[1] ?? []),
    'date' => get_comment_date(...$args),
    'link' => get_comment_link(...$args),
    'replyLink' => get_comment_reply_link(...$args),
    'class' => get_comment_class(...$args),
    'open' => comments_open(...$args),
}]);

// The menu a navigation naming none falls back to, made when there is none, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['navigationFallback' => static fn () => ($menu = WP_Navigation_Fallback::get_fallback()) instanceof WP_Post ? $menu->to_array() : null]);

// The site's roles object, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['roles' => static fn () => wp_roles()]);

// The content filters, set again by the names plugins remove them by, through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['resetKses' => static function (): void {
    kses_remove_filters();
    kses_init_filters();
}]);

// A theme template runs in this layer's globals, so the engine asks for it through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['loadTemplate' => static fn (...$args) => load_template(...$args)]);

// The translation controller, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['translationController' => static fn () => WP_Translation_Controller::get_instance()]);

// The rewrite object, the widget factory, the sitemap server and the menu walker, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'rewrite' => static fn () => _minn_rewrite(),
    'widgetFactory' => static fn () => _minn_widget_factory(),
    'sitemaps' => static fn () => wp_sitemaps_get_server(),
    'newMenuWalker' => static fn () => new Walker_Nav_Menu(),
]);

// An attachment's metadata is made by the image editors plugins choose, so the engine asks for it through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['attachmentMetadata' => static fn ($attachment_id, $file) => wp_generate_attachment_metadata($attachment_id, $file), 'uploadBits' => static fn ($name, $bits) => wp_upload_bits($name, null, $bits)]);

// What runs in this layer by its own names, asked for by the engine through Minn\Runtime\Handoff: the meta
// registered on init, a query's template, the block templates, and hooked blocks inserted into content.
Minn\Runtime\Handoff::bind([
    'registerInitMeta' => static function (): void {
        wp_create_initial_post_meta();
        wp_create_initial_comment_meta();
        wp_register_persisted_preferences_meta();
        _minn_register_toolbar_meta();
        register_block_core_footnotes_post_meta();
    },
    'queryTemplate' => static fn (...$args) => get_query_template(...$args),
    'blockTemplates' => static fn (...$args) => get_block_templates(...$args),
    'lazyloadTermMeta' => static fn (array $ids) => wp_lazyload_term_meta($ids),
    'convertSmilies' => static fn ($text) => convert_smilies($text),
    'blockHooks' => static fn (...$args) => apply_block_hooks_to_content(...$args),
    'blockHooksForPost' => static fn ($content, $post, $callback, &$ignored) => apply_block_hooks_to_content_from_post_object($content, $post, $callback, $ignored),
]);
