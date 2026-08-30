<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Inventory;
use Minn\Content\Site;
use Minn\Db;
use Minn\Extension\Loader;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Fields;
use Minn\Rest\Reply;
use Minn\Rest\Taxonomies;
use Minn\Rest\Types;
use Minn\RestError;
use Minn\Support\FileHeaders;

/**
 * The Manage half of minn-admin/v1: the Structure view (post types,
 * taxonomies, the terms switcher), the Extensions view's theme
 * inventory, the update slots (always empty: the engine has no update
 * channel to poll), the bundled changelog and guide, and the person's
 * appearance. Everything answers from the registries, the theme folders,
 * and the app bundle on disk; nothing calls out.
 */
final readonly class ManageController
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Types $types,
        private Taxonomies $taxonomies,
        private Loader $extensions,
        private Inventory $inventory,
        private Permalinks $permalinks,
        private App $app,
        private Appearance $appearance,
        private HiddenIntegrations $hiddenIntegrations,
        private Updates $updates,
        private Caller $caller,
        private string $contentDir,
    ) {
    }

    #[Route(Method::Get, '/minn-admin/v1/term-taxonomies')]
    public function termTaxonomies(Request $request): Response
    {
        $this->requireFloor();
        $public = $this->publicTypes();
        $out = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if (!$taxonomy['visibility']['show_ui'] || !$this->caller->can($taxonomy['capabilities']['manage_terms'])) {
                continue;
            }
            if (array_intersect($taxonomy['types'], $public) === []) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'rest' => $taxonomy['rest_base'],
                'label' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'item' => strtolower(html_entity_decode($taxonomy['labels']['singular_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'hierarchical' => (bool) $taxonomy['hierarchical'],
                'canDelete' => $this->caller->can($taxonomy['capabilities']['delete_terms']),
                'canEdit' => $this->caller->can($taxonomy['capabilities']['edit_terms']),
                'count' => $this->termCount($slug),
                'types' => array_values($taxonomy['types']),
            ];
        }
        $rank = ['category' => 0, 'post_tag' => 1];
        usort($out, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['slug']] ?? 2;
            $rb = $rank[$b['slug']] ?? 2;
            return $ra !== $rb ? $ra <=> $rb : strcasecmp($a['label'], $b['label']);
        });
        return $this->reply($request, $out);
    }

    /** The Structure view's post types: core and site-declared, with their live counts. */
    #[Route(Method::Get, '/minn-admin/v1/post-types')]
    public function postTypes(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('manage_options');
        $admin = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/types-admin.json'), true);
        $out = [];
        foreach ($this->types->all() as $slug => $type) {
            if (str_starts_with($slug, 'wp_') || in_array($slug, ['attachment', 'nav_menu_item'], true)) {
                continue;
            }
            $declared = $this->types->isDeclared($slug);
            $supports = $declared
                ? ['title', 'editor']
                : array_keys(array_filter((array) ($admin[$slug]['supports'] ?? ['title' => true, 'editor' => true])));
            $out[] = [
                'slug' => $slug,
                'plural' => (string) $type['name'],
                'singular' => (string) ($admin[$slug]['labels']['singular_name'] ?? $type['name']),
                'description' => (string) $type['description'],
                'public' => true,
                'hierarchical' => (bool) $type['hierarchical'],
                'has_archive' => (bool) $type['has_archive'],
                'show_in_rest' => true,
                'rest_base' => (string) $type['rest_base'],
                'supports' => $supports,
                // The reference registers post_format on posts; wp/v2/types hides it (not in REST), the Structure view lists it.
                'taxonomies' => array_values($slug === 'post' ? [...$type['taxonomies'], 'post_format'] : $type['taxonomies']),
                'count' => $this->postCount($slug),
                'source' => $declared ? 'minn' : 'core',
                'editable' => false,
            ];
        }
        $catalog = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if ($taxonomy['visibility']['show_ui'] && !in_array($slug, ['nav_menu', 'wp_pattern_category', 'post_format'], true)) {
                $catalog[] = ['slug' => $slug, 'label' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')];
            }
        }
        return $this->reply($request, ['types' => $out, 'backends' => [], 'taxCatalog' => $catalog]);
    }

    #[Route(Method::Get, '/minn-admin/v1/taxonomies')]
    public function taxonomies(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('manage_options');
        $out = [];
        foreach ($this->taxonomies->all() as $slug => $taxonomy) {
            if (!$taxonomy['visibility']['show_ui'] || in_array($slug, ['nav_menu', 'wp_pattern_category', 'post_format'], true)) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'plural' => html_entity_decode($taxonomy['labels']['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'singular' => html_entity_decode($taxonomy['labels']['singular_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'public' => (bool) $taxonomy['visibility']['public'],
                'hierarchical' => (bool) $taxonomy['hierarchical'],
                'show_in_rest' => true,
                'object_types' => array_values($taxonomy['types']),
                'count' => $this->termCount($slug),
                'source' => 'core',
                'editable' => false,
            ];
        }
        return $this->reply($request, ['taxonomies' => $out, 'backends' => []]);
    }

    #[Route(Method::Get, '/minn-admin/v1/themes')]
    public function themes(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('switch_themes');
        $active = (string) ($this->site->option('stylesheet') ?? '');
        $offers = $this->caller->can('update_themes') ? $this->updates->themeOffers() : [];
        $auto = $this->updates->auto('theme');
        $items = [];
        foreach ($this->themeFolders() as $slug => $headers) {
            $items[] = [
                'stylesheet' => $slug,
                'name' => self::themeText($headers['Theme Name']),
                'version' => $headers['Version'],
                'author' => self::themeText($headers['Author']),
                'author_uri' => $headers['Author URI'],
                'theme_uri' => $headers['Theme URI'],
                'screenshot' => $this->screenshot($slug),
                'active' => $slug === $active,
                'parent' => $headers['Template'] === '' ? null : $headers['Template'],
                'on_wporg' => $this->updates->themeOnDirectory($slug),
                'network' => false,
                'update' => $offers[$slug] ?? null,
                'block' => is_file("{$this->contentDir}/themes/{$slug}/templates/index.html"),
                'auto_update' => in_array($slug, $auto, true),
            ];
        }
        usort($items, static fn (array $a, array $b): int => ($b['active'] <=> $a['active']) ?: strcasecmp($a['name'], $b['name']));
        return $this->reply($request, ['themes' => $items, 'auto_updates' => $this->caller->can('update_themes')]);
    }

    #[Route(Method::Post, '/minn-admin/v1/themes/activate')]
    public function activateTheme(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('switch_themes');
        $stylesheet = trim((string) ($request->json()['stylesheet'] ?? $request->form['stylesheet'] ?? ''));
        $folders = $this->themeFolders();
        if ($stylesheet === '' || !isset($folders[$stylesheet])) {
            throw new RestError('not_found', 'Theme not found.', 404);
        }
        $template = $folders[$stylesheet]['Template'];
        if ($template !== '' && !isset($folders[$template])) {
            throw new RestError('not_found', 'The parent theme is not installed.', 404);
        }
        $this->site->setOption('stylesheet', $stylesheet);
        $this->site->setOption('template', $template === '' ? $stylesheet : $template);
        $this->site->setOption('current_theme', $folders[$stylesheet]['Theme Name']);
        return $this->reply($request, ['active' => $stylesheet]);
    }

    #[Route(Method::Get, '/minn-admin/v1/translations')]
    public function translations(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, ['count' => 0, 'groups' => []]);
    }

    #[Route(Method::Get, '/minn-admin/v1/changelog')]
    public function changelog(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, $this->bundled('changelog.md'));
    }

    #[Route(Method::Get, '/minn-admin/v1/guide')]
    public function guide(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, $this->bundled('docs/user-guide.md'));
    }

    #[Route(Method::Get, '/minn-admin/v1/me/appearance')]
    public function myAppearance(Request $request): Response
    {
        return $this->reply($request, $this->appearance->read($this->requireFloor()));
    }

    #[Route(Method::Post, '/minn-admin/v1/me/appearance')]
    public function saveMyAppearance(Request $request): Response
    {
        return $this->reply($request, $this->appearance->save($this->requireFloor(), $this->appearanceBody($request)));
    }

    #[Route(Method::Get, '/minn-admin/v1/users/{id:\d+}/appearance')]
    public function userAppearance(Request $request, string $id): Response
    {
        return $this->reply($request, $this->appearance->read($this->editableUser($id)));
    }

    #[Route(Method::Post, '/minn-admin/v1/users/{id:\d+}/appearance')]
    public function saveUserAppearance(Request $request, string $id): Response
    {
        return $this->reply($request, $this->appearance->save($this->editableUser($id), $this->appearanceBody($request)));
    }

    /** The target user's restore list, for the user edit page. */
    #[Route(Method::Get, '/minn-admin/v1/users/{id:\d+}/hidden')]
    public function hidden(Request $request, string $id): Response
    {
        return $this->reply($request, ['hidden' => $this->hiddenIntegrations->listFor($this->editableUser($id))]);
    }

    /** An administrator restores something another person hid; hiding stays that person's own choice. */
    #[Route(Method::Post, '/minn-admin/v1/users/{id:\d+}/integrations/unhide')]
    public function unhideForUser(Request $request, string $id): Response
    {
        $userId = $this->editableUser($id);
        $integration = $request->json()['integration'] ?? $request->query('integration');
        if (!is_string($integration) || $integration === '') {
            throw RestError::missingParams(['integration']);
        }
        $this->hiddenIntegrations->unhide($userId, HiddenIntegrations::sanitize($integration));
        return $this->reply($request, ['ok' => true, 'hidden' => $this->hiddenIntegrations->listFor($userId)]);
    }

    #[Route(Method::Post, '/minn-admin/v1/integrations/hide')]
    public function hide(Request $request): Response
    {
        $userId = $this->requireFloor();
        if (!$this->hiddenIntegrations->hide($userId, $this->integrationId($request))) {
            throw new RestError('minn_unknown_integration', 'That integration is not registered.', 400);
        }
        return $this->reply($request, $this->integrationState($userId));
    }

    #[Route(Method::Post, '/minn-admin/v1/integrations/unhide')]
    public function unhide(Request $request): Response
    {
        $userId = $this->requireFloor();
        $this->hiddenIntegrations->unhide($userId, $this->integrationId($request));
        return $this->reply($request, $this->integrationState($userId));
    }

    private function integrationId(Request $request): string
    {
        $id = $request->json()['id'] ?? $request->query('id');
        if (!is_string($id) || $id === '') {
            throw RestError::missingParams(['id']);
        }
        return HiddenIntegrations::sanitize($id);
    }

    /**
     * The boot slices a hide or unhide repaints from. The engine registers no
     * plugin surfaces, editor panels, design sources, or block forms.
     */
    private function integrationState(int $userId): array
    {
        return [
            'ok' => true,
            'surfaces' => [],
            'editorPanels' => [],
            'hidden' => $this->hiddenIntegrations->listFor($userId),
            'designs' => [],
            'editorCommands' => [],
            'blockForms' => [],
            'insertBlocks' => [],
        ];
    }

    /** @return array{version: string, markdown: string} */
    private function bundled(string $relative): array
    {
        $file = $this->app->file($relative);
        return ['version' => $this->app->version(), 'markdown' => $file === null ? '' : (string) file_get_contents($file)];
    }

    private function appearanceBody(Request $request): array
    {
        $body = $request->json();
        return $body === [] ? $request->form : $body;
    }

    private function editableUser(string $id): int
    {
        $self = $this->requireFloor();
        $userId = (int) $id;
        if ($userId !== $self && !$this->caller->can('edit_users')) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
        return $userId;
    }

    /** A theme header as the reference serves it: tags stripped, a bare ampersand entity-encoded. */
    private static function themeText(string $value): string
    {
        return (string) preg_replace('/&(?!(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);)/i', '&amp;', strip_tags($value));
    }

    /** @return array<string, array<string, string>> slug => style.css headers */
    private function themeFolders(): array
    {
        $dir = "{$this->contentDir}/themes";
        $out = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
            if ($entry[0] === '.' || !is_dir("{$dir}/{$entry}")) {
                continue;
            }
            $headers = FileHeaders::values("{$dir}/{$entry}/style.css", ['Theme Name', 'Version', 'Author', 'Author URI', 'Theme URI', 'Template']);
            if ($headers['Theme Name'] !== '') {
                $out[$entry] = $headers;
            }
        }
        return $out;
    }

    private function screenshot(string $slug): string
    {
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
            if (is_file("{$this->contentDir}/themes/{$slug}/screenshot.{$ext}")) {
                return $this->permalinks->url("/wp-content/themes/{$slug}/screenshot.{$ext}");
            }
        }
        return '';
    }

    /** @return list<string> */
    private function publicTypes(): array
    {
        return array_values(array_filter(array_keys($this->types->all()), static fn (string $slug) => !str_starts_with($slug, 'wp_') && $slug !== 'nav_menu_item'));
    }

    private function termCount(string $taxonomy): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ?", [$taxonomy]);
    }

    private function postCount(string $type): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status IN ('publish', 'future', 'draft', 'pending', 'private')",
            [$type],
        );
    }

    private function reply(Request $request, mixed $data): Response
    {
        return Reply::item($data, Fields::fromQuery($request->query));
    }

    private function requireFloor(): int
    {
        $userId = $this->caller->require('rest_forbidden', 'Sorry, you are not allowed to do that.')->id();
        $this->requireCap('edit_posts');
        return $userId;
    }

    private function requireCap(string $capability): void
    {
        if (!$this->caller->can($capability)) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
    }
}
