/*! Minn Engine wp-dom-ready | MIT | wp.domReady(callback): now when the document is ready, else on DOMContentLoaded. */
(function (global) {
	global.wp = global.wp || {};
	global.wp.domReady = function (callback) {
		if (typeof document === 'undefined') { return; }
		if (document.readyState === 'complete' || document.readyState === 'interactive') { callback(); return; }
		document.addEventListener('DOMContentLoaded', callback);
	};
})(window);
