<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * A feed as it is written: text as given, and what each template tag and
 * action prints, in the order the template asks for them.
 */
final class FeedWriter
{
    private string $out = '';

    /** Text as written. */
    public function put(string ...$text): self
    {
        $this->out .= implode('', $text);
        return $this;
    }

    /** What a function that prints (a template tag) prints, called with its arguments. */
    public function tag(string $function, mixed ...$args): self
    {
        ob_start();
        try {
            $function(...$args);
        } finally {
            $this->out .= (string) ob_get_clean();
        }
        return $this;
    }

    /** What an action's callbacks print. */
    public function act(string $hook, mixed ...$args): self
    {
        return $this->tag('do_action', $hook, ...$args);
    }

    /** Everything written so far. */
    public function text(): string
    {
        return $this->out;
    }
}
