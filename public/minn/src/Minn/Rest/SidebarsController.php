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
use Minn\Runtime\WidgetAreas;

/**
 * wp/v2/sidebars and wp/v2/widget-types as the reference answers them
 * (probe rest-widgets), for anyone who can edit theme options. A sidebar
 * is a registered one or the inactive widgets, with its wrapping markup,
 * the registered widgets it holds, and its status: active when registered
 * under a classic theme (a block theme renders none). Saving a sidebar's
 * widgets takes them from any other sidebar and sends the ones it drops to
 * the inactive widgets. The widget types are the registered widgets by id
 * base, in id order.
 */
final readonly class SidebarsController
{
    private const BODY = ['widgets' => ['description' => 'Nested widgets.', 'type' => 'array', 'items' => ['type' => ['object', 'string']], 'required' => false]];

    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** Every sidebar: those the sidebars option lists, then any other registered one. */
    #[Route(Method::Get, '/wp/v2/sidebars', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT])]
    public function sidebars(Request $request): Response
    {
        self::ready();
        $out = [];
        foreach (array_keys(\retrieve_widgets()) as $id) {
            if (WidgetAreas::sidebar((string) $id) !== null) {
                $out[] = $this->item((string) $id);
            }
        }
        return Reply::item($out, Fields::fromQuery($request->query));
    }

    /** One sidebar. */
    #[Route(Method::Get, '/wp/v2/sidebars/{id:[\w-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT])]
    public function sidebar(Request $request, string $id): Response
    {
        self::ready();
        self::requireSidebar($id);
        return Reply::item($this->item($id), Fields::fromQuery($request->query));
    }

    /** Saves a sidebar's widgets, in the order given. */
    #[Route(Method::Post, '/wp/v2/sidebars/{id:[\w-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    #[Route(Method::Put, '/wp/v2/sidebars/{id:[\w-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    #[Route(Method::Patch, '/wp/v2/sidebars/{id:[\w-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    public function save(Request $request, string $id): Response
    {
        self::ready();
        self::requireSidebar($id);
        $body = $request->json();
        if (isset($body['widgets']) && is_array($body['widgets'])) {
            $wanted = array_values(array_map(static fn ($w) => is_array($w) ? (string) ($w['id'] ?? '') : (string) $w, $body['widgets']));
            $sidebars = \retrieve_widgets();
            $dropped = array_diff((array) ($sidebars[$id] ?? []), $wanted);
            foreach ($sidebars as $other => $widgets) {
                $sidebars[$other] = array_values(array_diff((array) $widgets, $wanted));
            }
            $sidebars[$id] = $wanted;
            $sidebars[WidgetAreas::INACTIVE] = array_values(array_merge($sidebars[WidgetAreas::INACTIVE] ?? [], $dropped));
            \wp_set_sidebars_widgets($sidebars);
        }
        return Reply::item($this->item($id), Fields::fromQuery($request->query));
    }

    /** The registered widget types, by id base. */
    #[Route(Method::Get, '/wp/v2/widget-types', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT])]
    public function types(Request $request): Response
    {
        self::ready();
        $types = self::widgetTypes();
        $fields = Fields::fromQuery($request->query);
        $items = array_map(fn (\WP_Widget $widget) => $this->type($widget), array_values($types));
        return Reply::item($fields === null ? $items : array_map(static fn (array $item) => $fields->apply($item), $items), null);
    }

    /** One widget type. */
    #[Route(Method::Get, '/wp/v2/widget-types/{id:[a-zA-Z0-9_-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT])]
    public function widgetType(Request $request, string $id): Response
    {
        self::ready();
        $widget = self::widgetTypes()[$id] ?? null;
        if ($widget === null) {
            throw new RestError('rest_widget_type_invalid', 'Invalid widget type.', 404);
        }
        return Reply::item($this->type($widget), Fields::fromQuery($request->query));
    }

    /**
     * A widget type's form and preview for settings that are not saved: the
     * settings sent (encoded with their hash) updated from the form's
     * fields, the form for them (number -1 unless one is sent), the widget
     * as the_widget shows it, and the settings encoded again.
     */
    #[Route(Method::Post, '/wp/v2/widget-types/{id:[a-zA-Z0-9_-]+}/encode', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'))]
    public function encode(Request $request, string $id): Response
    {
        self::ready();
        $widget = self::widgetTypes()[$id] ?? null;
        if ($widget === null) {
            throw new RestError('rest_widget_type_invalid', 'Invalid widget type.', 404);
        }
        $body = $request->json();
        $number = (int) ($body['number'] ?? -1);
        $instance = [];
        if (isset($body['instance']['encoded'], $body['instance']['hash'])) {
            $serialized = (string) base64_decode((string) $body['instance']['encoded'], true);
            if (!hash_equals(\wp_hash($serialized), (string) $body['instance']['hash'])) {
                throw new RestError('rest_invalid_widget', 'The provided instance is malformed.', 400);
            }
            $instance = (array) \maybe_unserialize($serialized);
        }
        if (isset($body['form_data'])) {
            parse_str((string) $body['form_data'], $fields);
            $widget->_set($number);
            $instance = (array) $widget->update((array) ($fields['widget-' . $widget->id_base][$number] ?? reset($fields['widget-' . $widget->id_base]) ?: []), $instance);
        }
        $widget->_set($number);
        ob_start();
        $widget->form($instance);
        $form = trim((string) ob_get_clean());
        ob_start();
        \the_widget(get_class($widget), $instance);
        $serialized = serialize($instance);
        $encoded = ['encoded' => base64_encode($serialized), 'hash' => \wp_hash($serialized)] + (empty($widget->widget_options['show_instance_in_rest']) ? [] : ['raw' => $instance]);
        return Reply::item(['form' => $form, 'preview' => trim((string) ob_get_clean()), 'instance' => $encoded], null);
    }

    /**
     * The widgets are registered on init; a runtime that stopped short of it
     * registers them now. Without the runtime there are no widgets to serve.
     */
    public static function ready(): void
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
        if (!\did_action('widgets_init')) {
            \wp_widgets_init();
        }
    }

    /** @return array<string, mixed> */
    private function item(string $id): array
    {
        $sidebar = (array) WidgetAreas::sidebar($id);
        $registered = $GLOBALS['wp_registered_widgets'] ?? [];
        $item = [
            'id' => $id,
            'name' => (string) ($sidebar['name'] ?? ''),
            'description' => (string) ($sidebar['description'] ?? ''),
            'class' => (string) ($sidebar['class'] ?? ''),
            'before_widget' => (string) ($sidebar['before_widget'] ?? ''),
            'after_widget' => (string) ($sidebar['after_widget'] ?? ''),
            'before_title' => (string) ($sidebar['before_title'] ?? ''),
            'after_title' => (string) ($sidebar['after_title'] ?? ''),
            'status' => $id !== WidgetAreas::INACTIVE && \is_registered_sidebar($id) && !\wp_is_block_theme() ? 'active' : 'inactive',
            'widgets' => array_values(array_filter((array) (\retrieve_widgets()[$id] ?? []), static fn ($widget) => isset($registered[$widget]))),
            '_links' => [
                'collection' => [['href' => $this->url->to('/wp/v2/sidebars')]],
                'self' => [['href' => $this->url->to('/wp/v2/sidebars/' . $id), 'targetHints' => ['allow' => ['GET', 'POST', 'PUT', 'PATCH']]]],
                'wp:widget' => [['embeddable' => true, 'href' => $this->url->to('/wp/v2/widgets', ['sidebar' => $id])]],
                'curies' => RestUrl::curies(),
            ],
        ];
        return RuntimePrepare::item('rest_prepare_sidebar', $item, static fn () => $sidebar);
    }

    /** @return array<string, mixed> */
    private function type(\WP_Widget $widget): array
    {
        $item = [
            'id' => $widget->id_base,
            'name' => $widget->name,
            'description' => (string) ($widget->widget_options['description'] ?? ''),
            'is_multi' => true,
            'classname' => (string) ($widget->widget_options['classname'] ?? ''),
            '_links' => [
                'collection' => [['href' => $this->url->to('/wp/v2/widget-types')]],
                'self' => [['href' => $this->url->to('/wp/v2/widget-types/' . $widget->id_base), 'targetHints' => ['allow' => ['GET']]]],
            ],
        ];
        return RuntimePrepare::item('rest_prepare_widget_type', $item, static fn () => $widget);
    }

    /** @return array<string, \WP_Widget> by id base, in id order */
    private static function widgetTypes(): array
    {
        $types = [];
        foreach (\_minn_widget_factory()->widgets as $widget) {
            $types[$widget->id_base] = $widget;
        }
        ksort($types, SORT_STRING);
        return $types;
    }

    private static function requireSidebar(string $id): void
    {
        if (WidgetAreas::sidebar($id) === null) {
            throw new RestError('rest_sidebar_not_found', 'No sidebar exists with that id.', 404);
        }
    }
}
