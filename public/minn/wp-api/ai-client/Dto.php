<?php
/**
 * The AI client SDK's values (probe ai-client-prompt): its enums (a value
 * per constant, named by static calls, asked about by is* calls), the
 * messages and their parts, function calls, responses and declarations,
 * files, and the few model and tool types the prompt builder's methods
 * name. An array form leaves out what is unset; a function response keeps
 * its response even when null.
 */

namespace WordPress\AiClient\Common\Exception {
    use WordPress\AiClient\Common\Contracts\AiClientExceptionInterface;

    class InvalidArgumentException extends \InvalidArgumentException implements AiClientExceptionInterface
    {
    }
}

namespace WordPress\AiClient\Common {
    /** An enum of string constants: Enum::fooBar() is the FOO_BAR case, $case->isFooBar() asks about it. */
    abstract class AbstractEnum implements \JsonSerializable
    {
        private function __construct(private string $enumValue, private string $enumName)
        {
        }

        public function __get(string $property)
        {
            return match ($property) {
                'value' => $this->enumValue,
                'name' => $this->enumName,
                default => throw new \BadMethodCallException(sprintf('Property %s::%s does not exist.', static::class, $property)),
            };
        }

        public function __set(string $property, $value): void
        {
            throw new \BadMethodCallException(sprintf('Cannot modify property %s::%s.', static::class, $property));
        }

        public static function from(string $value): self
        {
            return static::tryFrom($value) ?? throw new \InvalidArgumentException(sprintf('%s is not a valid backing value for enum %s.', $value, static::class));
        }

        public static function tryFrom(string $value): ?self
        {
            $name = array_search($value, static::constants(), true);
            return $name === false ? null : new static($value, (string) $name);
        }

        public static function cases(): array
        {
            return array_map(static fn (string $value) => static::from($value), array_values(static::constants()));
        }

        public function equals($other): bool
        {
            return $other instanceof self ? $this->is($other) : $other === $this->enumValue;
        }

        public function is(self $other): bool
        {
            return $other instanceof static && $other->enumValue === $this->enumValue;
        }

        public static function getValues(): array
        {
            return array_values(static::constants());
        }

        public static function isValidValue(string $value): bool
        {
            return in_array($value, static::constants(), true);
        }

        public function __call(string $name, array $arguments): bool
        {
            $constant = str_starts_with($name, 'is') ? self::constantName(substr($name, 2)) : '';
            if (!array_key_exists($constant, static::constants())) {
                throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
            }
            return static::constants()[$constant] === $this->enumValue;
        }

        public static function __callStatic(string $name, array $arguments): self
        {
            $constant = self::constantName($name);
            if (!array_key_exists($constant, static::constants())) {
                throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
            }
            return new static(static::constants()[$constant], $constant);
        }

        public function __toString(): string
        {
            return $this->enumValue;
        }

        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->enumValue;
        }

        /** @return array<string, string> */
        protected static function constants(): array
        {
            return (new \ReflectionClass(static::class))->getConstants();
        }

        private static function constantName(string $camel): string
        {
            return strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
        }
    }
}

namespace WordPress\AiClient\Messages\Enums {
    use WordPress\AiClient\Common\AbstractEnum;

    class MessageRoleEnum extends AbstractEnum
    {
        public const USER = 'user';
        public const MODEL = 'model';
    }

    class MessagePartTypeEnum extends AbstractEnum
    {
        public const TEXT = 'text';
        public const FILE = 'file';
        public const FUNCTION_CALL = 'function_call';
        public const FUNCTION_RESPONSE = 'function_response';
    }

    class MessagePartChannelEnum extends AbstractEnum
    {
        public const CONTENT = 'content';
        public const THOUGHT = 'thought';
    }

    class ModalityEnum extends AbstractEnum
    {
        public const TEXT = 'text';
        public const DOCUMENT = 'document';
        public const IMAGE = 'image';
        public const AUDIO = 'audio';
        public const VIDEO = 'video';
    }
}

namespace WordPress\AiClient\Files\Enums {
    use WordPress\AiClient\Common\AbstractEnum;

    class FileTypeEnum extends AbstractEnum
    {
        public const INLINE = 'inline';
        public const REMOTE = 'remote';
    }

