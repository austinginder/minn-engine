<?php
/**
 * Behaviour probe for plain data objects a plugin keeps in its settings and
 * reads back (WooCommerce PayPal Payments keeps its Pay Later settings so):
 * maybe_unserialize of what serialize() wrote, and an option holding them
 * read from the database. Each comes back as its class, every property set
 * (public, protected, private, a parent's private one of the same name,
 * readonly, typed, nested), its constructor not run. Classes that run code
 * as they are revived or let go (__wakeup, __unserialize, __destruct) are
 * left out: the engine keeps those a stdClass on purpose, and
 * tests/unit/stored-objects.php holds that side. Same protocol as
 * api-probe.php; the option it writes is removed.
 */
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
const ZZ_PROBE_STORED_OPTION = 'zz_minn_probe_stored_objects';
$hadOption = get_option(ZZ_PROBE_STORED_OPTION, null);
register_shutdown_function(static function () use ($hadOption): void {
    if ($hadOption === null) {
        delete_option(ZZ_PROBE_STORED_OPTION);
    }
});

if (!class_exists('ZzProbeStoredBase', false)) {
    class ZzProbeStoredBase
    {
        private string $secret = 'base-secret';
        protected int $level = 1;
    }
    final class ZzProbeStoredDto extends ZzProbeStoredBase
    {
        public static int $built = 0;
        public string $name;
        public bool $enabled = false;
        public array $list = [];
        public ?ZzProbeStoredDto $child = null;
        protected string $guarded = 'g';
        private string $secret = 'own-secret';
        public readonly string $fixed;
        public function __construct(string $name, bool $enabled = false, string $fixed = 'f')
        {
            self::$built++;
            $this->name = $name;
            $this->enabled = $enabled;
            $this->fixed = $fixed;
        }
    }
    class ZzProbeStoredLoose
    {
        public $anything;
        public $more = 'default';
    }
}

// Every property as (array) shows it, the visibility markers spelled out.
$describe = static function ($value) use (&$describe) {
    if (is_object($value)) {
        $properties = [];
        foreach ((array) $value as $key => $item) {
            $properties[str_replace("\0", '|', (string) $key)] = $describe($item);
        }
        return ['class' => get_class($value), 'properties' => $properties];
    }
    return is_array($value) ? array_map($describe, $value) : $value;
};

$dto = new ZzProbeStoredDto('cart', true, 'fixed-value');
$dto->list = ['inline', 2, null];
$dto->child = new ZzProbeStoredDto('nested');
$loose = new ZzProbeStoredLoose();
$loose->anything = ['k' => new ZzProbeStoredDto('in-loose')];
$builtBefore = ZzProbeStoredDto::$built;

$say('a plain object read back', $describe(maybe_unserialize(serialize($dto))));
$say('read back equal to what was stored', maybe_unserialize(serialize($dto)) == $dto);
$say('objects in a list and in an untyped property', $describe(maybe_unserialize(serialize(['cart' => $dto, 'loose' => $loose]))));
$say('no constructor ran while reading', ZzProbeStoredDto::$built === $builtBefore);
$say('written back the same', serialize(maybe_unserialize(serialize($dto))) === serialize($dto));

// An option a plugin saved, read from the database rather than the request's cache.
update_option(ZZ_PROBE_STORED_OPTION, ['cart' => $dto, 'checkout' => new ZzProbeStoredDto('checkout')], false);
wp_cache_delete(ZZ_PROBE_STORED_OPTION, 'options');
wp_cache_delete('alloptions', 'options');
$stored = get_option(ZZ_PROBE_STORED_OPTION);
$say('an option holding them, read from the database', $describe($stored));
$say('its getter sees its own class', is_array($stored) && ($stored['cart'] ?? null) instanceof ZzProbeStoredDto);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
