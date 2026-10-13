/*! Minn Engine wp-url | MIT | The wp.url helpers over the browser's URL parser. */
(function (global) {
	function getQueryString(url) { var m = /\?([^#]*)/.exec(String(url)); return m && m[1] ? m[1] : undefined; }
	/** What follows a URL's scheme and its colon; undefined for text with whitespace, or with no scheme before a slash. */
	function afterScheme(url) {
		var text = String(url), colon = text.indexOf(':');
		if (/\s/.test(text) || colon < 1 || text.slice(0, colon).indexOf('/') !== -1) { return undefined; }
		return text.slice(colon + 1);
	}
	/** Where a host part ends: at the first slash, # or ?, or at the end. */
	function endOfHost(rest) { var end = rest.search(/[\/#?]/); return end === -1 ? rest.length : end; }
	/** The host (and port) after the scheme, once up to three slashes are skipped. */
	function getAuthority(url) {
		var rest = afterScheme(url);
		if (rest === undefined) { return undefined; }
		var slashes = /^\/*/.exec(rest)[0].length;
		if (slashes > 3) { return undefined; }
		rest = rest.slice(slashes);
		var host = rest.slice(0, endOfHost(rest));
		return host === '' ? undefined : host;
	}
	/** The path after the host's slash, up to any query or fragment; a host must follow // or the colon directly. */
	function getPath(url) {
		var rest = afterScheme(url);
		if (rest === undefined) { return undefined; }
		if (rest.slice(0, 2) === '//') { rest = rest.slice(2); }
		var end = endOfHost(rest);
		if (end === 0 || rest.charAt(end) !== '/') { return undefined; }
		var path = rest.slice(end + 1).split(/[#?]/)[0];
		return path === '' ? undefined : path;
	}
	function buildQueryString(data) {
		var parts = [];
		(function walk(value, prefix) {
			if (value === null || value === undefined) { return; }
			if (typeof value === 'object') {
				Object.keys(value).forEach(function (k) { walk(value[k], prefix ? prefix + '[' + k + ']' : k); });
				return;
			}
			parts.push(encodeURIComponent(prefix) + '=' + encodeURIComponent(String(value)));
		})(data, '');
		return parts.join('&').replace(/%5B/g, '[').replace(/%5D/g, ']');
	}
	function getQueryArgs(url) {
		var qs = getQueryString(url) || '';
		var out = {};
		qs.split('&').filter(Boolean).forEach(function (pair) {
			var eq = pair.indexOf('=');
			var key = decodeURIComponent(eq === -1 ? pair : pair.slice(0, eq)).replace(/\+/g, ' ');
			var value = eq === -1 ? '' : decodeURIComponent(pair.slice(eq + 1).replace(/\+/g, ' '));
			var path = key.replace(/\]/g, '').split('[');
			var node = out;
			path.forEach(function (segment, index) {
				var last = index === path.length - 1;
				if (last) {
					if (segment === '') { node[Object.keys(node).length] = value; } else { node[segment] = value; }
					return;
				}
				var next = path[index + 1];
				node[segment] = node[segment] || (next === '' || /^\d+$/.test(next) ? [] : {});
				node = node[segment];
			});
		});
		return out;
	}
	function addQueryArgs(url, args) {
		url = url || '';
		if (!args || !Object.keys(args).length) { return url; }
		var base = url;
		var fragment = '';
		var hash = base.indexOf('#');
		if (hash !== -1) { fragment = base.slice(hash); base = base.slice(0, hash); }
		var q = base.indexOf('?');
		var existing = q !== -1 ? getQueryArgs(base) : {};
		if (q !== -1) { base = base.slice(0, q); }
		return base + '?' + buildQueryString(Object.assign({}, existing, args)) + fragment;
	}
	var api = {
		isURL: function (url) { try { new URL(url); return true; } catch (e) { return false; } },
		isEmail: function (v) { return /^(mailto:)?[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(String(v)); },
		getProtocol: function (url) { var m = /^([^\s:]+:)/.exec(String(url)); return m ? m[1] : undefined; },
		isValidProtocol: function (p) { return !!p && /^[a-z\-.\+]+[0-9]*:$/i.test(p); },
		getAuthority: getAuthority,
		isValidAuthority: function (a) { return !!a && /^[^\s#?]+$/.test(a); },
		getPath: getPath,
		isValidPath: function (p) { return !!p && /^[^\s#?]+$/.test(p); },
		getQueryString: getQueryString,
		buildQueryString: buildQueryString,
		isValidQueryString: function (q) { return !!q && /^[^\s#?\/]+$/.test(q); },
		getQueryArgs: getQueryArgs,
		getQueryArg: function (url, arg) { return getQueryArgs(url)[arg]; },
		hasQueryArg: function (url, arg) { return getQueryArgs(url)[arg] !== undefined; },
		removeQueryArgs: function (url) {
			var remove = Array.prototype.slice.call(arguments, 1);
			var q = url.indexOf('?');
			if (q === -1) { return url; }
			var args = getQueryArgs(url);
			remove.forEach(function (k) { delete args[k]; });
			var qs = buildQueryString(args);
			return qs ? url.slice(0, q) + '?' + qs : url.slice(0, q);
		},
		addQueryArgs: addQueryArgs,
		getFragment: function (url) { var m = /^\S+?(#[^\s\?]*)/.exec(String(url)); return m ? m[1] : undefined; },
		isValidFragment: function (f) { return !!f && /^#[^\s#?\/]*$/.test(f); },
		prependHTTP: function (url) { url = String(url).trim(); return url === '' || /^(https?:\/\/|mailto:|tel:|\/|#|\?)/i.test(url) ? url : 'http://' + url; },
		prependHTTPS: function (url) { url = String(url).trim(); return url === '' || /^(https?:\/\/|mailto:|tel:|\/|#|\?)/i.test(url) ? url : 'https://' + url; },
		safeDecodeURI: function (uri) { try { return decodeURI(uri); } catch (e) { return uri; } },
		safeDecodeURIComponent: function (uri) { try { return decodeURIComponent(uri); } catch (e) { return uri; } },
		filterURLForDisplay: function (url, maxLength) {
			var out = String(url).replace(/^[a-z\-.\+]+[0-9]*:(\/\/)?/i, '').replace(/^www\./i, '');
			if (/^[^\/]+\/$/.test(out)) { out = out.replace('/', ''); }
			return !maxLength || out.length <= maxLength ? out : out.slice(0, maxLength);
		},
		cleanForSlug: function (s) {
			return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[\s\.\/]+/g, '-').replace(/[^\p{L}\p{N}_-]+/gu, '').replace(/-+/g, '-').replace(/(^-+)|(-+$)/g, '');
		},
		normalizePath: function (p) {
			var parts = String(p).split('?');
			var path = parts[0].replace(/\/$/, '');
			return parts[1] ? path + '?' + parts[1].split('&').sort().join('&') : path;
		},
		getFilename: function (url) { try { var name = new URL(url, 'http://minn.invalid/').pathname.split('/').pop(); return name || undefined; } catch (e) { return undefined; } },
	};
	global.wp = global.wp || {};
	global.wp.url = api;
})(window);
