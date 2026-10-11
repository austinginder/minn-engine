<?php
/**
 * The AI client's prompt builder and abilities (probe ai-client-prompt).
 * Core registers no AI provider, so every generation ends in an error: what
 * each configuring call hands back, which error each generation gives
 * (and that the first error stays), the support checks, an empty prompt;
 * abilities offered to a prompt; the ability resolver's names, the
 * function calls it executes and the message it answers with; and the data
 * objects and enums those pass around. The probe's abilities are its own.
 */

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_action('wp_abilities_api_categories_init', static function (): void {
    wp_register_ability_category('zz-ai', ['label' => 'Zz AI', 'description' => 'Probe.']);
});
add_action('wp_abilities_api_init', static function (): void {
    wp_register_ability('zz-ai/echo', ['label' => 'Zz Echo', 'description' => 'Echoes its input.', 'category' => 'zz-ai', 'input_schema' => ['type' => 'object', 'properties' => ['word' => ['type' => 'string']]], 'execute_callback' => static fn ($input = null) => ['echo' => $input], 'permission_callback' => '__return_true']);
    wp_register_ability('zz-ai/plain', ['label' => 'Zz Plain', 'description' => 'No input.', 'category' => 'zz-ai', 'execute_callback' => static fn () => 'plain', 'permission_callback' => '__return_true']);
    wp_register_ability('zz-ai/closed', ['label' => 'Zz Closed', 'description' => 'Refuses.', 'category' => 'zz-ai', 'execute_callback' => static fn () => 'never', 'permission_callback' => '__return_false']);
});
$plain = static function ($value) {
    if (is_wp_error($value)) {
        return ['error' => $value->get_error_code(), 'message' => $value->get_error_message(), 'data' => $value->get_error_data()];
    }
    return is_object($value) ? (method_exists($value, 'toArray') ? [get_class($value), $value->toArray()] : get_class($value)) : $value;
};
$timeouts = [];
$timeout = static function ($seconds) use (&$timeouts) {
    $timeouts[] = [$seconds, func_num_args()];
    return $seconds;
};
add_filter('wp_ai_client_default_request_timeout', $timeout, 10, 9);
$direct = new WP_AI_Client_Prompt_Builder(WordPress\AiClient\AiClient::defaultRegistry(), 'Made directly');
$say('a prompt builder', ['with text' => get_class(wp_ai_client_prompt('Hello')), 'with none' => get_class(wp_ai_client_prompt()), 'made directly' => [get_class($direct), $plain($direct->generate_text())], 'timeouts' => $timeouts]);
remove_filter('wp_ai_client_default_request_timeout', $timeout, 10);

