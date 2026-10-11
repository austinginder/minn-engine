<?php
/**
 * The parts of the AI client SDK the WordPress adapters stand on, under the
 * names the reference ships (probe ai-client-adapters): its exception
 * contract and runtime exception, the data object base, the request
 * options, the network exception, the client-with-options contract, and
 * the HTTP client discovery the SDK finds its client through.
 */

namespace WordPress\AiClient\Common\Contracts {
    interface AiClientExceptionInterface extends \Throwable
    {
    }

    interface WithJsonSchemaInterface
    {
        public static function getJsonSchema(): array;
    }

    interface WithArrayTransformationInterface
    {
        public function toArray(): array;

        public static function fromArray(array $array);

        public static function isArrayShape(array $array): bool;
    }
}

namespace WordPress\AiClient\Common\Exception {
    use WordPress\AiClient\Common\Contracts\AiClientExceptionInterface;

    class RuntimeException extends \RuntimeException implements AiClientExceptionInterface
    {
    }
}

namespace WordPress\AiClient\Common {
    use WordPress\AiClient\Common\Contracts\WithArrayTransformationInterface;
    use WordPress\AiClient\Common\Contracts\WithJsonSchemaInterface;

    abstract class AbstractDataTransferObject implements \JsonSerializable, WithJsonSchemaInterface, WithArrayTransformationInterface
    {
        /** Whether the array holds every property the schema requires. */
        public static function isArrayShape(array $array): bool
        {
            return array_diff((array) (static::getJsonSchema()['required'] ?? []), array_keys($array)) === [];
        }

        #[\ReturnTypeWillChange]
        public function jsonSerialize()
        {
            return $this->toArray();
        }
    }
}

namespace WordPress\AiClient\Providers\Http\DTO {
    use WordPress\AiClient\Common\AbstractDataTransferObject;

    /** How long a request may take, how long to connect, and how many redirects to follow; each unset until given. */
    class RequestOptions extends AbstractDataTransferObject
    {
        public const KEY_TIMEOUT = 'timeout';
        public const KEY_CONNECT_TIMEOUT = 'connectTimeout';
        public const KEY_MAX_REDIRECTS = 'maxRedirects';

        protected ?float $timeout = null;
        protected ?float $connectTimeout = null;
        protected ?int $maxRedirects = null;

        public function setTimeout(?float $timeout): void
        {
            $this->timeout = $timeout;
        }

        public function setConnectTimeout(?float $timeout): void
        {
            $this->connectTimeout = $timeout;
        }

        public function setMaxRedirects(?int $maxRedirects): void
        {
            $this->maxRedirects = $maxRedirects;
        }

        public function getTimeout(): ?float
        {
            return $this->timeout;
        }

        public function getConnectTimeout(): ?float
        {
            return $this->connectTimeout;
        }

        /** Null until a redirect limit is given; then whether it is above zero. */
        public function allowsRedirects(): ?bool
        {
            return $this->maxRedirects === null ? null : $this->maxRedirects > 0;
        }

        public function getMaxRedirects(): ?int
        {
            return $this->maxRedirects;
        }

        public function toArray(): array
        {
            return array_filter([self::KEY_TIMEOUT => $this->timeout, self::KEY_CONNECT_TIMEOUT => $this->connectTimeout, self::KEY_MAX_REDIRECTS => $this->maxRedirects], static fn ($value) => $value !== null);
        }

        public static function fromArray(array $array): self
        {
            $options = new self();
            $options->setTimeout(isset($array[self::KEY_TIMEOUT]) ? (float) $array[self::KEY_TIMEOUT] : null);
            $options->setConnectTimeout(isset($array[self::KEY_CONNECT_TIMEOUT]) ? (float) $array[self::KEY_CONNECT_TIMEOUT] : null);
            $options->setMaxRedirects(isset($array[self::KEY_MAX_REDIRECTS]) ? (int) $array[self::KEY_MAX_REDIRECTS] : null);
            return $options;
        }

        public static function getJsonSchema(): array
        {
            $number = ['type' => ['number', 'null'], 'minimum' => 0];
            return ['type' => 'object', 'properties' => [self::KEY_TIMEOUT => $number, self::KEY_CONNECT_TIMEOUT => $number, self::KEY_MAX_REDIRECTS => ['type' => ['integer', 'null'], 'minimum' => 0]], 'additionalProperties' => false];
        }
    }
}

namespace WordPress\AiClient\Providers\Http\Exception {
    use WordPress\AiClient\Common\Exception\RuntimeException;

    class NetworkException extends RuntimeException
    {
    }
}

namespace WordPress\AiClient\Providers\Http\Contracts {
    use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
    use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
    use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;

    interface ClientWithOptionsInterface
    {
        public function sendRequestWithOptions(RequestInterface $request, RequestOptions $options): ResponseInterface;
    }
}

namespace WordPress\AiClientDependencies\Http\Discovery {
    use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;

    /** The strategies a discovery asks, in order, for a class of a type. */
    abstract class ClassDiscovery
    {
        private static array $strategies = [];

        public static function setStrategies(array $strategies)
        {
            self::$strategies = array_values($strategies);
        }

        public static function getStrategies()
        {
            return self::$strategies;
        }

        public static function appendStrategy($strategy)
        {
            self::$strategies[] = $strategy;
        }

        public static function prependStrategy($strategy)
        {
            array_unshift(self::$strategies, $strategy);
        }

        public static function clearCache()
        {
        }

        public static function safeClassExists($class)
        {
            try {
                return class_exists($class) || interface_exists($class);
            } catch (\Throwable) {
                return false;
            }
        }

        /** The first candidate a strategy offers for the type; null when none does. */
        protected static function findOneByType($type)
        {
            foreach (self::$strategies as $strategy) {
                foreach ((array) $strategy::getCandidates($type) as $candidate) {
                    if (isset($candidate['class'])) {
                        return $candidate['class'];
                    }
                }
            }
            return null;
        }

        protected static function instantiateClass($class)
        {
            return is_callable($class) ? $class() : new $class();
        }
    }

    final class Psr18ClientDiscovery extends ClassDiscovery
    {
        /** The HTTP client the strategies offer; an error when none does. */
        public static function find()
        {
            $class = static::findOneByType(ClientInterface::class);
            if ($class === null) {
                throw new \RuntimeException('No PSR-18 client found.');
            }
            return static::instantiateClass($class);
        }
    }
}

namespace WordPress\AiClient\Providers\Http\Abstracts {
    use WordPress\AiClientDependencies\Http\Discovery\Psr18ClientDiscovery;
    use WordPress\AiClientDependencies\Http\Discovery\Strategy\DiscoveryStrategy;
    use WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory;
    use WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface;

    /** A strategy that offers the HTTP client createClient() makes, first among the strategies once init() runs. */
    abstract class AbstractClientDiscoveryStrategy implements DiscoveryStrategy
    {
        public static function init(): void
        {
            Psr18ClientDiscovery::prependStrategy(static::class);
        }

        public static function getCandidates($type)
        {
            if ($type !== ClientInterface::class) {
                return [];
            }
            return [['class' => static fn () => static::createClient(new Psr17Factory())]];
        }

        abstract protected static function createClient(Psr17Factory $psr17Factory): ClientInterface;
    }
}
