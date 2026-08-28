<?php
/** Shared helpers for the engine suites. */

function minn_test_fetch( string $url, int $timeout = 10 ): array {
	$ctx  = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'ignore_errors' => true, 'timeout' => $timeout ),
		)
	);
	$body    = @file_get_contents( $url, false, $ctx );
	$headers = array( 'status' => 0 );
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$headers['status'] = (int) $m[1];
		} elseif ( str_contains( $h, ':' ) ) {
			[ $k, $v ] = explode( ':', $h, 2 );
			$headers[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}
	return array( $headers, minn_test_neutralise( (string) $body ) );
}

/** The wp-container-core-*-is-layout suffix is engine-defined (contracts/blocks.md); neutralise it before diffing. */
function minn_test_neutralise( string $body ): string {
	return (string) preg_replace( '/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', $body );
}

/** Structural diff: first differing path, or null when equal. Object key
 *  order is ignored; array order is enforced. */
function minn_test_diff( $a, $b, string $path = '$' ): ?string {
	if ( is_array( $a ) && is_array( $b ) ) {
		$a_list = array_is_list( $a );
		if ( $a_list !== array_is_list( $b ) ) {
			return "$path (list vs object)";
		}
		if ( $a_list && count( $a ) !== count( $b ) ) {
			return "$path (length " . count( $a ) . ' vs ' . count( $b ) . ')';
		}
		if ( ! $a_list ) {
			$missing = array_diff( array_keys( $a ), array_keys( $b ) );
			$extra   = array_diff( array_keys( $b ), array_keys( $a ) );
			if ( $missing || $extra ) {
				return "$path (keys missing: " . implode( ',', $missing ) . ' extra: ' . implode( ',', $extra ) . ')';
			}
		}
		foreach ( $a as $k => $v ) {
			$d = minn_test_diff( $v, $b[ $k ], "$path.$k" );
			if ( null !== $d ) {
				return $d;
			}
		}
		return null;
	}
	if ( $a !== $b ) {
		return "$path (" . var_export( $a, true ) . ' vs ' . var_export( $b, true ) . ')';
	}
	return null;
}
