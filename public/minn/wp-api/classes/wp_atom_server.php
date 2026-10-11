<?php

/**
 * The Atom Publishing Protocol server WordPress dropped in 3.5.0. Any call
 * on it, an object's or the class's, is reported deprecated in favour of
 * the plugin that carries it on, and answers nothing (probe loose-classes).
 */
class wp_atom_server
{
    public function __call($name, $arguments)
    {
        _deprecated_function(self::class . '::' . $name, '3.5.0', 'the Atom Publishing Protocol plugin');
    }

    public static function __callStatic($name, $arguments)
    {
        _deprecated_function(self::class . '::' . $name, '3.5.0', 'the Atom Publishing Protocol plugin');
    }
}
