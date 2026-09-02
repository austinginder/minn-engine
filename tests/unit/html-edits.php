<?php

declare(strict_types=1);

use Minn\Html\Edits;
use Minn\Html\Tags;

$attrs = static fn () => [['name' => 'class', 'lower' => 'class', 'start' => 5, 'end' => 16, 'value' => 'a b', 'quoted' => true]];

return [
    'names with spaces, quotes, or slashes are refused' => static fn () => Edits::validName('data-x') && !Edits::validName('bad name') && !Edits::validName('a"b') && !Edits::validName(''),
    'a set answers reads before it is written' => static function () {
        $e = new Edits();
        $e->setAttribute('Data-Id', '7');
        return $e->attribute('data-id') === ['name' => 'Data-Id', 'value' => '7'] && $e->hasTagEdits();
    },
    'classes after edits: additions appended once, removals gone' => static function () {
        $e = new Edits();
        $e->addClass('c');
        $e->removeClass('a');
        $e->addClass('c');
        return $e->classesAfter(['a', 'b', 'a']) === ['b', 'c'];
    },
    'replacements rewrite the class in place and insert a new attribute after the name' => static function () use ($attrs) {
        $e = new Edits();
        $e->addClass('c');
        $e->setAttribute('id', 'x');
        $r = $e->takeReplacements($attrs(), 4, 'a b');
        return $r === [[5, 16, 'class="a b c"'], [4, 4, ' id="x"']] && !$e->hasTagEdits();
    },
    'removing the last class cuts the attribute' => static function () use ($attrs) {
        $e = new Edits();
        $e->removeClass('a');
        $e->removeClass('b');
        return $e->takeReplacements($attrs(), 4, 'a b') === [[5, 16, '']];
    },
    'text: escaped for a text node, refused for a comment that would close early' => static function () {
        $e = new Edits();
        $ok = $e->setTextFor(Tags::TEXT, null, null, 'a<b&c');
        $text = $e->takeText();
        $refused = !$e->setTextFor(Tags::COMMENT, null, Tags::COMMENT_HTML, 'x --> y');
        $none = !$e->setTextFor(Tags::TAG, 'div', null, 'x');
        return $ok && $text === 'a&lt;b&amp;c' && $refused && $none && $e->takeText() === null;
    },
    'a script body gets its closers escaped' => static function () {
        $e = new Edits();
        return $e->setTextFor(Tags::TAG, 'script', null, 'a</script>b') && $e->takeText() === 'a</\\u0073cript>b';
    },
];
