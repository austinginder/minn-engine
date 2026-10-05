<?php
/**
 * The Requests library's small utilities: the case-insensitive dictionary
 * headers live in, the response headers that keep every value of a
 * repeated header, an iterator that maps values through a callback, and
 * the input checks behind its argument errors.
 */

namespace WpOrg\Requests\Utility {
    use ArrayAccess;
    use ArrayIterator;
    use CurlHandle;
    use ReturnTypeWillChange;
    use Traversable;
    use WpOrg\Requests\Exception;
    use WpOrg\Requests\Exception\InvalidArgument;

    class CaseInsensitiveDictionary implements ArrayAccess, \IteratorAggregate
    {
        protected $data = [];

        public function __construct(array $data = [])
        {
            foreach ($data as $offset => $value) {
                $this->offsetSet($offset, $value);
            }
        }

        #[ReturnTypeWillChange]
        public function offsetExists($offset)
        {
            return isset($this->data[is_string($offset) ? strtolower($offset) : $offset]);
        }

        #[ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->data[is_string($offset) ? strtolower($offset) : $offset] ?? null;
        }

        #[ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            if ($offset === null) {
                throw new Exception('Object is a dictionary, not a list', 'invalidset');
            }
            $this->data[is_string($offset) ? strtolower($offset) : $offset] = $value;
        }

        #[ReturnTypeWillChange]
        public function offsetUnset($offset)
        {
            unset($this->data[is_string($offset) ? strtolower($offset) : $offset]);
        }

        #[ReturnTypeWillChange]
        public function getIterator()
        {
            return new ArrayIterator($this->data);
        }

        public function getAll()
        {
            return $this->data;
        }
    }

    /** An array iterator whose values pass through a callback on the way out. */
    final class FilteredIterator extends ArrayIterator
    {
        private $callback;

        public function __construct($data, $callback)
        {
            if (!InputValidator::is_iterable($data)) {
                throw InvalidArgument::create(1, '$data', 'iterable', gettype($data));
            }
            parent::__construct($data instanceof Traversable ? iterator_to_array($data) : $data);
            $this->callback = is_callable($callback) ? $callback : null;
        }

        public function __unserialize($data): void
        {
        }

        public function __wakeup(): void
        {
            unset($this->callback);
        }

        #[ReturnTypeWillChange]
        public function current()
        {
            $value = parent::current();
            return $this->callback !== null ? call_user_func($this->callback, $value) : $value;
        }

        #[ReturnTypeWillChange]
        public function unserialize($data)
        {
        }
    }

    final class InputValidator
    {
        public static function is_string_or_stringable($input)
        {
            return is_string($input) || self::is_stringable_object($input);
        }

        public static function is_numeric_array_key($input)
        {
            return is_int($input) || (is_string($input) && preg_match('/^-?[0-9]+$/', $input) === 1);
        }

        public static function is_stringable_object($input)
        {
            return is_object($input) && method_exists($input, '__toString');
        }

        public static function has_array_access($input)
        {
            return is_array($input) || $input instanceof ArrayAccess;
        }

        public static function is_iterable($input)
        {
            return is_array($input) || $input instanceof Traversable;
        }

        public static function is_curl_handle($input)
        {
            return $input instanceof CurlHandle;
        }
    }
}

namespace WpOrg\Requests\Response {
    use ReturnTypeWillChange;
    use WpOrg\Requests\Exception;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Utility\CaseInsensitiveDictionary;
    use WpOrg\Requests\Utility\FilteredIterator;

    /** Response headers: each name keeps all its values; reading one joins them with commas. */
    class Headers extends CaseInsensitiveDictionary
    {
        #[ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            $offset = is_string($offset) ? strtolower($offset) : $offset;
            return isset($this->data[$offset]) ? $this->flatten($this->data[$offset]) : null;
        }

        #[ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            if ($offset === null) {
                throw new Exception('Object is a dictionary, not a list', 'invalidset');
            }
            $this->data[is_string($offset) ? strtolower($offset) : $offset][] = $value;
        }

        public function getValues($offset)
        {
            return $this->data[is_string($offset) ? strtolower($offset) : $offset] ?? null;
        }

        public function flatten($value)
        {
            if (is_string($value)) {
                return $value;
            }
            if (is_array($value)) {
                return implode(',', $value);
            }
            throw InvalidArgument::create(1, '$value', 'string|array', gettype($value));
        }

        #[ReturnTypeWillChange]
        public function getIterator()
        {
            return new FilteredIterator($this->data, [$this, 'flatten']);
        }
    }
}
