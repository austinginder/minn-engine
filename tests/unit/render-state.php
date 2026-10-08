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
    'with no request behind it, the state adopted last is the one current() answers with' => static function () {
        $s = new RenderState();
        $s->adopt();
        return RenderState::current() === $s;
    },
    'root padding is render configuration, so a reset leaves it alone' => static function () {
        $s = new RenderState();
        $s->useRootPadding(false);
        $s->nextId();
        $s->reset();
        return $s->rootPaddingAware() === false && $s->nextId() === 1;
    },
    'a fresh state assumes the theme puts root padding in custom properties' => static function () {
        return (new RenderState())->rootPaddingAware() === true;
    },
    'galleries are recorded for the stylesheet' => static function () {
        $s = new RenderState();
        $s->recordGallery(2);
        return $s->galleries() === [2];
    },
    'block gaps are render configuration, so a reset leaves them alone' => static function () {
        $s = new RenderState();
        $fresh = $s->blockGap();
        $s->useBlockGap(false);
        $s->reset();
        return $fresh === true && $s->blockGap() === false;
    },
];