    class MediaOrientationEnum extends AbstractEnum
    {
        public const SQUARE = 'square';
        public const LANDSCAPE = 'landscape';
        public const PORTRAIT = 'portrait';
    }
}

namespace WordPress\AiClient\Providers\Models\Enums {
    use WordPress\AiClient\Common\AbstractEnum;

    class CapabilityEnum extends AbstractEnum
    {
        public const TEXT_GENERATION = 'text_generation';
        public const IMAGE_GENERATION = 'image_generation';
        public const TEXT_TO_SPEECH_CONVERSION = 'text_to_speech_conversion';
        public const SPEECH_GENERATION = 'speech_generation';
        public const MUSIC_GENERATION = 'music_generation';
        public const VIDEO_GENERATION = 'video_generation';
        public const EMBEDDING_GENERATION = 'embedding_generation';
        public const CHAT_HISTORY = 'chat_history';
    }
}

namespace WordPress\AiClient\Tools\DTO {
    use WordPress\AiClient\Common\AbstractDataTransferObject;

    class FunctionCall extends AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_ARGS = 'args';

        public function __construct(private ?string $id = null, private ?string $name = null, private $args = null)
        {
        }

        public function getId(): ?string
        {
            return $this->id;
        }

        public function getName(): ?string
        {
            return $this->name;
        }

        public function getArgs()
        {
            return $this->args;
        }

        public function toArray(): array
        {
            return array_filter([self::KEY_ID => $this->id, self::KEY_NAME => $this->name, self::KEY_ARGS => $this->args], static fn ($value) => $value !== null);
        }

        public static function fromArray(array $array): self
        {
            return new self($array[self::KEY_ID] ?? null, $array[self::KEY_NAME] ?? null, $array[self::KEY_ARGS] ?? null);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => [self::KEY_ID => ['type' => 'string'], self::KEY_NAME => ['type' => 'string'], self::KEY_ARGS => []]];
        }
    }

    class FunctionResponse extends AbstractDataTransferObject
    {
        public const KEY_ID = 'id';
        public const KEY_NAME = 'name';
        public const KEY_RESPONSE = 'response';

        public function __construct(private ?string $id, private ?string $name, private $response)
        {
        }

        public function getId(): ?string
        {
            return $this->id;
        }

        public function getName(): ?string
        {
            return $this->name;
        }

        public function getResponse()
        {
            return $this->response;
        }

        public function toArray(): array
        {
            return array_filter([self::KEY_ID => $this->id, self::KEY_NAME => $this->name], static fn ($value) => $value !== null) + [self::KEY_RESPONSE => $this->response];
        }

        public static function fromArray(array $array): self
        {
            return new self($array[self::KEY_ID] ?? null, $array[self::KEY_NAME] ?? null, $array[self::KEY_RESPONSE] ?? null);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => [self::KEY_ID => ['type' => 'string'], self::KEY_NAME => ['type' => 'string'], self::KEY_RESPONSE => []], 'required' => [self::KEY_RESPONSE]];
        }
    }

    class FunctionDeclaration extends AbstractDataTransferObject
    {
        public const KEY_NAME = 'name';
        public const KEY_DESCRIPTION = 'description';
        public const KEY_PARAMETERS = 'parameters';

        public function __construct(private string $name, private string $description, private ?array $parameters = null)
        {
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getDescription(): string
        {
            return $this->description;
        }

        public function getParameters(): ?array
        {
            return $this->parameters;
        }

        public function toArray(): array
        {
            return [self::KEY_NAME => $this->name, self::KEY_DESCRIPTION => $this->description] + ($this->parameters === null ? [] : [self::KEY_PARAMETERS => $this->parameters]);
        }

        public static function fromArray(array $array): self
        {
            return new self((string) ($array[self::KEY_NAME] ?? ''), (string) ($array[self::KEY_DESCRIPTION] ?? ''), $array[self::KEY_PARAMETERS] ?? null);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => [self::KEY_NAME => ['type' => 'string'], self::KEY_DESCRIPTION => ['type' => 'string'], self::KEY_PARAMETERS => ['type' => 'object']], 'required' => [self::KEY_NAME, self::KEY_DESCRIPTION]];
        }
    }

    /** A web search a model may run: the domains it may and may not search. */
    class WebSearch extends AbstractDataTransferObject
    {
        public function __construct(private array $allowedDomains = [], private array $disallowedDomains = [])
        {
        }

        public function toArray(): array
        {
            return array_filter(['allowedDomains' => $this->allowedDomains, 'disallowedDomains' => $this->disallowedDomains]);
        }

        public static function fromArray(array $array): self
        {
            return new self((array) ($array['allowedDomains'] ?? []), (array) ($array['disallowedDomains'] ?? []));
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => ['allowedDomains' => ['type' => 'array'], 'disallowedDomains' => ['type' => 'array']]];
        }
    }
}

