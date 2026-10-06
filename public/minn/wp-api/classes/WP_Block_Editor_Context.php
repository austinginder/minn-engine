<?php

/** Which editor a block editor setting is for, and the post it edits, if any. */
#[AllowDynamicProperties]
final class WP_Block_Editor_Context
{
    public $name = 'core/edit-post';
    public $post = null;

    public function __construct(array $settings = [])
    {
        if (isset($settings['name'])) {
            $this->name = $settings['name'];
        }
        if (isset($settings['post'])) {
            $this->post = $settings['post'];
        }
    }
}
