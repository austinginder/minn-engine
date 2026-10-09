<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp-abilities/v1/abilities/{name}/run (probe abilities-rest-controllers).
 * The permission check is where a run is judged, in order:
 *   1. an ability shown in REST (rest_ability_not_found, 404);
 *   2. the method its annotations ask for: GET when read-only, DELETE when
 *      destructive and idempotent, else POST (405);
 *   3. the input, which takes the input schema's default and is validated
 *      (400);
 *   4. the ability's permission: false is rest_ability_cannot_execute, an
 *      error is kept, each at the authorization status.
 * The run itself only finds the ability and runs it, returning its error
 * as it is. Input comes from the query on GET and from the JSON body
 * otherwise. The input argument is sanitized against the input schema only
 * when it validates, and is left as given otherwise.
 */
class WP_REST_Abilities_V1_Run_Controller extends WP_REST_Controller
{
    protected $namespace = 'wp-abilities/v1';
    protected $rest_base = 'abilities';

    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<name>[a-zA-Z0-9\-\/]+?)/run', [
            ['methods' => WP_REST_Server::ALLMETHODS, 'callback' => [$this, 'execute_ability'], 'permission_callback' => [$this, 'check_ability_permissions'], 'args' => ['name' => SchemaData::of('ability-name')] + $this->get_run_args()],
            'schema' => [$this, 'get_run_schema'],
        ]);
    }

    public function execute_ability($request)
    {
        $ability = $this->ability($request, false);
        if (is_wp_error($ability)) {
            return $ability;
        }
        $result = $ability->execute($this->get_input_from_request($request));
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public function validate_request_method(string $request_method, array $annotations)
    {
        $wanted = match (true) {
            !empty($annotations['readonly']) => ['GET', 'Read-only abilities require GET method.'],
            !empty($annotations['destructive']) && !empty($annotations['idempotent']) => ['DELETE', 'Abilities that perform destructive actions require DELETE method.'],
            default => ['POST', 'Abilities that perform updates require POST method.'],
        };
        return $request_method === $wanted[0] ? true : new WP_Error('rest_ability_invalid_method', $wanted[1], ['status' => 405]);
    }

    public function check_ability_permissions($request)
    {
        $ability = $this->ability($request, true);
        $method = is_wp_error($ability) ? $ability : $this->validate_request_method($request->get_method(), (array) $ability->get_meta_item('annotations', []));
        if (is_wp_error($method)) {
            return $method;
        }
        $input = $ability->normalize_input($this->get_input_from_request($request));
        $valid = $ability->validate_input($input);
        if (is_wp_error($valid)) {
            return $this->ensure_error_status($valid, 400);
        }
        $allowed = $ability->check_permissions($input);
        if (is_wp_error($allowed)) {
            return $this->ensure_error_status($allowed, rest_authorization_required_code());
        }
        return $allowed === true ? true : new WP_Error('rest_ability_cannot_execute', 'Sorry, you are not allowed to execute this ability.', ['status' => rest_authorization_required_code()]);
    }

    public function sanitize_input_for_ability($input, $request)
    {
        $name = (string) $request['name'];
        $ability = wp_has_ability($name) ? wp_get_ability($name) : null;
        return $ability === null || $ability->get_input_schema() === [] ? $input : $this->coerce_input_to_schema($input, $ability);
    }

    public function get_run_args(): array
    {
        return ['input' => SchemaData::of('ability-run-input') + ['sanitize_callback' => [$this, 'sanitize_input_for_ability']]];
    }

    public function get_run_schema(): array
    {
        return SchemaData::of('ability-execution');
    }

    /** The named ability, or rest_ability_not_found; with $shown, only one shown in REST. */
    private function ability($request, bool $shown)
    {
        $name = (string) $request['name'];
        $ability = wp_has_ability($name) ? wp_get_ability($name) : null;
        return $ability === null || ($shown && !$ability->get_meta_item('show_in_rest')) ? new WP_Error('rest_ability_not_found', 'Ability not found.', ['status' => 404]) : $ability;
    }

    private function ensure_error_status(WP_Error $error, int $status): WP_Error
    {
        $data = $error->get_error_data();
        if (!is_array($data) || !isset($data['status'])) {
            $error->add_data(['status' => $status]);
        }
        return $error;
    }

    private function get_input_from_request($request)
    {
        return $request->get_method() === 'GET' ? ($request->get_query_params()['input'] ?? null) : ($request->get_json_params()['input'] ?? null);
    }

    private function coerce_input_to_schema($input, WP_Ability $ability)
    {
        $schema = $ability->get_input_schema();
        if (is_wp_error(rest_validate_value_from_schema($input, $schema, 'input'))) {
            return $input;
        }
        $sanitized = rest_sanitize_value_from_schema($input, $schema, 'input');
        return $this->input_contains_error($sanitized) ? $input : $sanitized;
    }

    private function input_contains_error($value): bool
    {
        if (is_wp_error($value)) {
            return true;
        }
        return is_array($value) && array_filter($value, fn ($item) => $this->input_contains_error($item)) !== [];
    }
}
