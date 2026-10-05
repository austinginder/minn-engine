<?php
/** Drop-in probe: a database class that extends wpdb and counts queries, the way Query Monitor's db.php does. */

if (!defined('ABSPATH')) {
    exit;
}

class Minn_Probe_DB extends wpdb
{
    public $probe_queries = 0;

    public function query($query)
    {
        $this->probe_queries++;
        return parent::query($query);
    }
}

$wpdb = new Minn_Probe_DB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
