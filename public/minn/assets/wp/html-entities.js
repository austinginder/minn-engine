/*! Minn Engine wp-html-entities | MIT | wp.htmlEntities.decodeEntities through the browser's parser. */
(function (global) {
	var el;
	global.wp = global.wp || {};
	global.wp.htmlEntities = {
		decodeEntities: function (html) {
			if (typeof html !== 'string' || html.indexOf('&') === -1) { return html; }
			el = el || document.createElement('textarea');
			el.innerHTML = html;
			return el.textContent;
		},
	};
})(window);
