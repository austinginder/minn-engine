<?php
/**
 * The AI client SDK's front (probe ai-client-prompt): the provider registry
 * (core registers no provider, and providers built on the SDK's own
 * abstract provider classes are not part of the engine yet), AiClient's
 * defaults, and the prompt builder. The builder keeps the prompt and its
 * settings; generating checks the prompt has content, then asks the
 * registry for a model with the capability, and finds none.
 */

namespace WordPress\AiClient\Providers {
    use WordPress\AiClient\Common\Exception\InvalidArgumentException;

    /** The providers a site has registered, by id. */
    class ProviderRegistry
    {
        /** @var array<string, string> id => class name */
        private array $providers = [];
        private $httpTransporter = null;
        private array $authentication = [];

        /** Providers are classes built on the SDK's abstract provider, which the engine does not carry yet. */
        public function registerProvider(string $className): void
        {
            throw new InvalidArgumentException(sprintf('Provider class %s cannot be registered: the engine carries no provider base classes.', $className));
        }

        public function getRegisteredProviderIds(): array
        {
            return array_keys($this->providers);
        }

        public function hasProvider(string $idOrClassName): bool
        {
            return isset($this->providers[$idOrClassName]) || in_array($idOrClassName, $this->providers, true);
        }

        public function getProviderClassName(string $idOrClassName): string
        {
            if (!$this->hasProvider($idOrClassName)) {
                throw new InvalidArgumentException(sprintf('Provider not registered: %s', $idOrClassName));
            }
            return $this->providers[$idOrClassName] ?? $idOrClassName;
        }

        public function getProviderId(string $idOrClassName): string
        {
            $this->getProviderClassName($idOrClassName);
            return isset($this->providers[$idOrClassName]) ? $idOrClassName : (string) array_search($idOrClassName, $this->providers, true);
        }

        public function isProviderConfigured(string $idOrClassName): bool
        {
            return false;
        }

        /** The models that meet the requirements: none, with no provider registered. */
        public function findModelsMetadataForSupport($modelRequirements): array
        {
            return [];
        }

        public function findProviderModelsMetadataForSupport(string $idOrClassName, $modelRequirements): array
        {
            $this->getProviderClassName($idOrClassName);
            return [];
        }

        public function getProviderModel(string $idOrClassName, string $modelId, $modelConfig = null)
        {
            $this->getProviderClassName($idOrClassName);
            throw new InvalidArgumentException(sprintf('Model not found: %s', $modelId));
        }

        public function setHttpTransporter($httpTransporter): void
        {
            $this->httpTransporter = $httpTransporter;
        }

        public function getHttpTransporter()
        {
            return $this->httpTransporter;
        }

        public function setProviderRequestAuthentication(string $idOrClassName, $requestAuthentication): void
        {
            $this->authentication[$this->getProviderId($idOrClassName)] = $requestAuthentication;
        }

        public function getProviderRequestAuthentication(string $idOrClassName)
        {
            return $this->authentication[$this->getProviderId($idOrClassName)] ?? null;
        }
    }
}

namespace WordPress\AiClient {
    use WordPress\AiClient\Builders\PromptBuilder;
    use WordPress\AiClient\Providers\ProviderRegistry;
    use WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface;
    use WordPress\AiClientDependencies\Psr\SimpleCache\CacheInterface;

    /** The SDK's defaults: one provider registry, the event dispatcher and cache it is given, and prompts over them. */
    final class AiClient
    {
        private static ?ProviderRegistry $registry = null;
        private static ?EventDispatcherInterface $dispatcher = null;
        private static ?CacheInterface $cache = null;

        public static function defaultRegistry(): ProviderRegistry
        {
            return self::$registry ??= new ProviderRegistry();
        }

        public static function setEventDispatcher(?EventDispatcherInterface $dispatcher): void
        {
            self::$dispatcher = $dispatcher;
        }

        public static function getEventDispatcher(): ?EventDispatcherInterface
        {
            return self::$dispatcher;
        }

        public static function setCache(?CacheInterface $cache): void
        {
            self::$cache = $cache;
        }

        public static function getCache(): ?CacheInterface
        {
            return self::$cache;
        }

        public static function isConfigured($availabilityOrIdOrClassName): bool
        {
            return is_string($availabilityOrIdOrClassName) && self::defaultRegistry()->hasProvider($availabilityOrIdOrClassName) && self::defaultRegistry()->isProviderConfigured($availabilityOrIdOrClassName);
        }

        public static function prompt($prompt = null, ?ProviderRegistry $registry = null): PromptBuilder
        {
            return new PromptBuilder($registry ?? self::defaultRegistry(), $prompt, self::$dispatcher);
        }

        public static function generateTextResult($prompt, $modelOrConfig = null, ?ProviderRegistry $registry = null)
        {
            return self::prompt($prompt, $registry)->generateTextResult();
        }

        public static function generateImageResult($prompt, $modelOrConfig = null, ?ProviderRegistry $registry = null)
        {
            return self::prompt($prompt, $registry)->generateImageResult();
        }

