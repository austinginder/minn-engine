/*! Minn Engine wp-i18n | MIT | The wp.i18n API over locale data in the Jed shape, with a small sprintf. */
(function (global) {
	var domains = { default: { '': { plural_forms: 'nplurals=2; plural=(n != 1);' } } };
	var listeners = [];
	// Plural-Forms is C's expression subset, parsed here and never handed to the JavaScript engine to run.
	var LEVELS = [['||'], ['&&'], ['==', '!='], ['<', '>', '<=', '>='], ['+', '-'], ['*', '/', '%']];
	var compiled = {};
	function parsePlural(expression) {
		var tokens = String(expression).match(/\d+|n|==|!=|<=|>=|&&|\|\||[<>!?:()+\-*\/%]|\S/g) || [];
		var pos = 0;
		function ternary() {
			var condition = binary(0);
			if (condition === null || tokens[pos] !== '?') { return condition; }
			pos++;
			var then = ternary();
			if (then === null || tokens[pos] !== ':') { return null; }
			pos++;
			var other = ternary();
			return other === null ? null : ['?', condition, then, other];
		}
		function binary(level) {
			if (level === LEVELS.length) { return unary(); }
			var left = binary(level + 1);
			while (left !== null && LEVELS[level].indexOf(tokens[pos]) !== -1) {
				var operator = tokens[pos++];
				var right = binary(level + 1);
				left = right === null ? null : [operator, left, right];
			}
			return left;
		}
		function unary() {
			var token = tokens[pos];
			if (token === '!' || token === '-') {
				pos++;
				var operand = unary();
				return operand === null ? null : [token === '!' ? '!' : 'neg', operand];
			}
			if (token === '(') {
				pos++;
				var inner = ternary();
				if (inner === null || tokens[pos] !== ')') { return null; }
				pos++;
				return inner;
			}
			if (token === 'n') { pos++; return ['n']; }
			if (token !== undefined && /^\d+$/.test(token)) { pos++; return ['num', Number(token)]; }
			return null;
		}
		var tree = tokens.length ? ternary() : null;
		return tree !== null && pos === tokens.length ? tree : null;
	}
	function evaluate(node, n) {
		switch (node[0]) {
			case 'num': return node[1];
			case 'n': return n;
			case '!': return evaluate(node[1], n) === 0 ? 1 : 0;
			case 'neg': return -evaluate(node[1], n);
			case '?': return evaluate(node[1], n) !== 0 ? evaluate(node[2], n) : evaluate(node[3], n);
			case '&&': return evaluate(node[1], n) !== 0 && evaluate(node[2], n) !== 0 ? 1 : 0;
			case '||': return evaluate(node[1], n) !== 0 || evaluate(node[2], n) !== 0 ? 1 : 0;
		}
		var a = evaluate(node[1], n), b = evaluate(node[2], n);
		switch (node[0]) {
			case '==': return a === b ? 1 : 0;
			case '!=': return a !== b ? 1 : 0;
			case '<': return a < b ? 1 : 0;
			case '>': return a > b ? 1 : 0;
			case '<=': return a <= b ? 1 : 0;
			case '>=': return a >= b ? 1 : 0;
			case '+': return a + b;
			case '-': return a - b;
			case '*': return a * b;
			case '/': return b === 0 ? 0 : Math.trunc(a / b);
			default: return b === 0 ? 0 : a % b;
		}
	}
	function plural(domain, n) {
		var d = domains[domain] || domains.default;
		var header = d[''] || {};
		var form = header.plural_forms || header['Plural-Forms'] || header['plural-forms'] || 'nplurals=2; plural=(n != 1);';
		var match = /plural\s*=\s*([^;]*)/.exec(form);
		var expression = match ? match[1] : 'n != 1';
		if (!Object.prototype.hasOwnProperty.call(compiled, expression)) { compiled[expression] = parsePlural(expression); }
		return compiled[expression] === null ? 0 : evaluate(compiled[expression], n);
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
