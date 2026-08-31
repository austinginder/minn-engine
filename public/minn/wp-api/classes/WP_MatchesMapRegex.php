<?php
/** The $matches[N] substitution rewrite rules use; substituted values are urlencoded (probed: 'ab c' becomes 'ab+c'). */
class WP_MatchesMapRegex
{
    private $_matches;
    public $output;
    private $_subject;
    public $_pattern = '(\$matches\[[1-9]+[0-9]*\])';

    public function __construct($subject, $matches)
    {
        $this->_subject = (string) $subject;
        $this->_matches = (array) $matches;
        $this->output = $this->_map();
    }

    public static function apply($subject, $matches)
    {
        $oSelf = new self($subject, $matches);
        return $oSelf->output;
    }

    private function _map()
    {
        return preg_replace_callback('#' . $this->_pattern . '#', [$this, 'callback'], $this->_subject);
    }

    public function callback($matches)
    {
        $index = (int) substr($matches[0], 9, -1);
        return isset($this->_matches[$index]) ? urlencode((string) $this->_matches[$index]) : '';
    }
}