        public static function convertTextToSpeechResult($prompt, $modelOrConfig = null, ?ProviderRegistry $registry = null)
        {
            return self::prompt($prompt, $registry)->convertTextToSpeechResult();
        }

        public static function generateSpeechResult($prompt, $modelOrConfig = null, ?ProviderRegistry $registry = null)
        {
            return self::prompt($prompt, $registry)->generateSpeechResult();
        }

        public static function generateVideoResult($prompt, $modelOrConfig = null, ?ProviderRegistry $registry = null)
        {
            return self::prompt($prompt, $registry)->generateVideoResult();
        }
    }
}

namespace WordPress\AiClient\Builders {
    use WordPress\AiClient\Common\Exception\InvalidArgumentException;
    use WordPress\AiClient\Files\DTO\File;
    use WordPress\AiClient\Files\Enums\FileTypeEnum;
    use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
    use WordPress\AiClient\Messages\DTO\Message;
    use WordPress\AiClient\Messages\DTO\MessagePart;
    use WordPress\AiClient\Messages\Enums\ModalityEnum;
    use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
    use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
    use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
    use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
    use WordPress\AiClient\Providers\ProviderRegistry;
    use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
    use WordPress\AiClient\Tools\DTO\FunctionResponse;
    use WordPress\AiClient\Tools\DTO\WebSearch;
    use WordPress\AiClientDependencies\Psr\EventDispatcher\EventDispatcherInterface;

    /**
     * A prompt and its settings. Each setting is kept as given; a provider is
     * checked against the registry and an aspect ratio against width:height
     * when given. Generating needs content, then a model with the capability.
     */
    class PromptBuilder
    {
        /** @var list<Message> */
        private array $history = [];
        /** @var list<MessagePart> */
        private array $parts = [];
        /** @var array<string, mixed> */
        private array $settings = [];

        public function __construct(private ProviderRegistry $registry, $prompt = null, private ?EventDispatcherInterface $eventDispatcher = null)
        {
            if ($prompt === null) {
                return;
            }
            if (is_string($prompt) && trim($prompt) === '') {
                throw new InvalidArgumentException('Cannot create a message from an empty string.');
            }
            match (true) {
                $prompt instanceof Message => $this->history[] = $prompt,
                $prompt instanceof MessagePart => $this->parts[] = $prompt,
                default => $this->parts[] = new MessagePart(is_scalar($prompt) ? (string) $prompt : json_encode($prompt)),
            };
        }

        public function __clone()
        {
            $this->parts = array_map(static fn (MessagePart $part) => clone $part, $this->parts);
        }

        public function withText(string $text): self
        {
            $this->parts[] = new MessagePart($text);
            return $this;
        }

        public function withFile($file, ?string $mimeType = null): self
        {
            $this->parts[] = new MessagePart($file instanceof File ? $file : new File($file, $mimeType));
            return $this;
        }

        public function withFunctionResponse(FunctionResponse $functionResponse): self
        {
            $this->parts[] = new MessagePart($functionResponse);
            return $this;
        }

        public function withMessageParts(MessagePart ...$parts): self
        {
            array_push($this->parts, ...$parts);
            return $this;
        }

        public function withHistory(Message ...$messages): self
        {
            array_push($this->history, ...$messages);
            return $this;
        }

        public function usingModel(ModelInterface $model): self
        {
            return $this->keep('model', $model);
        }

        public function usingModelPreference(...$preferredModels): self
        {
            return $this->keep('modelPreference', $preferredModels);
        }

        public function usingModelConfig(ModelConfig $config): self
        {
            return $this->keep('modelConfig', $config);
        }

        public function usingProvider(string $providerIdOrClassName): self
        {
            $this->registry->getProviderClassName($providerIdOrClassName);
            return $this->keep('provider', $providerIdOrClassName);
        }

        public function usingSystemInstruction(string $systemInstruction): self
        {
            return $this->keep('systemInstruction', $systemInstruction);
        }

        public function usingMaxTokens(int $maxTokens): self
        {
            return $this->keep('maxTokens', $maxTokens);
        }

        public function usingTemperature(float $temperature): self
        {
            return $this->keep('temperature', $temperature);
        }

        public function usingTopP(float $topP): self
        {
            return $this->keep('topP', $topP);
        }

        public function usingTopK(int $topK): self
        {
            return $this->keep('topK', $topK);
        }

        public function usingStopSequences(string ...$stopSequences): self
        {
            return $this->keep('stopSequences', $stopSequences);
        }

        public function usingCandidateCount(int $candidateCount): self
        {
            return $this->keep('candidateCount', $candidateCount);
        }

        public function usingFunctionDeclarations(FunctionDeclaration ...$functionDeclarations): self
        {
            return $this->keep('functionDeclarations', $functionDeclarations);
        }

        public function usingPresencePenalty(float $presencePenalty): self
        {
            return $this->keep('presencePenalty', $presencePenalty);
        }

        public function usingFrequencyPenalty(float $frequencyPenalty): self
        {
            return $this->keep('frequencyPenalty', $frequencyPenalty);
        }

