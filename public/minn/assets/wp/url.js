/*! Minn Engine wp-url | MIT | The wp.url helpers over the browser's URL parser. */
(function (global) {
	function getQueryString(url) { var m = /\?([^#]*)/.exec(String(url)); return m && m[1] ? m[1] : undefined; }
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
		getAuthority: function (url) { var m = /^[^\/\s:]+:(?:\/\/)?\/?([^\/\s#?]+)[\/#?]{0,1}\S*$/.exec(String(url)); return m ? m[1] : undefined; },
		isValidAuthority: function (a) { return !!a && /^[^\s#?]+$/.test(a); },
		getPath: function (url) { var m = /^[^\/\s:]+:(?:\/\/)?[^\/\s#?]+[\/]([^\s#?]+)[#?]{0,1}\S*$/.exec(String(url)); return m ? m[1] : undefined; },
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
