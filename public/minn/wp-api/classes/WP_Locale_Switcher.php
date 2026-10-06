<?php

use Minn\Runtime\Runtime;

/**
 * Switching the request's locale and back, as the reference does (probe
 * placeholders-a): a locale that is already in force, or not installed
 * (en_US always is), is refused; switching fires change_locale then
 * switch_locale with the user whose locale it is; restoring fires
 * change_locale then restore_previous_locale with the locale left; going
 * back to the request's own locale restores from there. The stack lives in
 * the runtime, so every switcher sees one state.
 */
#[AllowDynamicProperties]
class WP_Locale_Switcher
{
    public function init()
    {
        add_filter('locale', [$this, 'filter_locale']);
        add_filter('determine_locale', [$this, 'filter_locale']);
    }

    public function switch_to_locale($locale, $user_id = false)
    {
        $locale = (string) $locale;
        if ($locale === determine_locale() || ($locale !== 'en_US' && !in_array($locale, get_available_languages(), true))) {
            return false;
        }
        Runtime::locales()->push($locale, $user_id === false ? false : (int) $user_id);
        $this->changed($locale);
        do_action('switch_locale', $locale, $user_id);
        return true;
    }

    public function switch_to_user_locale($user_id)
    {
        return $this->switch_to_locale(get_user_locale($user_id), (int) $user_id);
    }

    public function restore_previous_locale()
    {
        if (!Runtime::locales()->switched()) {
            return false;
        }
        $previous = (string) Runtime::locales()->current();
        $locale = Runtime::locales()->pop() ?? determine_locale();
        $this->changed($locale);
        do_action('restore_previous_locale', $locale, $previous);
        return $locale;
    }

    public function restore_current_locale()
    {
        if (!Runtime::locales()->switched()) {
            return false;
        }
        // Back to the request's own locale, restored from as though it were the only one switched in.
        Runtime::locales()->clear();
        $locale = determine_locale();
        $this->changed($locale);
        do_action('restore_previous_locale', $locale, $locale);
        return $locale;
    }

    public function is_switched()
    {
        return Runtime::locales()->switched();
    }

    public function get_switched_locale()
    {
        return Runtime::locales()->current() ?? false;
    }

    public function get_switched_user_id()
    {
        return Runtime::locales()->currentUser();
    }

    public function filter_locale($locale)
    {
        return Runtime::locales()->current() ?? $locale;
    }

    private function changed(string $locale): void
    {
        _minn_reload_textdomains($locale);
        do_action('change_locale', $locale);
    }
}