namespace WordPress\AiClient\Files\DTO {
    use WordPress\AiClient\Common\AbstractDataTransferObject;

    /** A file a prompt carries or a model returns: a URL, or data with its MIME type. */
    class File extends AbstractDataTransferObject
    {
        public function __construct(private $file, private ?string $mimeType = null)
        {
        }

        public function getUrl(): ?string
        {
            return is_string($this->file) && preg_match('#^https?://#i', $this->file) ? $this->file : null;
        }

        public function getMimeType(): ?string
        {
            return $this->mimeType;
        }

        public function toArray(): array
        {
            $url = $this->getUrl();
            return array_filter(['fileType' => $url !== null ? 'remote' : 'inline', 'url' => $url, 'mimeType' => $this->mimeType], static fn ($value) => $value !== null);
        }

        public static function fromArray(array $array): self
        {
            return new self($array['url'] ?? ($array['base64Data'] ?? ''), $array['mimeType'] ?? null);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => ['fileType' => ['type' => 'string'], 'url' => ['type' => 'string'], 'mimeType' => ['type' => 'string']]];
        }
    }
}

namespace WordPress\AiClient\Messages\DTO {
    use WordPress\AiClient\Common\AbstractDataTransferObject;
    use WordPress\AiClient\Common\Exception\InvalidArgumentException;
    use WordPress\AiClient\Files\DTO\File;
    use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
    use WordPress\AiClient\Messages\Enums\MessagePartTypeEnum;
    use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
    use WordPress\AiClient\Tools\DTO\FunctionCall;
    use WordPress\AiClient\Tools\DTO\FunctionResponse;

    /** One part of a message: text, a file, a function call or a function response, on the content or thought channel. */
    class MessagePart extends AbstractDataTransferObject
    {
        public const KEY_CHANNEL = 'channel';
        public const KEY_TYPE = 'type';
        public const KEY_THOUGHT_SIGNATURE = 'thoughtSignature';
        public const KEY_TEXT = 'text';
        public const KEY_FILE = 'file';
        public const KEY_FUNCTION_CALL = 'functionCall';
        public const KEY_FUNCTION_RESPONSE = 'functionResponse';

        private MessagePartChannelEnum $channel;
        private MessagePartTypeEnum $type;

        public function __construct(private $content, ?MessagePartChannelEnum $channel = null, private ?string $thoughtSignature = null)
        {
            $this->channel = $channel ?? MessagePartChannelEnum::content();
            $this->type = match (true) {
                is_string($content) => MessagePartTypeEnum::text(),
                $content instanceof File => MessagePartTypeEnum::file(),
                $content instanceof FunctionCall => MessagePartTypeEnum::functionCall(),
                $content instanceof FunctionResponse => MessagePartTypeEnum::functionResponse(),
                default => throw new InvalidArgumentException('Message part content must be a string, a file, a function call or a function response.'),
            };
        }

        public function getChannel(): MessagePartChannelEnum
        {
            return $this->channel;
        }

        public function getType(): MessagePartTypeEnum
        {
            return $this->type;
        }

        public function getThoughtSignature(): ?string
        {
            return $this->thoughtSignature;
        }

        public function getText(): ?string
        {
            return is_string($this->content) ? $this->content : null;
        }

        public function getFile(): ?File
        {
            return $this->content instanceof File ? $this->content : null;
        }

        public function getFunctionCall(): ?FunctionCall
        {
            return $this->content instanceof FunctionCall ? $this->content : null;
        }

        public function getFunctionResponse(): ?FunctionResponse
        {
            return $this->content instanceof FunctionResponse ? $this->content : null;
        }

