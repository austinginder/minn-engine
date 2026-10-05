<?php
/** The content-type sniffer class name plugins pass to set_content_type_sniffer_class(): the engine sniffs in Minn\Feed\Locator. */

namespace SimplePie\Content\Type;

class Sniffer
{
    public $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    /** The response's own Content-Type, else text/plain. */
    public function get_type()
    {
        return strtolower(trim(explode(';', (string) ($this->file->headers['content-type'] ?? 'text/plain'))[0]));
    }
}
