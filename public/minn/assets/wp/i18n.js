/*! Minn Engine wp-i18n | MIT | The wp.i18n API over locale data in the Jed shape, with a small sprintf. */
(function (global) {
	var domains = { default: { '': { plural_forms: 'nplurals=2; plural=(n != 1);' } } };
	var listeners = [];
	function plural(domain, n) {
		var d = domains[domain] || domains.default;
		var header = d[''] || {};
		var form = header.plural_forms || header['Plural-Forms'] || header['plural-forms'] || 'nplurals=2; plural=(n != 1);';
		var match = /plural\s*=\s*([^;]+)/.exec(form);
		if (!match) { return n === 1 ? 0 : 1; }
		try { return Number(new Function('n', 'return Number(' + match[1] + ');')(n)); } catch (e) { return n === 1 ? 0 : 1; }
	}
	function lookup(domain, key) {
		var d = domains[domain] || domains.default;
		var entry = d[key];
		return Array.isArray(entry) ? entry : (typeof entry === 'string' ? [entry] : null);
	}
	function translate(domain, context, single, pluralForm, n) {
		var key = context ? context + '' + single : single;
		var entry = lookup(domain || 'default', key);
		if (typeof n === 'number') {
			var index = plural(domain || 'default', n);
			if (entry && entry[index]) { return entry[index]; }
			return index === 0 ? single : pluralForm;
		}
		return entry && entry[0] ? entry[0] : single;
	}
	function sprintf(format) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(format).replace(/%(?:(\d+)\$)?([-+ 0#]*)(\d+)?(?:\.(\d+))?([sdfux%])/g, function (m, pos, flags, width, precision, type) {
			if (type === '%') { return '%'; }
			var value = pos ? args[Number(pos) - 1] : args[i++];
			if (type === 'd' || type === 'u') { value = parseInt(value, 10); }
			else if (type === 'f') { value = precision ? Number(value).toFixed(Number(precision)) : Number(value); }
			else if (type === 'x') { value = Number(value).toString(16); }
			else { value = value === undefined || value === null ? '' : String(value); }
			var out = String(value);
			if (width && out.length < Number(width)) {
				var pad = Number(width) - out.length;
				out = flags.indexOf('-') !== -1 ? out + new Array(pad + 1).join(' ') : new Array(pad + 1).join(flags.indexOf('0') !== -1 ? '0' : ' ') + out;
			}
			return out;
		});
	}
	function notify() { listeners.forEach(function (fn) { fn(); }); }
	function setLocaleData(localeData, domain) {
		domain = domain || 'default';
		domains[domain] = Object.assign({}, domains[domain] || {}, localeData || {});
		notify();
	}
	var api = {
		__: function (text, domain) { return translate(domain, undefined, text); },
		_x: function (text, context, domain) { return translate(domain, context, text); },
		_n: function (single, pluralForm, n, domain) { return translate(domain, undefined, single, pluralForm, n); },
		_nx: function (single, pluralForm, n, context, domain) { return translate(domain, context, single, pluralForm, n); },
		sprintf: sprintf,
		setLocaleData: setLocaleData,
		addLocaleData: setLocaleData,
		resetLocaleData: function (localeData, domain) { domains[domain || 'default'] = Object.assign({ '': {} }, localeData || {}); notify(); },
		getLocaleData: function (domain) { return domains[domain || 'default']; },
		hasTranslation: function (single, context, domain) { return lookup(domain || 'default', context ? context + '' + single : single) !== null; },
		isRTL: function () { return translate('default', 'text direction', 'ltr') === 'rtl'; },
		subscribe: function (fn) { listeners.push(fn); return function () { listeners = listeners.filter(function (l) { return l !== fn; }); }; },
		createI18n: function () { return api; },
		defaultI18n: null,
	};
	api.defaultI18n = api;
	global.wp = global.wp || {};
	global.wp.i18n = api;
})(window);
