/*! Minn Engine wp-a11y | MIT | wp.a11y.speak into the live regions, creating them when the page has none. */
(function (global) {
	function region(ariaLive) {
		var id = 'a11y-speak-' + ariaLive;
		var el = document.getElementById(id);
		if (!el) {
			var wrap = document.getElementById('a11y-speak-wrap');
			if (!wrap) {
				wrap = document.createElement('div');
				wrap.id = 'a11y-speak-wrap';
				wrap.setAttribute('style', 'position:absolute;margin:-1px;padding:0;height:1px;width:1px;overflow:hidden;clip-path:inset(50%);border:0;word-wrap:normal !important;');
				document.body.appendChild(wrap);
			}
			el = document.createElement('div');
			el.id = id;
			el.className = 'a11y-speak-region';
			el.setAttribute('aria-live', ariaLive);
			el.setAttribute('aria-relevant', 'additions text');
			el.setAttribute('aria-atomic', 'true');
			wrap.appendChild(el);
		}
		return el;
	}
	global.wp = global.wp || {};
	global.wp.a11y = {
		speak: function (message, ariaLive) {
			ariaLive = ariaLive === 'assertive' ? 'assertive' : 'polite';
			var text = String(message).replace(/<[^<>]+>/g, ' ');
			var regions = document.querySelectorAll('.a11y-speak-region');
			for (var i = 0; i < regions.length; i++) { regions[i].textContent = ''; }
			region(ariaLive).textContent = text;
		},
		setup: function () { region('polite'); region('assertive'); },
	};
})(window);
