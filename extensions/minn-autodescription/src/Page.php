<?php

declare(strict_types=1);

namespace Minn\Ext\SeoFramework;

use Minn\Content\Posts;
use Minn\Content\Terms;
use Minn\Extension\Seams;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Front\Resolver;
use Minn\Http\Request;
use Minn\Media\Metadata;
use Minn\Support\Html;
use Minn\Support\Serialized;

/**
 * What the plugin printed for each kind of page, from its site settings
 * (title separator and order, the home tagline, the knowledge graph) and
 * the per-post _genesis_* meta: the title, the meta block, and the
 * schema.org graph. Written from observed output on every page type of
 * the reference site.
 */
final class Page
{
    private const ROBOTS = 'max-snippet:-1,max-image-preview:large,max-video-preview:-1';
    private const DESCRIPTION_LENGTH = 160;
    /** Social descriptions run longer than the search one. */
    private const SOCIAL_DESCRIPTION_LENGTH = 300;

    private array $settings;
    private Posts $posts;
    private Terms $terms;
    private Permalinks $permalinks;
    private ?Resolution $resolution = null;
    private string $blogName;
    private string $tagline;
    private string $home;

    public function __construct(private readonly Seams $minn)
    {
        $settings = Serialized::decode((string) ($minn->site->option('autodescription-site-settings') ?? ''));
        $this->settings = is_array($settings) ? $settings : [];
        $this->posts = new Posts($minn->db);
        $this->terms = new Terms($minn->db);
        $this->permalinks = Permalinks::fromDb($minn->db);
        $this->blogName = (string) ($minn->site->option('blogname') ?? '');
        $this->tagline = (string) ($minn->site->option('blogdescription') ?? '');
        $this->home = $this->permalinks->url('/');
    }

    private function resolution(): Resolution
    {
        if ($this->resolution === null) {
            $resolver = Resolver::fromDb($this->minn->db, static fn (array $post): bool => \Minn\Content\Reader::current()->canEdit((int) $post['ID']));
            $this->resolution = $resolver->resolve($this->minn->request);
        }
        return $this->resolution;
    }

    private function isHome(Resolution $r): bool
    {
        return $r->kind === Kind::Home || $r->front;
    }

    /** "{page} | {site}", the site and its tagline on the home page. */
    public function title(): ?string
    {
        $r = $this->resolution();
        $separator = ' ' . match ((string) ($this->settings['title_separator'] ?? 'pipe')) { 'pipe' => '|', 'dash' => '-', 'ndash' => '–', 'mdash' => '—', 'bull' => '•', 'middot' => '·', 'colon' => ':', default => '|' } . ' ';
        if ($this->isHome($r)) {
            $tagline = !empty($this->settings['homepage_tagline']) ? $this->tagline : '';
            $homeTitle = (string) ($this->settings['homepage_title'] ?? '') ?: $this->blogName;
            return $tagline === '' ? $homeTitle : (($this->settings['home_title_location'] ?? 'right') === 'left' ? $tagline . $separator . $homeTitle : $homeTitle . $separator . $tagline);
        }
        $name = $this->pageName($r);
        if ($name === null) {
            return null;
        }
        return ($this->settings['title_location'] ?? 'right') === 'left' ? $this->blogName . $separator . $name : $name . $separator . $this->blogName;
    }

    /** The page's own name: the SEO title when set, else what the reference labels the view. */
    private function pageName(Resolution $r): ?string
    {
        $record = $r->record ?? [];
        return match ($r->kind) {
            Kind::Single, Kind::Page => (string) ($this->posts->meta((int) $record['ID'], '_genesis_title') ?: $record['post_title']),
            Kind::Category => 'Category: ' . (string) $record['name'],
            Kind::Tag => 'Tag: ' . (string) $record['name'],
            Kind::Author => 'Author: ' . (string) ($record['display_name'] ?? $r->authorName),
            Kind::Search => 'Search Results for “' . (string) $r->search . '”',
            Kind::NotFound => 'Page not found',
            Kind::Date => 'Archives',
            default => null,
        };
    }

