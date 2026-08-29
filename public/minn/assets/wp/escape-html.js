/*! Minn Engine wp-escape-html | MIT | The wp.escapeHtml helpers. */
(function (global) {
	function escapeAmpersand(v) { return String(v).replace(/&(?!([a-z0-9]+|#[0-9]+|#x[a-f0-9]+);)/gi, '&amp;'); }
	function escapeQuotationMark(v) { return String(v).replace(/"/g, '&quot;'); }
	function escapeLessThan(v) { return String(v).replace(/</g, '&lt;'); }
	var api = {
		escapeAmpersand: escapeAmpersand,
		escapeQuotationMark: escapeQuotationMark,
		escapeLessThan: escapeLessThan,
		escapeAttribute: function (v) { return escapeLessThan(escapeQuotationMark(escapeAmpersand(v))); },
		escapeHTML: function (v) { return escapeLessThan(escapeAmpersand(v)); },
		escapeEditableHTML: function (v) { return escapeLessThan(String(v).replace(/&/g, '&amp;')); },
		isValidAttributeName: function (name) { return !/[- "'>\/="﷐-﷯￾￿]/.test(name); },
	};
	global.wp = global.wp || {};
	global.wp.escapeHtml = api;
})(window);
