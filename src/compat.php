<?php
/**
 * The bridge from the legacy procedural layer to the Minn\ classes. Every
 * function here delegates; nothing is implemented. It shrinks as each
 * remaining legacy file migrates and disappears with the last one.
 */

use Minn\Auth\Authenticated;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Auth\Cookie;
use Minn\Auth\Nonce;
use Minn\Auth\Password;
use Minn\Auth\Salts;
use Minn\Auth\Sessions;
use Minn\Content\Users;
use Minn\Db;

const MINN_DAY = 86400;

function minn_users(): Users {
	static $users = null;
	return $users ??= new Users( Db::shared() );
}

function minn_sessions(): Sessions {
	static $sessions = null;
	return $sessions ??= new Sessions( minn_users() );
}

function minn_cookie(): Cookie {
	static $cookie = null;
	return $cookie ??= new Cookie( Db::shared(), minn_users(), minn_sessions() );
}

function minn_authenticator(): Authenticator {
	static $authenticator = null;
	return $authenticator ??= new Authenticator( minn_cookie(), minn_users() );
}

function minn_capabilities(): Capabilities {
	static $capabilities = null;
	return $capabilities ??= Capabilities::fromDb( Db::shared() );
}

/* ------------------------------------------------------------- auth.php */

function minn_salt( string $scheme ): string {
	return Salts::for( $scheme );
}

function minn_hash( string $data, string $scheme ): string {
	return Salts::hash( $data, $scheme );
}

function minn_pass_fragment( string $user_pass ): string {
	return Password::fragment( $user_pass );
}

function minn_get_user_by_login( string $login ): ?array {
	return minn_users()->findByLogin( $login );
}

function minn_get_user_by_id( int $id ): ?array {
	return minn_users()->find( $id );
}

function minn_user_meta( int $uid, string $key ): ?string {
	return minn_users()->meta( $uid, $key );
}

function minn_validate_auth_cookie( string $cookie ): ?array {
	$session = minn_cookie()->validate( $cookie );
	return $session ? array( $session->user, $session->token ) : null;
}

function minn_logged_in_cookie_name(): string {
	return minn_cookie()->name();
}

function minn_verify_rest_nonce( string $nonce, int $uid, string $token ): bool {
	return Nonce::verify( $nonce, $uid, $token );
}

/** Cookie-only authentication for page loads: [user, token] or null, $why set on failure. */
function minn_authenticate_session( ?string &$why ): ?array {
	$result = minn_authenticator()->session( $_COOKIE );
	if ( ! $result instanceof Authenticated ) {
		$why = $result->code;
		return null;
	}
	$why = '';
	return array( $result->user, $result->token );
}

/** Cookie plus nonce authentication for REST calls: [user, token] or null, $why set on failure. */
function minn_authenticate_rest( ?string &$why ): ?array {
	$nonce  = $_SERVER['HTTP_X_WP_NONCE'] ?? ( $_GET['_wpnonce'] ?? null );
	$result = minn_authenticator()->rest( $_COOKIE, null === $nonce ? null : (string) $nonce );
	if ( ! $result instanceof Authenticated ) {
		$why = $result->code;
		return null;
	}
	$why = '';
	return array( $result->user, $result->token );
}

function minn_generate_auth_cookie( array $user, int $expiration, string $token ): string {
	return minn_cookie()->mint( $user, $expiration, $token );
}

function minn_create_rest_nonce( int $uid, string $token ): string {
	return Nonce::create( $uid, $token );
}

/* ------------------------------------------------------------ login.php */

function minn_check_password( string $password, string $hash ): bool {
	return Password::verify( $password, $hash );
}

