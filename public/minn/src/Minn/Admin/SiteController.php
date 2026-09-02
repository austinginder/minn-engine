<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Media\Metadata;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\Support\Serialized;
use Minn\Theme\Theme;

/**
 * The small Settings-view routes of minn-admin/v1: the site logo, the
 * permalink structure, the spam queue, and the months the library spans.
 */
final readonly class SiteController
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
        private Caller $caller,
    ) {
    }

    /** The site logo attachment. */
    #[Route(Method::Get, '/minn-admin/v1/site-logo', policy: new Policy(Access::Floor))]
    public function siteLogo(Request $request): Response
    {
        // The app treats every block theme as logo-capable (its Site Logo
        // block manages one whether or not the theme declares support);
        // a classic theme needs the declared custom-logo support.
        $supported = Theme::active($this->site, $this->permalinks, ABSPATH . 'wp-content/themes') !== null
            || (\function_exists('current_theme_supports') && \current_theme_supports('custom-logo'));
        $id = 0;
        if ($supported) {
            $mods = Serialized::decode((string) ($this->db->option('theme_mods_' . (string) ($this->db->option('stylesheet') ?? '')) ?? ''));
            $id = (int) ($mods['custom_logo'] ?? 0);
        }
        return Reply::answer($request, ['supported' => $supported, 'id' => $id, 'url' => $id > 0 ? $this->logoUrl($id) : '']);
    }

    /** The custom logo's medium rendition, the size the app's card shows. */
    private function logoUrl(int $id): string
    {
        $meta = Metadata::parse((string) ($this->posts->meta($id, '_wp_attachment_metadata') ?? ''));
        $file = (string) ($meta['file'] ?? '');
        if ($file === '') {
            return '';
        }
        $sized = $meta['sizes']['medium']['file'] ?? null;
        $rel = $sized !== null ? (str_contains($file, '/') ? dirname($file) . '/' : '') . $sized : $file;
        return $this->permalinks->url('/wp-content/uploads/' . $rel);
    }

    /** The permalink structure. */
    #[Route(Method::Get, '/minn-admin/v1/permalinks', policy: new Policy(Access::Floor))]
    public function permalinks(Request $request): Response
    {
        $this->caller->requireCap('manage_options');
        $structure = $this->permalinks->structure;
        return Reply::answer($request, [
            'structure' => $structure,
            'category_base' => (string) ($this->site->option('category_base') ?? ''),
            'tag_base' => (string) ($this->site->option('tag_base') ?? ''),
            'pretty' => $structure !== '',
            'app_url' => $this->permalinks->url($structure !== '' ? '/minn-admin/' : '/?minn_admin=1'),
        ]);
    }

    /** The spam settings. */
    #[Route(Method::Get, '/minn-admin/v1/spam', policy: new Policy(Access::Floor))]
    public function spam(Request $request): Response
    {
        $this->caller->requireCap('moderate_comments');
        $counts = ['spam' => 0, 'pending' => 0];
        $rows = $this->db->rows(
            "SELECT comment_approved, COUNT(*) AS c FROM {$this->db->table('comments')}
             WHERE comment_approved IN ('spam', '0') GROUP BY comment_approved",
        );
        foreach ($rows as $row) {
            $counts[$row['comment_approved'] === 'spam' ? 'spam' : 'pending'] = (int) $row['c'];
        }
        return Reply::answer($request, [
            'providers' => [],
            'queue' => $counts,
            'disallowed_keys' => (string) ($this->site->option('disallowed_keys') ?? ''),
        ]);
    }

    /** The months the library has uploads in. */
    #[Route(Method::Get, '/minn-admin/v1/media/months', policy: new Policy(Access::Floor))]
    public function mediaMonths(Request $request): Response
    {
        $rows = $this->db->rows(
            "SELECT DATE_FORMAT(post_date, '%Y-%m') AS ym, COUNT(*) AS c FROM {$this->db->table('posts')}
             WHERE post_type = 'attachment' AND post_status = 'inherit' GROUP BY ym ORDER BY ym DESC",
        );
        return Reply::answer($request, array_map(static fn (array $row) => ['value' => $row['ym'], 'count' => (int) $row['c']], $rows));
    }
}
