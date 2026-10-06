<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Runtime;
use Minn\Runtime\ThemeSupports;

/**
 * wp/v2/themes as the reference answers it (probe rest-themes): the
 * installed themes, or only those with a status (active, inactive), each
 * with its style.css headers raw and rendered, where it lives, and whether
 * it is a block theme. The active theme also carries its supports as the
 * editor reads them, the template types and part areas it may define, and
 * a link to the theme export; any theme with user styles links to them. Anyone who can edit
 * a type shown in REST may read the active theme; listing the others takes
 * switch_themes. Each theme goes through rest_prepare_theme. Served with
 * plugins loaded, since a theme's supports come from its own code.
 */
final readonly class InstalledThemesController
{
    private const STATUS = ['status' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['active', 'inactive']], 'description' => 'Limit result set to themes assigned one or more statuses.', 'required' => false]];

    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** The installed themes the caller may see. */
    #[Route(Method::Get, '/wp/v2/themes', policy: new Policy(Access::Public), args: [Args::CONTEXT, self::STATUS])]
    public function list(Request $request): Response
    {
        self::requireRuntime();
        $statuses = \wp_parse_list($request->query['status'] ?? []);
        if ($statuses === ['active'] ? !$this->viewsActive() : !$this->viewsThemes()) {
            throw $statuses === ['active']
                ? $this->caller->refuse('rest_cannot_view_active_theme', 'Sorry, you are not allowed to view the active theme.')
                : $this->caller->refuse('rest_cannot_view_themes', 'Sorry, you are not allowed to view themes.');
        }
        $items = [];
        foreach (\wp_get_themes() as $stylesheet => $theme) {
            if ($statuses === [] || in_array(self::status((string) $stylesheet), $statuses, true)) {
                $items[] = $this->item($theme);
            }
        }
        return Reply::list($items, count($items), 1, Fields::fromQuery($request->query));
    }

    /** One theme by its stylesheet. */
    #[Route(Method::Get, '/wp/v2/themes/{stylesheet:[^/:<>*?"|]+(?:/[^/:<>*?"|]+)?}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function single(Request $request, string $stylesheet): Response
    {
        self::requireRuntime();
        $active = self::status($stylesheet) === 'active';
        if ($active ? !$this->viewsActive() : !$this->viewsThemes()) {
            throw $active
                ? $this->caller->refuse('rest_cannot_view_active_theme', 'Sorry, you are not allowed to view the active theme.')
                : $this->caller->refuse('rest_cannot_view_themes', 'Sorry, you are not allowed to view themes.');
        }
        $theme = \wp_get_theme($stylesheet);
        if (!$theme->exists()) {
            throw new RestError('rest_theme_not_found', 'Theme not found.', 404);
        }
        return Reply::item($this->item($theme), Fields::fromQuery($request->query));
    }

    /** @return array<string, mixed> */
    private function item(\WP_Theme $theme): array
    {
        $stylesheet = $theme->get_stylesheet();
        $active = self::status($stylesheet) === 'active';
        $text = static fn (string $header): array => ['raw' => $theme->display($header, false), 'rendered' => $theme->display($header)];
        $item = [
            'stylesheet' => $stylesheet,
            'template' => $theme->get_template(),
            'requires_php' => (string) $theme->get('RequiresPHP'),
            'requires_wp' => (string) $theme->get('RequiresWP'),
            'textdomain' => (string) $theme->get('TextDomain'),
            'version' => (string) $theme->get('Version'),
            'screenshot' => (string) $theme->get_screenshot(),
            'author' => $text('Author'),
            'author_uri' => $text('AuthorURI'),
            'description' => $text('Description'),
            'name' => $text('Name'),
            'tags' => $text('Tags'),
            'theme_uri' => $text('ThemeURI'),
            'status' => self::status($stylesheet),
        ];
        if ($active) {
            $item['theme_supports'] = ThemeSupports::forRest(Runtime::current()->get('rest_prepare_request') ?? new \WP_REST_Request('GET', '/wp/v2/themes'));
        }
        $item += ['is_block_theme' => $theme->is_block_theme(), 'stylesheet_uri' => $theme->get_stylesheet_directory_uri(), 'template_uri' => $theme->get_template_directory_uri()];
        if ($active) {
            $item['default_template_types'] = array_map(static fn ($slug, $type) => ['title' => $type['title'], 'description' => $type['description'], 'slug' => (string) $slug], array_keys(\get_default_block_template_types()), \get_default_block_template_types());
            $item['default_template_part_areas'] = \get_allowed_block_template_part_areas();
        }
        $item['_links'] = $this->links($theme);
        return RuntimePrepare::item('rest_prepare_theme', $item, static fn () => \wp_get_theme($stylesheet));
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function links(\WP_Theme $theme): array
    {
        $stylesheet = $theme->get_stylesheet();
        $active = self::status($stylesheet) === 'active';
        $links = [
            'self' => [['href' => $this->url->to('/wp/v2/themes/' . $stylesheet), 'targetHints' => ['allow' => ['GET']]]],
            'collection' => [['href' => $this->url->to('/wp/v2/themes')]],
        ];
        // The active theme's user styles are made when missing; another theme links to its own only when it has some.
        $styles = $active ? \WP_Theme_JSON_Resolver::get_user_global_styles_post_id() : (int) (\WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($stylesheet)['ID'] ?? 0);
        if ($styles > 0) {
            $links['wp:user-global-styles'] = [['href' => $this->url->to('/wp/v2/global-styles/' . $styles)]];
        }
        if ($active && $theme->is_block_theme()) {
            $links['wp:export-theme'] = [['targetHints' => ['allow' => $this->caller->can('export') && $this->caller->can('edit_theme_options') ? ['GET'] : []], 'href' => $this->url->to('/wp-block-editor/v1/export')]];
        }
        if (count($links) > 2) {
            $links['curies'] = RestUrl::curies();
        }
        return $links;
    }

    /** May list the installed themes. */
    private function viewsThemes(): bool
    {
        return $this->caller->can('switch_themes') || $this->caller->can('manage_network_themes');
    }

    /** May read the active theme: anyone who lists themes, or edits a type shown in REST. */
    private function viewsActive(): bool
    {
        return $this->viewsThemes() || $this->caller->editsAnyRestType();
    }

    private static function status(string $stylesheet): string
    {
        return $stylesheet === \get_stylesheet() ? 'active' : 'inactive';
    }

    /** A theme's supports come from its own code: without the runtime there is no answer to give. */
    private static function requireRuntime(): void
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
    }
}