function minn_create_session( int $uid, int $expiration ): string {
	return minn_sessions()->create( $uid, $expiration, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
}

function minn_destroy_session( int $uid, string $token ): bool {
	return minn_sessions()->destroy( $uid, $token );
}

function minn_login( string $username, string $password ): ?array {
	return minn_authenticator()->login( $username, $password );
}

/* ------------------------------------------------------------- caps.php */

function minn_roles(): array {
	return minn_capabilities()->roles()->all();
}

function minn_user_roles( int $uid ): array {
	return minn_capabilities()->rolesOf( $uid );
}

function minn_user_capabilities( int $uid ): array {
	return minn_capabilities()->primitivesOf( $uid );
}

function minn_map_meta_cap( string $cap, int $uid, ?int $post_id = null ): array {
	return minn_capabilities()->map( $cap, $uid, $post_id );
}

function minn_user_can( int $uid, string $cap, ?int $post_id = null ): bool {
	return minn_capabilities()->can( $uid, $cap, $post_id );
}

/* -------------------------------------------------------------- rest.php */

function minn_api(): Minn\Rest\Api {
	static $api = null;
	return $api ??= Minn\Rest\Api::forRequest( Db::shared(), Minn\Http\Request::fromGlobals() );
}

function minn_posts(): Minn\Content\Posts {
	static $posts = null;
	return $posts ??= new Minn\Content\Posts( Db::shared() );
}

function minn_home_url( string $path = '' ): string {
	return minn_permalinks()->url( $path );
}

function minn_rest_url( string $route, array $args = array() ): string {
	return ( new Minn\Rest\RestUrl( minn_permalinks() ) )->to( $route, $args );
}

function minn_rest_headers(): void {
	foreach ( Minn\Rest\Reply::HEADERS as $name => $value ) {
		header( "$name: $value" );
	}
}

/** @param bool $per_item true for list payloads (filtering applies per row). */
function minn_rest_send( $data, int $status = 200, bool $per_item = false ): void {
	$fields = Minn\Rest\Fields::fromQuery( $_GET );
	if ( null !== $fields && is_array( $data ) && $status < 400 && $per_item ) {
		$data   = array_map( static fn( $row ) => $fields->apply( $row ), $data );
		$fields = null;
	}
	Minn\Rest\Reply::item( $data, $fields, $status )->send();
}

function minn_rest_error( string $code, string $message, int $status ): void {
	Minn\Rest\Reply::error( new Minn\RestError( $code, $message, $status ) )->send();
}

function minn_serialized_int_list( ?string $blob ): array {
	return Minn\Support\Serialized::intList( $blob );
}

function minn_texturize( string $html ): string {
	return Minn\Content\Texturize::html( $html );
}

function minn_render_blocks( string $raw ): string {
	return Minn\Content\Blocks::render( $raw );
}

function minn_rendered_excerpt( array $post ): string {
	return Minn\Content\Excerpt::render( $post );
}

function minn_post_meta_value( int $post_id, string $key ): ?string {
	return minn_posts()->meta( $post_id, $key );
}

function minn_rest_date( string $mysql ): string {
	return Minn\Rest\PostObject::date( $mysql );
}

function minn_post_type_config( string $type ): array {
	return array( 'rest_base' => Minn\Rest\PostObject::restBase( $type ) );
}

function minn_rest_post_object( array $p ): array {
	return minn_api()->postObject()->view( $p );
}

function minn_rest_post_object_edit( array $p, int $uid ): array {
	return minn_api()->postObject()->edit( $p, $uid );
}

function minn_taxonomy_config( string $rest_base ): array {
	return Minn\Rest\TermObject::config( $rest_base );
}

function minn_rest_term_object( array $t, string $rest_base ): array {
	return minn_api()->termObject()->view( $t, $rest_base );
}

function minn_types_registry(): array {
	return minn_api()->types()->all();
}

function minn_avatar_urls( string $email ): array {
	return Minn\Rest\UserObject::avatarUrls( $email );
}

function minn_rest_user_object( array $u, bool $is_self = false ): array {
	return minn_api()->userObject()->view( $u, $is_self );
}

function minn_rest_user_object_edit( array $u ): array {
	return minn_api()->userObject()->edit( $u );
}

/** Authenticated user id for this request (cookie plus nonce), or 0. */
function minn_current_user_id(): int {
	return minn_api()->caller()->id();
}
