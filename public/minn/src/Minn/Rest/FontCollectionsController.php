<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Objects\Errors;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * wp/v2/font-collections (probe: the font library routes on the
 * reference): the collections registered with the font library, in the
 * order they were registered, a page at a time, and one by its slug. Only
 * someone who may edit theme options reads them, refused as
 * rest_cannot_read. A collection shows its slug, then its name,
 * description, font families and categories as its data holds them, the
 * same in every context, through rest_prepare_font_collection. The
 * reference registers a Google Fonts collection read from wordpress.org;
 * the engine registers none of its own (wp-api/fonts.php), so the list
 * holds what plugins register.
 */
final readonly class FontCollectionsController
{
    private const REFUSAL = 'Sorry, you are not allowed to access font collections.';
    private const FIELDS = ['name', 'description', 'font_families', 'categories'];

    public function __construct(private RestUrl $url)
    {
    }

    /** The registered font collections, a page at a time. */
    #[Route(Method::Get, '/wp/v2/font-collections', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::REFUSAL, refuse: 'rest_cannot_read', message: self::REFUSAL), args: [FontArgs::COLLECTIONS])]
    public function list(Request $request): Response
    {
        $collections = array_values(self::collections());
        $perPage = max(1, (int) ($request->query['per_page'] ?? 10));
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $total = count($collections);
        $pages = (int) ceil($total / $perPage);
        if ($page > $pages && $total > 0) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        $items = [];
        // A page past the last of none holds nothing (and a huge one is no offset to compute).
        $shown = $page > $pages ? [] : array_slice($collections, ($page - 1) * $perPage, $perPage);
        foreach ($shown as $collection) {
            $data = $collection->get_data();
            if (!$data instanceof Errors) {
                $items[] = $this->item($collection, (array) $data);
            }
        }
        return Reply::list($items, $total, $pages, Fields::fromQuery($request->query));
    }

    /** One registered font collection, by its slug. */
    #[Route(Method::Get, '/wp/v2/font-collections/{slug:[\/\w-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::REFUSAL, refuse: 'rest_cannot_read', message: self::REFUSAL), args: [FontArgs::COLLECTION])]
    public function single(Request $request, string $slug): Response
    {
        $collection = self::collections()[$slug] ?? null;
        if (!is_object($collection)) {
            throw new RestError('rest_font_collection_not_found', 'Font collection not found.', 404);
        }
        $data = $collection->get_data();
        if ($data instanceof Errors) {
            throw new RestError((string) $data->get_error_code(), (string) $data->get_error_message(), 500);
        }
        return Reply::answer($request, $this->item($collection, (array) $data));
    }

    /**
     * The collections registered with the font library (WP_Font_Library),
     * which keeps its one instance on the runtime; none before anything
     * registers one.
     *
     * @return array<string, object>
     */
    private static function collections(): array
    {
        $library = Runtime::current()->get('font_library');
        return is_object($library) ? (array) $library->get_font_collections() : [];
    }

    /**
     * A collection as wp/v2 shapes it, through rest_prepare_font_collection.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function item(object $collection, array $data): array
    {
        $slug = (string) $collection->slug;
        $item = ['slug' => $slug] + array_intersect_key(array_replace(array_flip(self::FIELDS), $data), $data);
        $item['_links'] = [
            'self' => [Links::item('self', $this->url->to("/wp/v2/font-collections/{$slug}"), ['targetHints' => ['allow' => ['GET']]])],
            'collection' => [Links::item('collection', $this->url->to('/wp/v2/font-collections'), [])],
        ];
        return RuntimePrepare::item('rest_prepare_font_collection', $item, static fn (): object => $collection);
    }
}
