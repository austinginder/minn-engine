<?php

/**
 * Session tokens as plugins see them (probe session-tokens): the manager
 * session_token_manager names, a session made with whatever
 * attach_session_information adds and where and when it began, kept as
 * the sha256 of its token; read, verified, listed, updated and ended one
 * by one, all but one, or all. The store is the user's session_tokens
 * meta, which the engine's own sign-in reads (Minn\Auth\Sessions).
 */
#[AllowDynamicProperties]
abstract class WP_Session_Tokens
{
    protected $user_id;

    protected function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    final public static function get_instance($user_id)
    {
        $manager = apply_filters('session_token_manager', 'WP_User_Meta_Session_Tokens');
        return new $manager($user_id);
    }

    private function hash_token($token)
    {
        return hash('sha256', (string) $token);
    }

    final public function get($token)
    {
        return $this->get_session($this->hash_token($token));
    }

    final public function verify($token)
    {
        return (bool) $this->get_session($this->hash_token($token));
    }

    /** A new session until the expiration, with what plugins attach and where and when it began; its token. */
    final public function create($expiration)
    {
        $session = apply_filters('attach_session_information', [], $this->user_id);
        $session['expiration'] = $expiration;
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $session['ip'] = $_SERVER['REMOTE_ADDR'];
        }
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $session['ua'] = wp_unslash($_SERVER['HTTP_USER_AGENT']);
        }
        $session['login'] = time();
        $token = wp_generate_password(43, false, false);
        $this->update($token, $session);
        return $token;
    }

    final public function update($token, $session)
    {
        $this->update_session($this->hash_token($token), $session);
    }

    final public function destroy($token)
    {
        $this->update_session($this->hash_token($token), null);
    }

    final public function destroy_others($token_to_keep)
    {
        $verifier = $this->hash_token($token_to_keep);
        if ($this->get_session($verifier)) {
            $this->destroy_other_sessions($verifier);
        } else {
            $this->destroy_all_sessions();
        }
    }

    final protected function is_still_valid($session)
    {
        return $session['expiration'] >= time();
    }

    final public function destroy_all()
    {
        $this->destroy_all_sessions();
    }

    final public static function destroy_all_for_all_users()
    {
        $manager = apply_filters('session_token_manager', 'WP_User_Meta_Session_Tokens');
        call_user_func([$manager, 'drop_sessions']);
    }

    final public function get_all()
    {
        return array_values($this->get_sessions());
    }

    abstract protected function get_sessions();

    abstract protected function get_session($verifier);

    abstract protected function update_session($verifier, $session = null);

    abstract protected function destroy_other_sessions($verifier);

    abstract protected function destroy_all_sessions();

    public static function drop_sessions()
    {
    }
}

/** The sessions in the user's session_tokens meta, an old bare expiration read as a session, the expired left out. */
class WP_User_Meta_Session_Tokens extends WP_Session_Tokens
{
    protected function get_sessions()
    {
        $sessions = get_user_meta($this->user_id, 'session_tokens', true);
        if (!is_array($sessions)) {
            return [];
        }
        return array_filter(array_map([$this, 'prepare_session'], $sessions), [$this, 'is_still_valid']);
    }

    protected function prepare_session($session)
    {
        return is_int($session) ? ['expiration' => $session] : $session;
    }

    protected function get_session($verifier)
    {
        return $this->get_sessions()[$verifier] ?? null;
    }

    protected function update_session($verifier, $session = null)
    {
        $sessions = $this->get_sessions();
        if ($session) {
            $sessions[$verifier] = $session;
        } else {
            unset($sessions[$verifier]);
        }
        $this->update_sessions($sessions);
    }

    protected function update_sessions($sessions)
    {
        if ($sessions) {
            update_user_meta($this->user_id, 'session_tokens', $sessions);
        } else {
            delete_user_meta($this->user_id, 'session_tokens');
        }
    }

    protected function destroy_other_sessions($verifier)
    {
        $this->update_sessions([$verifier => $this->get_session($verifier)]);
    }

    protected function destroy_all_sessions()
    {
        $this->update_sessions([]);
    }

    public static function drop_sessions()
    {
        delete_metadata('user', 0, 'session_tokens', false, true);
    }
}