// Each configuring call on a fresh builder: what it hands back, then what generating text gives.
$calls = [
    'text' => static fn ($b) => $b->with_text('More'),
    'an empty text' => static fn ($b) => $b->with_text(''),
    'system instruction' => static fn ($b) => $b->using_system_instruction('Be brief.'),
    'max tokens' => static fn ($b) => $b->using_max_tokens(100),
    'negative max tokens' => static fn ($b) => $b->using_max_tokens(-5),
    'temperature' => static fn ($b) => $b->using_temperature(0.5),
    'top p' => static fn ($b) => $b->using_top_p(2.0),
    'top k' => static fn ($b) => $b->using_top_k(40),
    'stop sequences' => static fn ($b) => $b->using_stop_sequences('a', 'b'),
    'candidate count' => static fn ($b) => $b->using_candidate_count(0),
    'presence penalty' => static fn ($b) => $b->using_presence_penalty(0.1),
    'frequency penalty' => static fn ($b) => $b->using_frequency_penalty(0.1),
    'top logprobs' => static fn ($b) => $b->using_top_logprobs(3),
    'a JSON response' => static fn ($b) => $b->as_json_response(['type' => 'object']),
    'a mime type' => static fn ($b) => $b->as_output_mime_type('zz/none'),
    'a schema' => static fn ($b) => $b->as_output_schema(['type' => 'string']),
    'modalities' => static fn ($b) => $b->as_output_modalities(ModalityEnum::text(), ModalityEnum::image()),
    'a file type' => static fn ($b) => $b->as_output_file_type(FileTypeEnum::remote()),
    'an orientation' => static fn ($b) => $b->as_output_media_orientation(MediaOrientationEnum::landscape()),
    'an aspect ratio' => static fn ($b) => $b->as_output_media_aspect_ratio('16:9'),
    'a bad aspect ratio' => static fn ($b) => $b->as_output_media_aspect_ratio('wide'),
    'a voice' => static fn ($b) => $b->as_output_speech_voice('alloy'),
    'request options' => static fn ($b) => $b->using_request_options(new RequestOptions()),
    'a provider not registered' => static fn ($b) => $b->using_provider('zz'),
    'a model preference' => static fn ($b) => $b->using_model_preference('zz-model'),
    'function declarations' => static fn ($b) => $b->using_function_declarations(new FunctionDeclaration('zz_fn', 'A function.')),
    'history' => static fn ($b) => $b->with_history(new UserMessage([new MessagePart('Earlier')])),
    'message parts' => static fn ($b) => $b->with_message_parts(new MessagePart('Part')),
    'a method that does not exist' => static fn ($b) => $b->zz_nope(),
    'a temperature of the wrong type' => static fn ($b) => $b->using_temperature('hot'),
];
$configured = [];
foreach ($calls as $label => $call) {
    $builder = wp_ai_client_prompt('Hi');
    try {
        $returned = $call($builder);
        $configured[$label] = ['returned' => $returned === $builder ? 'the builder' : $plain($returned), 'generated' => $plain($builder->generate_text()), 'supported' => $builder->is_supported_for_text_generation()];
    } catch (Throwable $e) {
        $configured[$label] = ['threw' => get_class($e)];
    }
}
$say('configuring', $configured);

$generated = [];
foreach (['generate_text', 'generate_texts', 'generate_text_result', 'generate_result', 'generate_image', 'generate_images', 'generate_image_result', 'convert_text_to_speech', 'convert_text_to_speeches', 'convert_text_to_speech_result', 'generate_speech', 'generate_speeches', 'generate_speech_result', 'generate_video', 'generate_videos', 'generate_video_result', 'is_supported', 'is_supported_for_text_generation', 'is_supported_for_image_generation', 'is_supported_for_text_to_speech_conversion', 'is_supported_for_speech_generation', 'is_supported_for_music_generation', 'is_supported_for_video_generation', 'is_supported_for_embedding_generation'] as $method) {
    $generated[$method] = $plain(wp_ai_client_prompt('Hi')->$method());
}
$say('generating with no provider', $generated);
$say('an empty prompt', ['none' => $plain(wp_ai_client_prompt()->generate_text()), 'an empty string' => $plain(wp_ai_client_prompt('')->generate_text()), 'supported' => wp_ai_client_prompt()->is_supported_for_text_generation()]);
$first = wp_ai_client_prompt('Hi');
$first->zz_nope();
$first->using_provider('zz');
$say('the first error stays', ['generated' => $plain($first->generate_text()), 'image' => $plain($first->generate_image()), 'supported' => $first->is_supported_for_text_generation(), 'still the builder' => $first->using_max_tokens(10) === $first]);

// Abilities offered to a prompt.
$offered = [];
foreach (['by name and object' => ['zz-ai/echo', 'OBJECT'], 'a missing one' => ['zz-ai/missing'], 'none' => [], 'not an ability' => [42]] as $label => $abilities) {
    $builder = wp_ai_client_prompt('Hi');
    $abilities = array_map(static fn ($ability) => $ability === 'OBJECT' ? wp_get_ability('zz-ai/plain') : $ability, $abilities);
    $offered[$label] = ['returned' => $builder->using_abilities(...$abilities) === $builder ? 'the builder' : 'something else', 'generated' => $plain($builder->generate_text())];
}
$say('abilities offered to a prompt', $offered);

