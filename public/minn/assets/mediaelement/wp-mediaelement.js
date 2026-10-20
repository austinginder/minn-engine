/**
 * Minn's MediaElement start-up, at the address the reference loads its own
 * from (wp-mediaelement): every audio and video shortcode player on the page
 * becomes a MediaElement player with the site's settings (_wpmejsSettings),
 * once the page is ready and again when more content arrives (post-load).
 * wp.mediaelement.initialize() runs it again for players added later.
 * Written for the engine; the library itself is MediaElement.js (MIT).
 */
( function ( window, $ ) {
	'use strict';

	window.wp = window.wp || {};

	/** The settings a player starts with: the site's (_wpmejsSettings). */
	function settings() {
		return $.extend( true, { classPrefix: 'mejs-' }, window._wpmejsSettings || {} );
	}

	/** Makes a player of every shortcode player on the page not made one yet. */
	function initialize() {
		if ( typeof window.MediaElementPlayer !== 'function' ) {
			return;
		}
		$( '.wp-audio-shortcode, .wp-video-shortcode' )
			.not( '.mejs-container' )
			.filter( function () {
				return ! $( this ).parent().hasClass( 'mejs-mediaelement' ) && ! $( this ).closest( '.wp-playlist' ).length;
			} )
			.each( function () {
				new window.MediaElementPlayer( this, settings() );
			} );
	}

	window.wp.mediaelement = { initialize: initialize };

	$( initialize );
	$( document.body ).on( 'post-load', initialize );
}( window, window.jQuery ) );