        public function usingWebSearch(WebSearch $webSearch): self
        {
            return $this->keep('webSearch', $webSearch);
        }

        public function usingRequestOptions(RequestOptions $requestOptions): self
        {
            return $this->keep('requestOptions', $requestOptions);
        }

        public function usingTopLogprobs(?int $topLogprobs = null): self
        {
            return $this->keep('topLogprobs', $topLogprobs);
        }

        public function asOutputMimeType(string $mimeType): self
        {
            return $this->keep('outputMimeType', $mimeType);
        }

        public function asOutputSchema(array $schema): self
        {
            return $this->keep('outputSchema', $schema);
        }

        public function asOutputModalities(ModalityEnum ...$modalities): self
        {
            return $this->keep('outputModalities', $modalities);
        }

        public function asOutputFileType(FileTypeEnum $fileType): self
        {
            return $this->keep('outputFileType', $fileType);
        }

        public function asOutputMediaOrientation(MediaOrientationEnum $orientation): self
        {
            return $this->keep('outputMediaOrientation', $orientation);
        }

        public function asOutputMediaAspectRatio(string $aspectRatio): self
        {
            if (!preg_match('/^\d+:\d+$/', $aspectRatio)) {
                throw new InvalidArgumentException('Output media aspect ratio must be in the format "width:height" (e.g. 3:2, 16:9).');
            }
            return $this->keep('outputMediaAspectRatio', $aspectRatio);
        }

        public function asOutputSpeechVoice(string $voice): self
        {
            return $this->keep('outputSpeechVoice', $voice);
        }

        public function asJsonResponse(?array $schema = null): self
        {
            $this->keep('outputMimeType', 'application/json');
            return $schema === null ? $this : $this->keep('outputSchema', $schema);
        }

        /** Whether a model could take the prompt for the capability (text generation when none is named). */
        public function isSupported(?CapabilityEnum $capability = null): bool
        {
            return $this->hasContent() && $this->registry->findModelsMetadataForSupport([$capability ?? CapabilityEnum::textGeneration(), $this->settings]) !== [];
        }

        public function isSupportedForTextGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::textGeneration());
        }

        public function isSupportedForImageGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::imageGeneration());
        }

        public function isSupportedForTextToSpeechConversion(): bool
        {
            return $this->isSupported(CapabilityEnum::textToSpeechConversion());
        }

        public function isSupportedForVideoGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::videoGeneration());
        }

        public function isSupportedForSpeechGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::speechGeneration());
        }

        public function isSupportedForMusicGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::musicGeneration());
        }

        public function isSupportedForEmbeddingGeneration(): bool
        {
            return $this->isSupported(CapabilityEnum::embeddingGeneration());
        }

        /** A result for the capability (text generation when none is named): content first, then a model that has it. */
        public function generateResult(?CapabilityEnum $capability = null)
        {
            $capability ??= CapabilityEnum::textGeneration();
            if (!$this->hasContent()) {
                throw new InvalidArgumentException('Cannot generate from an empty prompt. Add content using withText() or similar methods.');
            }
            if ($this->registry->findModelsMetadataForSupport([$capability, $this->settings]) === []) {
                throw new InvalidArgumentException(sprintf('No models found that support %s for this prompt.', $capability->value));
            }
            throw new \WordPress\AiClient\Common\Exception\RuntimeException('Generating through a provider is not part of the engine yet.');
        }

        public function generateTextResult()
        {
            return $this->generateResult(CapabilityEnum::textGeneration());
        }

        public function generateImageResult()
        {
            return $this->generateResult(CapabilityEnum::imageGeneration());
        }

        public function generateSpeechResult()
        {
            return $this->generateResult(CapabilityEnum::speechGeneration());
        }

        public function convertTextToSpeechResult()
        {
            return $this->generateResult(CapabilityEnum::textToSpeechConversion());
        }

        public function generateVideoResult()
        {
            return $this->generateResult(CapabilityEnum::videoGeneration());
        }

        public function generateText(): string
        {
            return $this->generateTextResult();
        }

        public function generateTexts(?int $candidateCount = null): array
        {
            return $this->generateTextResult();
        }

        public function generateImage()
        {
            return $this->generateImageResult();
        }

        public function generateImages(?int $candidateCount = null): array
        {
            return $this->generateImageResult();
        }

        public function convertTextToSpeech()
        {
            return $this->convertTextToSpeechResult();
        }

        public function convertTextToSpeeches(?int $candidateCount = null): array
        {
            return $this->convertTextToSpeechResult();
        }

        public function generateSpeech()
        {
            return $this->generateSpeechResult();
        }

        public function generateSpeeches(?int $candidateCount = null): array
        {
            return $this->generateSpeechResult();
        }

        public function generateVideo()
        {
            return $this->generateVideoResult();
        }

        public function generateVideos(?int $candidateCount = null): array
        {
            return $this->generateVideoResult();
        }

        private function keep(string $setting, $value): self
        {
            $this->settings[$setting] = $value;
            return $this;
        }

        private function hasContent(): bool
        {
            return $this->parts !== [] || $this->history !== [];
        }
    }
}
