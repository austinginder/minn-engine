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
use Minn\Runtime\Registry;
use Minn\Runtime\Runtime;

/**
 * wp/v2/statuses as the reference answers it (probe rest-statuses): every
 * post status that is not internal, a plugin's beside core's, by name,
 * and trash last. A visitor sees the public ones; someone who can edit a
 * type shown in REST sees them all, and only they may ask for the edit
 * context of the list.
 * Each status links to its posts and goes through rest_prepare_status.
 * Like wp/v2/types, the list is keyed by name, so _fields over the whole
 * of it keeps nothing.
 */
final readonly class StatusesController
{
    /** The fields each context shows, in the reference's order. */
    private const FIELDS = [
        'embed' => ['name', 'slug'],
        'view' => ['name', 'public', 'queryable', 'slug', 'date_floating'],
        'edit' => ['name', 'private', 'protected', 'public', 'queryable', 'show_in_list', 'slug', 'date_floating'],
    ];

    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** The statuses the caller may see. */
    #[Route(Method::Get, '/wp/v2/statuses', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function list(Request $request): Response
    {
        $context = self::context($request);
        if ($context === 'edit' && !$this->editsAnyType()) {
            throw $this->caller->refuse('rest_cannot_view', 'Sorry, you are not allowed to manage post statuses.');
        }
        $out = [];
        $statuses = self::statuses();
        // Trash is internal, yet listed, and last.
        $listed = array_filter($statuses, static fn (array $status): bool => !$status['internal']) + array_intersect_key($statuses, ['trash' => true]);
        foreach ($listed as $name => $status) {
            if ($this->readable((string) $name, $status)) {
                $out[$name] = $this->item((string) $name, $status, self::FIELDS[$context]);
            }
        }
        return Reply::item($out, Fields::fromQuery($request->query));
    }

    /** One status. */
    #[Route(Method::Get, '/wp/v2/statuses/{status:[\w-]+}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function single(Request $request, string $status): Response
    {
        $found = self::statuses()[$status] ?? null;
        if ($found === null) {
            throw new RestError('rest_status_invalid', 'Invalid status.', 404);
        }
        if (!$this->readable($status, $found)) {
            throw $this->caller->refuse('rest_cannot_read_status', 'Cannot view status.');
        }
        $fields = self::FIELDS[self::context($request)];
        $asked = Fields::fromQuery($request->query)?->paths;
        return Reply::item($this->item($status, $found, $asked === null ? $fields : array_values(array_intersect($fields, $asked))), null);
    }

    /**
     * A status as the reference shows it, its link to its posts after its fields.
     *
     * @param array<string, mixed> $status
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function item(string $name, array $status, array $fields): array
    {
        $all = [
            'name' => (string) $status['label'],
            'private' => (bool) $status['private'],
            'protected' => (bool) $status['protected'],
            'public' => (bool) $status['public'],
            'queryable' => (bool) $status['publicly_queryable'],
            'show_in_list' => (bool) $status['show_in_admin_all_list'],
            'slug' => $name,
            'date_floating' => (bool) $status['date_floating'],
        ];
        $item = array_intersect_key($all, array_flip($fields));
        $item['_links'] = ['archives' => [['href' => $this->url->to('/wp/v2/posts', $name === 'publish' ? [] : ['status' => $name])]]];
        return RuntimePrepare::item('rest_prepare_status', $item, static fn () => \get_post_status_object($name));
    }

    /**
     * Whether the caller may see a status: a public one, or (for someone
     * who edits a type shown in REST) any but an internal one; trash counts
     * as not internal.
     *
     * @param array<string, mixed> $status
     */
    private function readable(string $name, array $status): bool
    {
        return $status['public'] || ((!$status['internal'] || $name === 'trash') && $this->editsAnyType());
    }

    /** Whether the caller can edit posts of some type shown in REST. */
    private function editsAnyType(): bool
    {
        $caps = Runtime::booted()
            ? array_unique(array_map(static fn ($type) => (string) $type->cap->edit_posts, \get_post_types(['show_in_rest' => true], 'objects')))
            : ['edit_posts', 'edit_pages', 'edit_theme_options'];
        foreach ($caps as $cap) {
            if ($this->caller->can($cap)) {
                return true;
            }
        }
        return false;
    }

    /** Every registered status: a plugin's too once plugins are loaded. @return array<string, array<string, mixed>> */
    private static function statuses(): array
    {
        return Runtime::booted() ? Runtime::registry()->statuses() : (new Registry(MINN_ENGINE_DIR))->statuses();
    }

    private static function context(Request $request): string
    {
        $context = (string) ($request->query['context'] ?? 'view');
        return isset(self::FIELDS[$context]) ? $context : 'view';
    }
}
