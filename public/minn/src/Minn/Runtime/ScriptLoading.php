<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * How a script may load, as the reference decides it (probe
 * dependencies-classes). A script keeps its own strategy (defer or async)
 * unless it has inline code after it, which makes it blocking, or an
 * enqueued dependent (however deep, through dependents that are themselves
 * enqueued) loads more strictly: blocking before defer before async. Its
 * fetch priority rises to the highest among those dependents: low, then
 * auto, then high. A delayed script that cannot be delayed (inline code
 * after it) waits for the footer when nothing enqueued depends on it. The
 * values a script may be given are checked here too.
 */
final class ScriptLoading
{
    private const STRATEGIES = ['' => 0, 'defer' => 1, 'async' => 2];
    private const PRIORITIES = ['low' => 0, 'auto' => 1, 'high' => 2];

    public function __construct(private readonly Assets $assets)
    {
    }

    /**
     * Why a strategy or fetch priority value is refused, as the notice the
     * reference gives; null when the value is fine (empty is fine).
     *
     * @return array{0: string, 1: string}|null the message and the version
     */
    public static function refusal(string $handle, string $key, mixed $value): ?array
    {
        return match (true) {
            $key === 'strategy' && !empty($value) && !in_array($value, ['defer', 'async'], true) => [sprintf('Invalid strategy `%1$s` defined for `%2$s` during script registration.', (string) $value, $handle), '6.3.0'],
            $key === 'fetchpriority' && !empty($value) && !isset(self::PRIORITIES[(string) $value]) => [sprintf('Invalid fetchpriority `%1$s` defined for `%2$s` during script registration.', (string) $value, $handle), '6.9.0'],
            default => null,
        };
    }

    /** The strategy a script loads with: 'defer', 'async', or '' for blocking. @param array<string, true> $checked */
    public function strategy(string $handle, array $checked = []): string
    {
        $item = $this->assets->item($handle);
        $own = (string) ($item['data']['strategy'] ?? '');
        if ($own === '' || !isset(self::STRATEGIES[$own]) || ($item['inline']['after'] ?? []) !== []) {
            return '';
        }
        $checked[$handle] = true;
        foreach ($this->enqueuedDependents($handle, $checked) as $dependent) {
            $theirs = $this->strategy($dependent, $checked);
            $own = self::STRATEGIES[$theirs] < self::STRATEGIES[$own] ? $theirs : $own;
        }
        return $own;
    }

    /**
     * Whether the head pass holds a script for the footer: one that asked to
     * be delayed but has inline code after it, with nothing enqueued that
     * depends on it.
     */
    public function heldForFooter(string $handle): bool
    {
        $item = $this->assets->item($handle);
        $asked = (string) ($item['data']['strategy'] ?? '');
        return ($asked === 'defer' || $asked === 'async') && ($item['inline']['after'] ?? []) !== [] && $this->enqueuedDependents($handle, []) === [];
    }

    /** A script's own fetch priority and the one it loads with. @return array{0: string, 1: string} */
    public function priority(string $handle): array
    {
        $own = $this->ownPriority($handle);
        return [$own, $this->raised($handle, $own, [])];
    }

    /** @param array<string, true> $checked */
    private function raised(string $handle, string $priority, array $checked): string
    {
        $checked[$handle] = true;
        foreach ($this->enqueuedDependents($handle, $checked) as $dependent) {
            $theirs = $this->raised($dependent, $this->ownPriority($dependent), $checked);
            $priority = self::PRIORITIES[$theirs] > self::PRIORITIES[$priority] ? $theirs : $priority;
        }
        return $priority;
    }

    private function ownPriority(string $handle): string
    {
        $priority = (string) ($this->assets->data($handle, 'fetchpriority') ?: 'auto');
        return isset(self::PRIORITIES[$priority]) ? $priority : 'auto';
    }

    /** @param array<string, true> $checked @return list<string> */
    private function enqueuedDependents(string $handle, array $checked): array
    {
        return array_values(array_filter($this->assets->dependents($handle), fn (string $dependent): bool => !isset($checked[$dependent]) && $this->assets->enqueued($dependent)));
    }
}
