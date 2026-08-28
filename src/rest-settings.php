<?php
/**
 * The wp/v2/settings surface: the registered-settings payload the Settings
 * views read and write, mapped to the options WordPress stores.
 *
 * Implemented from oracle captures recorded in contracts/rest/settings.md;
 * no WordPress source is used.
 */

/**
 * setting key => [ option name, type ]. Types: int, bool, string, string_or_default,
 * int_or_null. Registration order matches the oracle's payload order.
 */
function minn_settings_registry(): array {
	return array(
		'blog_public'            => array( 'blog_public', 'int' ),
		'minn_admin_maintenance' => array( 'minn_admin_maintenance', 'bool' ),
		'users_can_register'     => array( 'users_can_register', 'int' ),
		'default_role'           => array( 'default_role', 'string' ),
		'comment_moderation'     => array( 'comment_moderation', 'int' ),
		'comment_registration'   => array( 'comment_registration', 'int' ),
		'show_avatars'           => array( 'show_avatars', 'int' ),
		'title'                  => array( 'blogname', 'string' ),
		'description'            => array( 'blogdescription', 'string' ),
		'url'                    => array( 'siteurl', 'string' ),
		'email'                  => array( 'admin_email', 'string' ),
		'timezone'               => array( 'timezone_string', 'string' ),
		'date_format'            => array( 'date_format', 'string' ),
		'time_format'            => array( 'time_format', 'string' ),
		'start_of_week'          => array( 'start_of_week', 'int' ),
		'language'               => array( 'WPLANG', 'language' ),
		'use_smilies'            => array( 'use_smilies', 'bool' ),
		'default_category'       => array( 'default_category', 'int' ),
		'default_post_format'    => array( 'default_post_format', 'string' ),
		'posts_per_page'         => array( 'posts_per_page', 'int' ),
		'show_on_front'          => array( 'show_on_front', 'string' ),
		'page_on_front'          => array( 'page_on_front', 'int' ),
		'page_for_posts'         => array( 'page_for_posts', 'int' ),
		'default_ping_status'    => array( 'default_ping_status', 'string' ),
		'default_comment_status' => array( 'default_comment_status', 'string' ),
		'site_logo'              => array( 'site_logo', 'int_or_null' ),
		'site_icon'              => array( 'site_icon', 'int' ),
	);
}

function minn_settings_payload(): array {
	$out = array();
	foreach ( minn_settings_registry() as $key => [ $option, $type ] ) {
		$raw = minn_option( $option );
		switch ( $type ) {
			case 'int':
				$out[ $key ] = (int) ( $raw ?? 0 );
				break;
			case 'bool':
				// Missing option = registered default (false). A STORED empty
				// string reads back as null — core stores boolean false as ''
				// and then fails to re-type it, so the API serves null. Kept.
				$out[ $key ] = null === $raw ? false : ( '' === $raw ? null : '0' !== $raw );
				break;
			case 'language':
				$out[ $key ] = ( null === $raw || '' === $raw ) ? 'en_US' : $raw;
				break;
			case 'int_or_null':
				$out[ $key ] = null === $raw || '' === $raw ? null : (int) $raw;
				break;
			default:
				$out[ $key ] = (string) ( $raw ?? '' );
		}
	}
	return $out;
}

function minn_option_set( string $name, string $value ): void {
	global $table_prefix;
	if ( null === minn_option( $name ) ) {
		$stmt = minn_db()->prepare(
			"INSERT INTO {$table_prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'auto')"
		);
		$stmt->bind_param( 'ss', $name, $value );
	} else {
		$stmt = minn_db()->prepare(
			"UPDATE {$table_prefix}options SET option_value = ? WHERE option_name = ?"
		);
		$stmt->bind_param( 'ss', $value, $name );
	}
	$stmt->execute();
}

function minn_rest_settings( string $method ): void {
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 401 );
	}
	$uid = (int) $auth[0]['ID'];
	if ( ! minn_user_can( $uid, 'manage_options' ) ) {
		minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
	}

	if ( 'POST' === $method || 'PUT' === $method || 'PATCH' === $method ) {
		$body     = minn_request_body();
		$registry = minn_settings_registry();
		foreach ( $body as $key => $value ) {
			if ( ! isset( $registry[ $key ] ) ) {
				continue; // unregistered keys are ignored, as core does
			}
			[ $option, $type ] = $registry[ $key ];
			switch ( $type ) {
				case 'int':
					$stored = (string) (int) $value;
					break;
				case 'bool':
					$stored = $value ? '1' : '';
					break;
				case 'language':
					$stored = 'en_US' === $value ? '' : (string) $value;
					break;
				case 'int_or_null':
					$stored = null === $value ? '' : (string) (int) $value;
					break;
				default:
					$stored = (string) $value;
			}
			minn_option_set( $option, $stored );
		}
	}

	minn_rest_send( minn_settings_payload() );
}