    public function head(): string
    {
        $r = $this->resolution();
        $record = $r->record ?? [];
        $home = $this->isHome($r);
        $singular = in_array($r->kind, [Kind::Single, Kind::Page], true);
        $noindex = in_array($r->kind, [Kind::Search, Kind::NotFound, Kind::Date], true);

        $canonical = match (true) {
            $home => $this->home,
            $singular => $this->permalinks->forPost($record),
            $r->kind === Kind::Category || $r->kind === Kind::Tag => $this->permalinks->forTerm($record),
            $r->kind === Kind::Author => $this->permalinks->forAuthor($record ?: ['ID' => 0, 'user_nicename' => $r->authorName]),
            default => null,
        };
        $ogUrl = $r->kind === Kind::Search ? $this->permalinks->url('/search/' . rawurlencode((string) $r->search) . '/') : $canonical;
        $ogTitle = $home ? $this->blogName : $this->pageName($r);
        $description = $this->description($r);
        $social = $this->description($r, self::SOCIAL_DESCRIPTION_LENGTH);
        $image = $this->image($r);

        $tags = [self::meta('name', 'robots', ($noindex ? 'noindex,' : '') . self::ROBOTS)];
        if ($canonical !== null) {
            $tags[] = '<link rel="canonical" href="' . Html::attr($canonical) . '" />';
        }
        if ($description !== null) {
            $tags[] = self::meta('name', 'description', $description);
        }
        if ($r->kind !== Kind::NotFound && !empty($this->settings['og_tags'])) {
            $tags[] = self::meta('property', 'og:type', $r->kind === Kind::Single ? 'article' : 'website');
            $tags[] = self::meta('property', 'og:locale', 'en_US');
            $tags[] = self::meta('property', 'og:site_name', $this->blogName);
            $tags[] = self::meta('property', 'og:title', (string) $ogTitle);
            if ($social !== null) {
                $tags[] = self::meta('property', 'og:description', $social);
            }
            if ($ogUrl !== null) {
                $tags[] = self::meta('property', 'og:url', $ogUrl);
            }
            if ($image !== null) {
                $tags[] = self::meta('property', 'og:image', $image['url']);
                if ($image['width'] > 0) {
                    $tags[] = self::meta('property', 'og:image:width', (string) $image['width']);
                    $tags[] = self::meta('property', 'og:image:height', (string) $image['height']);
                }
                if ($image['alt'] !== '') {
                    $tags[] = self::meta('property', 'og:image:alt', $image['alt']);
                }
            }
            if ($r->kind === Kind::Single) {
                if (!empty($this->settings['post_publish_time'])) {
                    $tags[] = self::meta('property', 'article:published_time', self::iso((string) $record['post_date_gmt']));
                }
                if (!empty($this->settings['post_modify_time'])) {
                    $tags[] = self::meta('property', 'article:modified_time', self::iso((string) $record['post_modified_gmt']));
                }
            }
        }
        if ($r->kind !== Kind::NotFound && $r->kind !== Kind::Search && !empty($this->settings['twitter_tags'])) {
            $tags[] = self::meta('name', 'twitter:card', (string) ($this->settings['twitter_card'] ?? 'summary_large_image'));
            $tags[] = self::meta('name', 'twitter:title', (string) $ogTitle);
            if ($social !== null) {
                $tags[] = self::meta('name', 'twitter:description', $social);
            }
            if ($image !== null) {
                $tags[] = self::meta('name', 'twitter:image', $image['url']);
                if ($image['alt'] !== '') {
                    $tags[] = self::meta('name', 'twitter:image:alt', $image['alt']);
                }
            }
        }
        $out = "\n<!-- The SEO Framework by Sybre Waaijer -->\n" . implode("\n", $tags) . "\n";
        if (!empty($this->settings['ld_json_enabled'])) {
            $out .= '<script type="application/ld+json">' . json_encode($this->graph($r, $description), JSON_UNESCAPED_SLASHES) . "</script>\n";
        }
        return $out . "<!-- / The SEO Framework by Sybre Waaijer | 5.1.4 -->\n\n";
    }

    private static function meta(string $attribute, string $name, string $content): string
    {
        return '<meta ' . $attribute . '="' . $name . '" content="' . Html::attr($content) . '" />';
    }

    private static function iso(string $gmt): string
    {
        return gmdate('Y-m-d\TH:i:s', (int) strtotime($gmt . ' UTC')) . '+00:00';
    }

    /** The saved description, or the first 160 characters of the paragraphs, cut on a word, with an ellipsis unless a sentence ended. */
    private function description(Resolution $r, int $length = self::DESCRIPTION_LENGTH): ?string
    {
        $record = $r->record ?? [];
        if (in_array($r->kind, [Kind::Single, Kind::Page], true)) {
            $saved = (string) ($this->posts->meta((int) $record['ID'], '_genesis_description') ?? '');
            if ($saved !== '') {
                return $saved;
            }
            if (empty($this->settings['auto_description'])) {
                return null;
            }
            return self::generate((string) $record['post_content'], $length);
        }
        if (($r->kind === Kind::Category || $r->kind === Kind::Tag) && trim((string) ($record['description'] ?? '')) !== '') {
            return trim(strip_tags((string) $record['description']));
        }
        return null;
    }

