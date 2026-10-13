/**
 * Minn Engine: the embed card's own script. The card is a post's /embed/
 * page, normally shown in an iframe on another page. Inside a frame it
 * tells the page around it how tall it is (once loaded, after the window
 * resizes, and when that page says it is ready) and hands its link clicks
 * up, since a sandboxed frame cannot move the window itself; every message
 * carries the secret from the card's address (#?secret=...), so the page
 * knows which embed spoke. Framed or not, it runs the sharing dialog: the
 * share button opens it, its two tabs switch by click or arrow keys, Tab
 * stays inside it, and the close button or Escape shuts it again.
 */
(function (window, document) {
	'use strict';

	var KEY_TAB = 9;
	var KEY_ESCAPE = 27;
	var KEY_LEFT = 37;
	var KEY_RIGHT = 39;
	var RESIZE_DELAY = 100;

	// Scripts run here, so the root says so: the first whole "no-js" goes, " js" is added.
	var root = document.documentElement;
	root.className = root.className.replace(/\bno-js\b/, '') + ' js';

	var inFrame = window.self !== window.top;
	var secret = readSecret();
	var started = false;
	var resizeTimer = null;

	/**
	 * The secret is the last "secret=" in the address's fragment followed by
	 * ten letters, digits or underscores; failing that, the fragment itself.
	 */
	function readSecret() {
		var hash = window.location.hash;
		var match = /.*secret=(\w{10})/.exec(hash);
		return match ? match[1] : hash;
	}

	/** A plain object, as postMessage delivers one (not an array or a string). */
	function isPlainObject(value) {
		return Object.prototype.toString.call(value) === '[object Object]';
	}

	function post(message, value) {
		window.parent.postMessage({
			message: message,
			value: value,
			secret: secret
		}, '*');
	}

	/** The card's height, the body's box rounded up, for the page to size the iframe. */
	function sendHeight() {
		post('height', Math.ceil(document.body.getBoundingClientRect().height));
	}

	/** One report once a run of resizes settles. */
	function onResize() {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(sendHeight, RESIZE_DELAY);
	}

	/** The page around the card says it is ready: only it, and only with this card's secret. */
	function onMessage(event) {
		var data = event.data;
		if (event.source !== window.parent || !isPlainObject(data)) {
			return;
		}
		if (data.message === 'ready' && data.secret === secret) {
			sendHeight();
		}
	}

	/**
	 * A click on a link (or on something inside one) goes to the page around
	 * the card instead, which moves the whole window. The link is the clicked
	 * element when it has an href, else its parent; clicks with a modifier key
	 * are left to the browser.
	 */
	function onClick(event) {
		var target = event.target;
		if (!target || !target.hasAttribute) {
			return;
		}
		var holder = target.hasAttribute('href') ? target : target.parentNode;
		var href = holder && holder.getAttribute ? holder.getAttribute('href') : null;
		if (!href || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
			return;
		}
		event.preventDefault();
		post('link', href);
	}

	/** The sharing dialog: opening, closing, its tabs and its inputs. */
	function setUpSharing() {
		var dialog = document.querySelector('.wp-embed-share-dialog');
		var openButton = document.querySelector('.wp-embed-share-dialog-open');
		var closeButton = document.querySelector('.wp-embed-share-dialog-close');
		var tabs = document.querySelectorAll('.wp-embed-share-tab-button [role="tab"]');
		var inputs = document.querySelectorAll('.wp-embed-share-input');
		var i;

		if (!dialog) {
			return;
		}

		function selectedTab() {
			return dialog.querySelector('.wp-embed-share-tab-button [aria-selected="true"]');
		}

		function panelOf(tab) {
			return document.getElementById(tab.getAttribute('aria-controls'));
		}

		function setPanelHidden(tab, hidden) {
			var panel = panelOf(tab);
			if (panel) {
				panel.setAttribute('aria-hidden', hidden ? 'true' : 'false');
			}
		}

		function focusSelectedTab() {
			var tab = selectedTab();
			if (tab) {
				tab.focus();
			}
		}

		/** Closing adds the class back and returns focus to the button that opened it. */
		function closeDialog() {
			dialog.className += ' hidden';
			if (openButton) {
				openButton.focus();
			}
		}

		/** A clicked tab trades places with the selected one; focus order (tabindex) stays as it is. */
		function onTabClick() {
			var selected = selectedTab();
			if (!selected || selected === this) {
				return;
			}
			selected.setAttribute('aria-selected', 'false');
			setPanelHidden(selected, true);
			this.setAttribute('aria-selected', 'true');
			setPanelHidden(this, false);
		}

		/** Left and Right move to the tab beside this one, without wrapping, and take focus order with them. */
		function onTabKeydown(event) {
			var step = event.keyCode === KEY_LEFT ? -1 : (event.keyCode === KEY_RIGHT ? 1 : 0);
			if (!step) {
				return;
			}
			var index = Array.prototype.indexOf.call(tabs, this);
			var next = tabs[index + step];
			if (!next) {
				return;
			}
			this.setAttribute('tabindex', '-1');
			this.setAttribute('aria-selected', 'false');
			setPanelHidden(this, true);
			next.setAttribute('tabindex', '0');
			next.setAttribute('aria-selected', 'true');
			next.focus();
			setPanelHidden(next, false);
		}

		/** Escape closes an open dialog; Tab and Shift+Tab loop between the close button and the selected tab. */
		function onKeydown(event) {
			if (event.keyCode === KEY_ESCAPE) {
				if (dialog.className.indexOf('hidden') === -1) {
					closeDialog();
				}
				return;
			}
			if (event.keyCode !== KEY_TAB) {
				return;
			}
			var selected = selectedTab();
			if (!event.shiftKey && closeButton && event.target === closeButton && selected) {
				event.preventDefault();
				selected.focus();
			} else if (event.shiftKey && selected && event.target === selected && closeButton) {
				event.preventDefault();
				closeButton.focus();
			}
		}

		if (openButton) {
			openButton.addEventListener('click', function () {
				dialog.className = dialog.className.replace('hidden', '');
				focusSelectedTab();
			});
		}
		if (closeButton) {
			closeButton.addEventListener('click', closeDialog);
		}
		for (i = 0; i < tabs.length; i++) {
			tabs[i].addEventListener('click', onTabClick);
			tabs[i].addEventListener('keydown', onTabKeydown);
		}
		// A click puts the whole address or code in the selection, ready to copy.
		for (i = 0; i < inputs.length; i++) {
			inputs[i].addEventListener('click', function () {
				this.select();
			});
		}
		document.addEventListener('keydown', onKeydown);
	}

	/** Runs once, when the document is parsed. */
	function start() {
		if (started) {
			return;
		}
		started = true;
		setUpSharing();
		if (inFrame) {
			sendHeight();
			window.addEventListener('resize', onResize);
			window.addEventListener('message', onMessage);
			document.addEventListener('click', onClick);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(window, document);
