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
 * wp/v2/widgets as the reference answers it (probe rest-widgets), for
 * anyone who can edit theme options: the registered widgets of every
 * sidebar (or one), a widget created under the next free number for its
 * type, its settings saved through the widget's own update (raw when the
 * widget shows its instance in REST, encoded with a matching hash, or from
 * its form's fields) and registered at once, a widget moved between
 * sidebars, and a widget deleted, or without force sent to the inactive
 * widgets.
 */
final readonly class WidgetsController
{
    private const LIST = ['sidebar' => ['description' => 'The sidebar to return widgets for.', 'type' => 'string', 'required' => false]];

    private const BODY = [
        'id_base' => ['description' => 'The type of the widget. Corresponds to ID in widget-types endpoint.', 'type' => 'string', 'required' => false],
        'sidebar' => ['description' => 'The sidebar the widget belongs to.', 'type' => 'string', 'required' => false],
        'instance' => ['description' => 'Instance settings of the widget, if supported.', 'type' => 'object', 'required' => false],
        'form_data' => ['description' => 'URL-encoded form data from the widget admin form. Used to update a widget that does not support instance. Write only.', 'type' => 'string', 'required' => false],
    ];

    public function __construct(private WidgetObject $object, private Caller $caller)
    {
    }

    /** The registered widgets, sidebar by sidebar. */
    #[Route(Method::Get, '/wp/v2/widgets', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT, self::LIST])]
    public function list(Request $request): Response
    {
        SidebarsController::ready();
        $only = (string) ($request->query['sidebar'] ?? '');
        $context = Context::of($request);
        $out = [];
        foreach (\retrieve_widgets() as $sidebar => $widgets) {
            if ($only !== '' && $only !== (string) $sidebar) {
                continue;
            }
            foreach ((array) $widgets as $id) {
                if (isset($GLOBALS['wp_registered_widgets'][$id])) {
                    $out[] = $this->object->view((string) $id, (string) $sidebar, $context);
                }
            }
        }
        $fields = Fields::fromQuery($request->query);
        return Reply::item($fields === null ? $out : array_map(static fn (array $item) => $fields->apply($item), $out), null);
    }

    /** One widget. */
    #[Route(Method::Get, '/wp/v2/widgets/{id:[\w\-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [Args::CONTEXT])]
    public function single(Request $request, string $id): Response
    {
        SidebarsController::ready();
        return Reply::item($this->object->view($id, self::sidebarOf($id), Context::of($request)), Fields::fromQuery($request->query));
    }

    /** Creates a widget in a sidebar (the inactive widgets unless one is named). */
    #[Route(Method::Post, '/wp/v2/widgets', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    public function create(Request $request): Response
    {
        SidebarsController::ready();
        $body = $request->json();
        $object = \_minn_widget_factory()->get_widget_object((string) ($body['id_base'] ?? ''));
        if (!$object instanceof \WP_Widget) {
            throw new RestError('rest_invalid_widget', 'Invalid widget.', 400);
        }
        $sidebar = (string) ($body['sidebar'] ?? WidgetAreas::INACTIVE);
        $settings = (array) $object->get_settings();
        $number = max([1, ...array_filter(array_keys($settings), 'is_int')]) + 1;
        $id = $this->saveInstance($object, $number, $body);
        \wp_assign_widget_to_sidebar($id, $sidebar);
        \do_action('rest_after_save_widget', $id, $sidebar, Runtime::current()->get('rest_prepare_request'), true);
        return Reply::item($this->object->view($id, $sidebar, Context::Edit), null, 201);
    }

    /** Saves a widget's settings and moves it, as the body asks. */
    #[Route(Method::Post, '/wp/v2/widgets/{id:[\w\-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    #[Route(Method::Put, '/wp/v2/widgets/{id:[\w\-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    #[Route(Method::Patch, '/wp/v2/widgets/{id:[\w\-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), body: [self::BODY])]
    public function update(Request $request, string $id): Response
    {
        SidebarsController::ready();
        $sidebar = self::sidebarOf($id);
        $body = $request->json();
        $parsed = WidgetAreas::parse($id);
        $object = \_minn_widget_factory()->get_widget_object($parsed['id_base']);
        if ($object instanceof \WP_Widget && (isset($body['instance']) || isset($body['form_data']))) {
            $this->saveInstance($object, (int) ($parsed['number'] ?? 0), $body);
        }
        if (isset($body['sidebar']) && (string) $body['sidebar'] !== $sidebar) {
            $sidebar = (string) $body['sidebar'];
            \wp_assign_widget_to_sidebar($id, $sidebar);
        }
        \do_action('rest_after_save_widget', $id, $sidebar, Runtime::current()->get('rest_prepare_request'), false);
        return Reply::item($this->object->view($id, $sidebar, Context::Edit), null);
    }

    /** Deletes a widget with force; without, sends it to the inactive widgets. */
    #[Route(Method::Delete, '/wp/v2/widgets/{id:[\w\-]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_manage_widgets', signInMessage: 'Sorry, you are not allowed to manage widgets on this site.', refuse: 'rest_cannot_manage_widgets', message: 'Sorry, you are not allowed to manage widgets on this site.'), args: [['force' => ['description' => 'Whether to force removal of the widget, or move it to the inactive sidebar.', 'type' => 'boolean', 'required' => false]]])]
    public function delete(Request $request, string $id): Response
    {
        SidebarsController::ready();
        $sidebar = self::sidebarOf($id);
        if (!\rest_sanitize_boolean($request->query['force'] ?? false)) {
            \wp_assign_widget_to_sidebar($id, WidgetAreas::INACTIVE);
            return Reply::item($this->object->view($id, WidgetAreas::INACTIVE, Context::Edit), null);
        }
        // The answer carries the deleted widget's links, as the reference's does.
        $previous = $this->object->view($id, $sidebar, Context::Edit);
        $links = $previous['_links'];
        unset($previous['_links']);
        $parsed = WidgetAreas::parse($id);
        $object = \_minn_widget_factory()->get_widget_object($parsed['id_base']);
        if ($object instanceof \WP_Widget) {
            $settings = (array) $object->get_settings();
            unset($settings[$parsed['number'] ?? -1]);
            $object->save_settings($settings);
        }
        \wp_assign_widget_to_sidebar($id, '');
        \do_action('delete_widget', $id, $sidebar, $parsed['id_base']);
        return Reply::item(['deleted' => true, 'previous' => $previous, '_links' => $links], null);
    }

    /**
     * Saves a widget's settings through its own update and registers it:
     * raw settings (when it shows them), encoded ones with their hash, or
     * its form's fields. The widget's id.
     *
     * @param array<string, mixed> $body
     */
    private function saveInstance(\WP_Widget $object, int $number, array $body): string
    {
        $new = self::newInstance($object, $number, $body);
        $settings = (array) $object->get_settings();
        $old = isset($settings[$number]) ? (array) $settings[$number] : [];
        $object->_set($number);
        $saved = $object->update($new, $old);
        $saved = \apply_filters('widget_update_callback', $saved, $new, $old, $object);
        if ($saved !== false) {
            $settings[$number] = $saved;
            $object->save_settings($settings);
        }
        $object->_register_one($number);
        return $object->id_base . '-' . $number;
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private static function newInstance(\WP_Widget $object, int $number, array $body): array
    {
        $instance = (array) ($body['instance'] ?? []);
        if (isset($instance['raw'])) {
            if (empty($object->widget_options['show_instance_in_rest'])) {
                throw new RestError('rest_invalid_widget', 'Widget type does not support raw instances.', 400);
            }
            return (array) $instance['raw'];
        }
        if (isset($instance['encoded'], $instance['hash'])) {
            $serialized = (string) base64_decode((string) $instance['encoded'], true);
            if (!hash_equals(\wp_hash($serialized), (string) $instance['hash'])) {
                throw new RestError('rest_invalid_widget', 'The provided instance is malformed.', 400);
            }
            return (array) \maybe_unserialize($serialized);
        }
        if (isset($body['form_data'])) {
            parse_str((string) $body['form_data'], $fields);
            return (array) ($fields['widget-' . $object->id_base][$number] ?? reset($fields['widget-' . $object->id_base]) ?: []);
        }
        return [];
    }

    /** The sidebar holding a widget; a widget no sidebar holds is no widget. */
    private static function sidebarOf(string $id): string
    {
        $sidebar = \wp_find_widgets_sidebar($id);
        if ($sidebar === null || !isset($GLOBALS['wp_registered_widgets'][$id])) {
            throw new RestError('rest_widget_not_found', 'No widget exists with that id.', 404);
        }
        return $sidebar;
    }
}