    public static function generate(string $content, int $length = self::DESCRIPTION_LENGTH): ?string
    {
        $content = (string) preg_replace('/<!--.*?-->/s', ' ', $content);
        $content = (string) preg_replace('/<h[1-6]\b[^>]*>.*?<\/h[1-6]>/is', ' ', $content);
        $content = (string) preg_replace('/<(script|style|figcaption)\b[^>]*>.*?<\/\1>/is', ' ', $content);
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return null;
        }
        $cut = mb_strlen($text) > $length;
        if ($cut) {
            $text = mb_substr($text, 0, $length);
            $text = (string) preg_replace('/\s+\S*$/u', '', $text);
        }
        $text = rtrim($text, " ,;:-–—");
        return preg_match('/[.!?]$/u', $text) && !$cut ? $text : $text . '…';
    }

    /** @return array{url: string, width: int, height: int, alt: string}|null */
    private function image(Resolution $r): ?array
    {
        $record = $r->record ?? [];
        if (in_array($r->kind, [Kind::Single, Kind::Page], true)) {
            $thumbnail = (int) ($this->posts->meta((int) $record['ID'], '_thumbnail_id') ?? 0);
            if ($thumbnail > 0) {
                $attached = $this->attachment($thumbnail, withAlt: true);
                if ($attached !== null) {
                    return $attached;
                }
            }
            if (preg_match('/<img\s[^>]*src="([^"]+)"/', (string) $record['post_content'], $m)) {
                return ['url' => $m[1], 'width' => 0, 'height' => 0, 'alt' => ''];
            }
            return null;
        }
        if ($r->kind === Kind::NotFound) {
            return null;
        }
        $icon = (int) ($this->minn->site->option('site_icon') ?? 0);
        return $icon > 0 ? $this->attachment($icon, withAlt: false) : null;
    }

    /** @return array{url: string, width: int, height: int, alt: string}|null */
    private function attachment(int $id, bool $withAlt): ?array
    {
        $file = $this->posts->meta($id, '_wp_attached_file');
        if ($file === null) {
            return null;
        }
        $meta = Metadata::parse($this->posts->meta($id, '_wp_attachment_metadata'));
        return [
            'url' => $this->permalinks->url('/wp-content/uploads/' . $file),
            'width' => (int) $meta['width'],
            'height' => (int) $meta['height'],
            'alt' => $withAlt ? (string) ($this->posts->meta($id, '_wp_attachment_image_alt') ?? '') : '',
            'size' => (int) $meta['filesize'],
        ];
    }

    /** The schema.org graph: the site, the organisation, the page with its breadcrumb. */
    private function graph(Resolution $r, ?string $description): array
    {
        $record = $r->record ?? [];
        $home = $this->isHome($r);
        $siteId = $this->home . '#/schema/WebSite';
        $orgId = $this->home . '#/schema/Organization';
        $organization = [
            '@type' => 'Organization',
            '@id' => $orgId,
            'name' => (string) ($this->settings['knowledge_name'] ?? '') ?: $this->blogName,
            'url' => $this->home,
        ];
        $sameAs = [];
        foreach (['facebook', 'twitter', 'instagram', 'youtube', 'linkedin', 'pinterest', 'soundcloud', 'tumblr'] as $network) {
            if (!empty($this->settings['knowledge_' . $network])) {
                $sameAs[] = (string) $this->settings['knowledge_' . $network];
            }
        }
        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }
        $icon = (int) ($this->minn->site->option('site_icon') ?? 0);
        $logo = !empty($this->settings['knowledge_logo']) && $icon > 0 ? $this->attachment($icon, withAlt: false) : null;
        if ($logo !== null) {
            $organization['logo'] = ['@type' => 'ImageObject', 'url' => $logo['url'], 'contentUrl' => $logo['url'], 'width' => $logo['width'], 'height' => $logo['height'], 'contentSize' => (string) $logo['size']];
        }
        $website = [
            '@type' => 'WebSite',
            '@id' => $siteId,
            'url' => $this->home,
            'name' => $this->blogName,
        ];
        if (!empty($this->settings['knowledge_name']) && $this->settings['knowledge_name'] !== $this->blogName) {
            $website['alternateName'] = (string) $this->settings['knowledge_name'];
        }
        if ($this->tagline !== '') {
            $website['description'] = $this->tagline;
        }
        $website['inLanguage'] = 'en-US';
        if (!empty($this->settings['ld_json_searchbox'])) {
            $website['potentialAction'] = ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $this->home . 'search/{search_term_string}/'], 'query-input' => 'required name=search_term_string'];
        }
        $website['publisher'] = $home ? ['@id' => $orgId] : $organization;

        $name = (string) $this->title();
        $url = match (true) {
            $home => $this->home,
            in_array($r->kind, [Kind::Single, Kind::Page], true) => $this->permalinks->forPost($record),
            $r->kind === Kind::Category || $r->kind === Kind::Tag => $this->permalinks->forTerm($record),
            $r->kind === Kind::Search => $this->permalinks->url('/search/' . rawurlencode((string) $r->search) . '/'),
            default => null,
        };
        $type = match (true) {
            $r->kind === Kind::Search => ['CollectionPage', 'SearchResultsPage'],
            $r->kind === Kind::Category || $r->kind === Kind::Tag || $r->kind === Kind::Author || $r->kind === Kind::Date => 'CollectionPage',
            default => 'WebPage',
        };
        $page = ['@type' => $type];
        if ($url !== null) {
            $page['@id'] = $url;
            $page['url'] = $url;
        }
        $page['name'] = $name;
        if ($description !== null) {
            $page['description'] = $description;
        }
        $page['inLanguage'] = 'en-US';
        $page['isPartOf'] = ['@id' => $siteId];
        $page['breadcrumb'] = ['@type' => 'BreadcrumbList', '@id' => $this->home . '#/schema/BreadcrumbList', 'itemListElement' => $this->breadcrumbs($r)];
        if ($url !== null && $r->kind !== Kind::Search && !($r->kind === Kind::Category || $r->kind === Kind::Tag)) {
            $page['potentialAction'] = ['@type' => 'ReadAction', 'target' => $url];
        }
        if ($home) {
            $page['about'] = ['@id' => $orgId];
        }
        if ($r->kind === Kind::Single) {
            $page['datePublished'] = self::iso((string) $record['post_date_gmt']);
            $page['dateModified'] = self::iso((string) $record['post_modified_gmt']);
            $author = $this->minn->db->row("SELECT ID, display_name, user_email FROM {$this->minn->db->table('users')} WHERE ID = ? LIMIT 1", [(int) $record['post_author']]);
            if ($author !== null) {
                $page['author'] = ['@type' => 'Person', '@id' => $this->home . '#/schema/Person/' . md5((string) $author['user_email']), 'name' => (string) $author['display_name']];
            }
        }
        $graph = [$website, $page];
        if ($home) {
            $graph[] = $organization;
        }
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** Home alone is one item (an object); anything else is the trail from home. */
    private function breadcrumbs(Resolution $r): array
    {
        $record = $r->record ?? [];
        if ($this->isHome($r)) {
            return ['@type' => 'ListItem', 'position' => 1, 'name' => $this->blogName];
        }
        $items = [['@type' => 'ListItem', 'position' => 1, 'item' => $this->home, 'name' => $this->blogName]];
        $middle = [];
        if ($r->kind === Kind::Page) {
            $parent = (int) ($record['post_parent'] ?? 0);
            $chain = [];
            while ($parent > 0) {
                $page = $this->posts->find($parent);
                if ($page === null) {
                    break;
                }
                array_unshift($chain, $page);
                $parent = (int) $page['post_parent'];
            }
            foreach ($chain as $page) {
                $middle[] = ['item' => $this->permalinks->forPost($page), 'name' => (string) $page['post_title']];
            }
        } elseif ($r->kind === Kind::Single) {
            $categories = $this->posts->terms((int) $record['ID'], 'category');
            $category = $categories === [] ? null : $this->terms->find('category', $categories[0][0]);
            if ($category !== null) {
                $middle[] = ['item' => $this->permalinks->forTerm($category), 'name' => 'Category: ' . (string) $category['name']];
            }
        }
        foreach ($middle as $crumb) {
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'item' => $crumb['item'], 'name' => $crumb['name']];
        }
        $last = $r->kind === Kind::Single || $r->kind === Kind::Page ? (string) $record['post_title'] : (string) $this->pageName($r);
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $last];
        return $items;
    }
}