        public function toArray(): array
        {
            $key = [MessagePartTypeEnum::TEXT => self::KEY_TEXT, MessagePartTypeEnum::FILE => self::KEY_FILE, MessagePartTypeEnum::FUNCTION_CALL => self::KEY_FUNCTION_CALL, MessagePartTypeEnum::FUNCTION_RESPONSE => self::KEY_FUNCTION_RESPONSE][$this->type->value];
            $out = [self::KEY_CHANNEL => $this->channel->value, self::KEY_TYPE => $this->type->value, $key => is_string($this->content) ? $this->content : $this->content->toArray()];
            return $out + ($this->thoughtSignature === null ? [] : [self::KEY_THOUGHT_SIGNATURE => $this->thoughtSignature]);
        }

        public static function fromArray(array $array): self
        {
            $content = $array[self::KEY_TEXT] ?? (isset($array[self::KEY_FUNCTION_CALL]) ? FunctionCall::fromArray($array[self::KEY_FUNCTION_CALL]) : (isset($array[self::KEY_FUNCTION_RESPONSE]) ? FunctionResponse::fromArray($array[self::KEY_FUNCTION_RESPONSE]) : File::fromArray((array) ($array[self::KEY_FILE] ?? []))));
            return new self($content, isset($array[self::KEY_CHANNEL]) ? MessagePartChannelEnum::from($array[self::KEY_CHANNEL]) : null, $array[self::KEY_THOUGHT_SIGNATURE] ?? null);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => [self::KEY_CHANNEL => ['type' => 'string'], self::KEY_TYPE => ['type' => 'string']], 'required' => [self::KEY_TYPE]];
        }

        public function __clone()
        {
            $this->content = is_object($this->content) ? clone $this->content : $this->content;
        }
    }

    /** A message: its role and its parts. */
    class Message extends AbstractDataTransferObject
    {
        public const KEY_ROLE = 'role';
        public const KEY_PARTS = 'parts';

        /** @var list<MessagePart> */
        private array $parts;

        public function __construct(private MessageRoleEnum $role, array $parts)
        {
            foreach ($parts as $part) {
                if (!$part instanceof MessagePart) {
                    throw new InvalidArgumentException('Message parts must be MessagePart objects.');
                }
            }
            $this->parts = array_values($parts);
        }

        public function getRole(): MessageRoleEnum
        {
            return $this->role;
        }

        public function getParts(): array
        {
            return $this->parts;
        }

        public function withPart(MessagePart $part): Message
        {
            $new = clone $this;
            $new->parts[] = $part;
            return $new;
        }

        public function toArray(): array
        {
            return [self::KEY_ROLE => $this->role->value, self::KEY_PARTS => array_map(static fn (MessagePart $part) => $part->toArray(), $this->parts)];
        }

        public static function fromArray(array $array): self
        {
            $parts = array_map(static fn (array $part) => MessagePart::fromArray($part), (array) ($array[self::KEY_PARTS] ?? []));
            return new self(MessageRoleEnum::from((string) ($array[self::KEY_ROLE] ?? 'user')), $parts);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object', 'properties' => [self::KEY_ROLE => ['type' => 'string', 'enum' => MessageRoleEnum::getValues()], self::KEY_PARTS => ['type' => 'array']], 'required' => [self::KEY_ROLE, self::KEY_PARTS]];
        }

        public function __clone()
        {
            $this->parts = array_map(static fn (MessagePart $part) => clone $part, $this->parts);
        }
    }

    class UserMessage extends Message
    {
        public function __construct(array $parts)
        {
            parent::__construct(MessageRoleEnum::user(), $parts);
        }
    }

    class ModelMessage extends Message
    {
        public function __construct(array $parts)
        {
            parent::__construct(MessageRoleEnum::model(), $parts);
        }
    }
}

namespace WordPress\AiClient\Providers\Models\Contracts {
    /** A model a provider offers. */
    interface ModelInterface
    {
    }
}

namespace WordPress\AiClient\Providers\Models\DTO {
    use WordPress\AiClient\Common\AbstractDataTransferObject;

    /** A model's settings, as given. */
    class ModelConfig extends AbstractDataTransferObject
    {
        public function __construct(private array $config = [])
        {
        }

        public function toArray(): array
        {
            return $this->config;
        }

        public static function fromArray(array $array): self
        {
            return new self($array);
        }

        public static function getJsonSchema(): array
        {
            return ['type' => 'object'];
        }
    }
}