// The resolver.
$resolver = 'WP_AI_Client_Ability_Function_Resolver';
$say('ability names', [
    'to function' => [WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name('zz-ai/echo'), WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name('plain'), WP_AI_Client_Ability_Function_Resolver::ability_name_to_function_name('a/b/c')],
    'to ability' => [WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name('wpab__zz-ai__echo'), WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name('zz_other'), WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name('wpab__a__b__c')],
]);
$allowed = new WP_AI_Client_Ability_Function_Resolver(wp_get_ability('zz-ai/echo'), 'zz-ai/plain', 'zz-ai/closed');
$executed = [];
foreach ([['c1', 'wpab__zz-ai__echo', ['word' => 'hi']], ['c2', 'wpab__zz-ai__plain', null], ['c3', 'wpab__zz-ai__closed', []], ['c4', 'wpab__zz-ai__nope', []], ['c5', 'other_fn', []], [null, 'wpab__zz-ai__echo', 'not an object']] as [$id, $name, $args]) {
    $call = new FunctionCall($id, $name, $args);
    $executed[($id ?? 'no id') . ' ' . $name] = ['an ability call' => $allowed->is_ability_call($call), 'executed' => $plain($allowed->execute_ability($call))];
}
$say('the resolver executes calls', $executed);
$message = new ModelMessage([new MessagePart('Text first'), new MessagePart(new FunctionCall('c1', 'wpab__zz-ai__echo', ['word' => 'yo'])), new MessagePart(new FunctionCall('c5', 'other_fn', [])), new MessagePart(new FunctionCall('c2', 'wpab__zz-ai__plain'))]);
$say('the resolver answers a message', [
    'has calls' => [$allowed->has_ability_calls($message), $allowed->has_ability_calls(new UserMessage([new MessagePart('No calls')]))],
    'answer' => $plain($allowed->execute_abilities($message)),
    'with none allowed' => $plain((new WP_AI_Client_Ability_Function_Resolver())->execute_abilities($message)),
]);

// The data objects and enums.
$role = MessageRoleEnum::user();
$say('data objects', [
    'function call' => [(new FunctionCall('a', 'b', null))->toArray(), (new FunctionCall(null, 'b'))->toArray(), (new FunctionCall('a', 'b', ['x' => 1]))->getArgs()],
    'function response' => [(new FunctionResponse(null, 'n', null))->toArray(), (new FunctionResponse('i', 'n', ['ok' => true]))->toArray()],
    'function declaration' => [(new FunctionDeclaration('n', 'd'))->toArray(), (new FunctionDeclaration('n', 'd', ['type' => 'object']))->toArray()],
    'message parts' => [(new MessagePart('hi'))->toArray(), (new MessagePart(new FunctionCall('a', 'b', ['x' => 1])))->toArray(), (new MessagePart(new FunctionResponse('a', 'b', 1)))->toArray(), (string) (new MessagePart('hi'))->getType(), (new MessagePart('hi'))->getText()],
    'messages' => [(new UserMessage([new MessagePart('hi')]))->toArray(), (new ModelMessage([]))->toArray(), (string) (new ModelMessage([]))->getRole(), count((new UserMessage([]))->withPart(new MessagePart('x'))->getParts())],
    'an enum' => [$role->value, (string) $role, json_encode($role), $role->isUser(), $role->isModel(), MessageRoleEnum::from('model')->value, MessageRoleEnum::tryFrom('zz'), MessageRoleEnum::getValues(), $role->equals('user'), $role->is(MessageRoleEnum::user()), MessageRoleEnum::isValidValue('model'), count(MessageRoleEnum::cases())],
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
