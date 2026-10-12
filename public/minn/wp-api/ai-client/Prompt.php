<?php
/**
 * The WordPress prompt builder and ability resolver (probe ai-client-prompt).
 *
 * The builder speaks snake_case over the SDK's builder. A setting or a
 * generation that fails is kept, and every generation after it answers that
 * failure: an invalid
 * argument as prompt_invalid_argument (400), anything else as
 * prompt_builder_error (500), each naming the exception's class. A support
 * check then answers false; any other call hands back the builder. Abilities
 * offered to a prompt become function declarations.
 *
 * The resolver runs the function calls a model makes on behalf of the
 * abilities it was given: wpab__ plus the ability's name, its slash as a
 * double underscore.
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\ProviderRegistry;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

class WP_AI_Client_Prompt_Builder
{
    private $builder = null;
    private $error = null;

    /** The SDK's builder over the registry, with wp_ai_client_default_request_timeout's timeout (30 seconds) for its requests. */
    public function __construct(ProviderRegistry $registry, $prompt = null)
    {
        $options = new RequestOptions();
        $options->setTimeout((float) apply_filters('wp_ai_client_default_request_timeout', 30));
        try {
            $this->builder = new PromptBuilder($registry, $prompt, AiClient::getEventDispatcher());
            $this->builder->usingRequestOptions($options);
        } catch (Exception $e) {
            $this->error = $this->exception_to_wp_error($e);
        }
    }

    /** The abilities, by name or object, offered to the model as functions; one that is not an ability is passed over. */
    public function using_abilities(...$abilities)
    {
        $declarations = [];
        foreach ($abilities as $ability) {
            $ability = is_string($ability) ? wp_get_ability($ability) : $ability;
            if ($ability instanceof WP_Ability) {
                $schema = $ability->get_input_schema();
                $declarations[] = new FunctionDeclaration(WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name($ability->get_name()), $ability->get_description(), $schema === [] ? null : (array) $schema);
            }
        }
        return $declarations === [] ? $this : $this->__call('using_function_declarations', $declarations);
    }

    /**
     * A builder call by its snake_case name: the builder back for a setting,
     * the answer for a check or a generation. A check or a generation is
     * first offered to wp_ai_client_prevent_prompt: prevented, the builder
     * answers as one that failed. A failed generation is kept like a failed
     * setting; a failed check is only false (probe hook-inputs-2).
     */
    public function __call(string $name, array $arguments)
    {
        $runs = self::is_generating_method($name) || self::is_support_check_method($name);
        if ($this->error === null && $runs && apply_filters('wp_ai_client_prevent_prompt', false, $this)) {
            $this->error = new WP_Error('prompt_prevented', __('Prompt execution was prevented by a filter.'), ['status' => 503]);
        }
        if ($this->error !== null) {
            return $this->answer_for_error($name);
        }
        try {
            $result = call_user_func_array($this->get_builder_callable($name), $arguments);
        } catch (Exception $e) {
            if (self::is_support_check_method($name)) {
                return false;
            }
            $this->error = $this->exception_to_wp_error($e);
            return self::is_generating_method($name) ? $this->error : $this;
        }
        return $result === $this->builder ? $this : $result;
    }

    /** What a call answers once a setting has failed. */
    private function answer_for_error(string $name)
    {
        return self::is_generating_method($name) ? $this->error : (self::is_support_check_method($name) ? false : $this);
    }

    private function exception_to_wp_error(Exception $e)
    {
        $invalid = $e instanceof InvalidArgumentException;
        return new WP_Error($invalid ? 'prompt_invalid_argument' : 'prompt_builder_error', $e->getMessage(), ['status' => $invalid ? 400 : 500, 'exception_class' => get_class($e)]);
    }

    private static function is_support_check_method(string $name)
    {
        return str_starts_with($name, 'is_supported');
    }

    private static function is_generating_method(string $name)
    {
        return str_starts_with($name, 'generate') || str_starts_with($name, 'convert_text_to_speech');
    }

    /** The SDK builder's method for the name; a name it lacks is a BadMethodCallException. */
    protected function get_builder_callable(string $name): callable
    {
        $method = $this->snake_to_camel_case($name);
        if (!method_exists($this->builder, $method)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist on %s.', $name, get_class($this->builder)));
        }
        return [$this->builder, $method];
    }

    private function snake_to_camel_case(string $snake_case)
    {
        return lcfirst(str_replace('_', '', ucwords($snake_case, '_')));
    }
}

class WP_AI_Client_Ability_Function_Resolver
{
    private $abilities = [];

    /** The abilities a model may call, by name or object. */
    public function __construct(...$abilities)
    {
        foreach ($abilities as $ability) {
            $name = $ability instanceof WP_Ability ? $ability->get_name() : (is_string($ability) ? $ability : null);
            if ($name !== null) {
                $this->abilities[] = $name;
            }
        }
    }

    public function is_ability_call(FunctionCall $call)
    {
        return str_starts_with((string) $call->getName(), 'wpab__');
    }

    /** The response to one call: the ability's result, or the reason it did not run, as an error and its code. */
    public function execute_ability(FunctionCall $call)
    {
        [$id, $name] = [$call->getId() ?? 'unknown', (string) $call->getName()];
        if (!$this->is_ability_call($call)) {
            return new FunctionResponse($id, $name, ['error' => 'Not an ability function call', 'code' => 'invalid_ability_call']);
        }
        $ability_name = self::function_name_to_ability_name($name);
        $ability = in_array($ability_name, $this->abilities, true) ? wp_get_ability($ability_name) : null;
        if (!$ability instanceof WP_Ability) {
            return new FunctionResponse($id, $name, ['error' => sprintf('Ability "%s" was not specified in the allowed abilities list.', $ability_name), 'code' => 'ability_not_allowed']);
        }
        // Empty arguments are no input at all, so an ability without an input schema still runs.
        $result = $ability->execute(empty($call->getArgs()) ? null : $call->getArgs());
        return new FunctionResponse($id, $name, is_wp_error($result) ? ['error' => $result->get_error_message(), 'code' => $result->get_error_code(), 'data' => $result->get_error_data()] : $result);
    }

    public function has_ability_calls(Message $message)
    {
        foreach ($message->getParts() as $part) {
            $call = $part->getFunctionCall();
            if ($call !== null && $this->is_ability_call($call)) {
                return true;
            }
        }
        return false;
    }

    /** A user message answering each ability call in the message, in order; other calls are left out. */
    public function execute_abilities(Message $message)
    {
        $parts = [];
        foreach ($message->getParts() as $part) {
            $call = $part->getFunctionCall();
            if ($call !== null && $this->is_ability_call($call)) {
                $parts[] = new MessagePart($this->execute_ability($call));
            }
        }
        return new UserMessage($parts);
    }

    public static function ability_name_to_function_name(string $ability_name)
    {
        return 'wpab__' . str_replace('/', '__', $ability_name);
    }

    /** The ability a function name stands for: the name after its first six characters, double underscores as slashes. */
    public static function function_name_to_ability_name(string $function_name)
    {
        return str_replace('__', '/', substr($function_name, 6));
    }
}
