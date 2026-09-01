<?php

declare(strict_types=1);

use Minn\Db;

/** A list parameter stands for a list of placeholders. */
return [
    'a list expands to one placeholder per value' => static function () {
        [$sql, $params] = Db::expand('SELECT 1 WHERE t IN (?) AND s = ?', [['post', 'page'], 'publish']);
        return $sql === 'SELECT 1 WHERE t IN (?, ?) AND s = ?' && $params === ['post', 'page', 'publish'] ?: "{$sql} " . json_encode($params);
    },
    'scalars pass through untouched' => static function () {
        [$sql, $params] = Db::expand('SELECT 1 WHERE s = ? AND n = ?', ['x', 5]);
        return $sql === 'SELECT 1 WHERE s = ? AND n = ?' && $params === ['x', 5];
    },
    'an empty list becomes NULL, which nothing is IN' => static function () {
        [$sql, $params] = Db::expand('SELECT 1 WHERE t IN (?)', [[]]);
        return $sql === 'SELECT 1 WHERE t IN (NULL)' && $params === [];
    },
    'a question mark inside a quoted string is text' => static function () {
        [$sql, $params] = Db::expand("SELECT 1 WHERE q = 'a?b' AND n = ?", [[1, 2]]);
        return $sql === "SELECT 1 WHERE q = 'a?b' AND n = ?, ?" && $params === [1, 2] ?: $sql;
    },
    'several lists expand in order' => static function () {
        [$sql, $params] = Db::expand('a = ? AND b IN (?) AND c IN (?)', [1, [2, 3], [4]]);
        return $sql === 'a = ? AND b IN (?, ?) AND c IN (?)' && $params === [1, 2, 3, 4];
    },
    'string keys on the params do not matter' => static function () {
        [, $params] = Db::expand('a = ? AND b IN (?)', ['first' => 1, 'second' => [2]]);
        return $params === [1, 2];
    },
];
