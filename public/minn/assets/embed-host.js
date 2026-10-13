/**
 * Minn Engine: the host side of a post embed. A page that embeds a post
 * holds a blockquote (the fallback link) and an iframe of the post's embed
 * card, both marked with the same secret. The card posts messages to this
 * page: once one arrives with a secret the page knows, the blockquote is
 * hidden and the iframe shown; "height" sizes the iframe (held between 200
 * and 1000 pixels) and "link" takes the whole window to an address on the
 * card's own site, when the reader clicked inside the card. Each embed
 * prints this script, so it installs itself once; wp.receiveEmbedMessage
 * is the marker, and the handler plugins can call.
 */
(function (window, document) {
	'use strict';

	if (!document.querySelector || !window.addEventListener || typeof window.URL === 'undefined') {
		return;
	}

	window.wp = window.wp || {};
	if (window.wp.receiveEmbedMessage) {
		return;
	}

	var MIN_HEIGHT = 200;
	var MAX_HEIGHT = 1000;
	var SECRET_LENGTH = 10;
	var SECRET_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

	/** A plain object, as postMessage delivers one (not an array, a string or a date). */
	function isPlainObject(value) {
		return Object.prototype.toString.call(value) === '[object Object]';
	}

	/** The height asked for as a whole number, held between the bounds; anything unreadable is the least. */
	function clampHeight(value) {
		var height = parseInt(value, 10);
		if (height > MAX_HEIGHT) {
			return MAX_HEIGHT;
		}
		return height >= MIN_HEIGHT ? height : MIN_HEIGHT;
	}

	/** Sends the top window to an address, only an http(s) one on the card's host, and only after a click in the card. */
	function followLink(iframe, value) {
		var target;
		var source;
		try {
			target = new URL(value);
			source = new URL(iframe.getAttribute('src'));
		} catch (error) {
			return;
		}
		if (!/^https?:$/.test(target.protocol) || target.host !== source.host) {
			return;
		}
		if (document.activeElement === iframe) {
			window.top.location.href = value;
		}
	}

	/** A message from an embed card: only one whose secret is letters and digits counts. */
	window.wp.receiveEmbedMessage = function (event) {
		var data = event.data;
		if (!isPlainObject(data) || /[^a-zA-Z0-9]/.test(data.secret)) {
			return;
		}
		var quotes = document.querySelectorAll('blockquote[data-secret="' + data.secret + '"]');
		var frames = document.querySelectorAll('iframe[data-secret="' + data.secret + '"]');
		var i;
		for (i = 0; i < quotes.length; i++) {
			quotes[i].style.display = 'none';
		}
		for (i = 0; i < frames.length; i++) {
			var iframe = frames[i];
			if (iframe.contentWindow !== event.source) {
				continue;
			}
			iframe.removeAttribute('style');
			if (data.message === 'height') {
				iframe.height = clampHeight(data.value);
			} else if (data.message === 'link') {
				followLink(iframe, data.value);
			}
		}
	};

	/** Ten random letters and digits. */
	function makeSecret() {
		var secret = '';
		var values = null;
		if (window.crypto && window.crypto.getRandomValues) {
			values = window.crypto.getRandomValues(new Uint32Array(SECRET_LENGTH));
		}
		for (var i = 0; i < SECRET_LENGTH; i++) {
			var pick = values ? values[i] : Math.floor(Math.random() * 4294967296);
			secret += SECRET_CHARACTERS.charAt(pick % SECRET_CHARACTERS.length);
		}
		return secret;
	}

	/**
	 * Every embed iframe gets a secret if it has none (in its src too, where
	 * the card reads it), then each is told the page is ready, so a card that
	 * loaded first sends its height again.
	 */
	function onReady() {
		var frames = document.querySelectorAll('iframe.wp-embedded-content');
		var i;
		for (i = 0; i < frames.length; i++) {
			if (!frames[i].getAttribute('data-secret')) {
				var secret = makeSecret();
				frames[i].src += '#?secret=' + secret;
				frames[i].setAttribute('data-secret', secret);
			}
		}
		for (i = 0; i < frames.length; i++) {
			if (frames[i].contentWindow) {
				frames[i].contentWindow.postMessage({
					message: 'ready',
					secret: frames[i].getAttribute('data-secret')
				}, '*');
			}
		}
	}

	window.addEventListener('message', window.wp.receiveEmbedMessage, false);
	document.addEventListener('DOMContentLoaded', onReady, false);
})(window, document);
