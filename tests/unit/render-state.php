<?php

declare(strict_types=1);

use Minn\Blocks\RenderState;

return [
    'one counter numbers everything in order and reset starts it over' => static function () {
        $s = new RenderState();
        $a = $s->nextId();
        $b = $s->nextId();
        $s->reset();
        return $a === 1 && $b === 2 && $s->nextId() === 1;
    },
    'a key can be entered once at a time: the cycle guard' => static function () {
        $s = new RenderState();
        $first = $s->enter('part:header');
        $again = $s->enter('part:header');
        $s->leave('part:header');
        return $first && !$again && $s->enter('part:header');
    },
    'the request state is whichever instance adopted last' => static function () {
        $s = new RenderState();
        $s->adopt();
        return RenderState::current() === $s;
    },
    'variations and galleries are recorded for the stylesheet' => static function () {
        $s = new RenderState();
        $s->recordVariation('core/button', 'outline', 3);
        $s->recordGallery(2);
        return $s->variations() === [['core/button', 'outline', 3]] && $s->galleries() === [2];
    },
];
