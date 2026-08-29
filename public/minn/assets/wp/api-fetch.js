/*! Minn Engine wp-api-fetch | MIT | wp.apiFetch with the middleware chain: root URL, nonce, preloading, fetch-all. */
(function (global) {
	var middlewares = [];
	var fetchHandler = function (options) {
		var url = options.url || options.path;
		var headers = Object.assign({ Accept: 'application/json, */*;q=0.1' }, options.headers || {});
		var body = options.body;
		if (options.data !== undefined) { body = JSON.stringify(options.data); headers['Content-Type'] = 'application/json'; }
		var parse = options.parse !== false;
		return global.fetch(url, { method: options.method || 'GET', headers: headers, body: body, credentials: options.credentials || 'include', signal: options.signal }).then(function (response) {
			if (!parse) { return response; }
			if (response.status === 204) { return null; }
			return response.json().catch(function () { return null; }).then(function (data) {
				if (!response.ok) { throw (data && data.code ? data : { code: 'unknown_error', message: response.statusText, data: { status: response.status } }); }
				return data;
			});
		});
	};
	function apiFetch(options) {
		var chain = middlewares.slice().reverse();
		var run = function (opts, index) {
			if (index >= chain.length) { return fetchHandler(opts); }
			return chain[index](opts, function (next) { return run(next, index + 1); });
		};
		return Promise.resolve(run(options, 0));
	}
	apiFetch.use = function (mw) { middlewares.push(mw); };
	apiFetch.setFetchHandler = function (fn) { fetchHandler = fn; };
	apiFetch.createNonceMiddleware = function (nonce) {
		var mw = function (options, next) {
			var headers = Object.assign({}, options.headers || {});
			var has = Object.keys(headers).some(function (k) { return k.toLowerCase() === 'x-wp-nonce'; });
			if (!has) { headers['X-WP-Nonce'] = mw.nonce; }
			return next(Object.assign({}, options, { headers: headers }));
		};
		mw.nonce = nonce;
		return mw;
	};
	apiFetch.createRootURLMiddleware = function (root) {
		return function (options, next) {
			if (options.url || typeof options.path !== 'string') { return next(options); }
			var path = options.path.replace(/^\//, '');
			var url = root.indexOf('?') !== -1 ? root + path.replace(/^\?/, '&') : root.replace(/\/$/, '') + '/' + path;
			return next(Object.assign({}, options, { url: url }));
		};
	};
	apiFetch.createPreloadingMiddleware = function (preloaded) {
		var cache = Object.assign({}, preloaded || {});
		return function (options, next) {
			var method = (options.method || 'GET').toUpperCase();
			if (typeof options.path === 'string' && (method === 'GET' || method === 'OPTIONS')) {
				var key = global.wp && global.wp.url ? global.wp.url.normalizePath(options.path) : options.path;
				var hit = cache[key] && (method === 'GET' ? cache[key] : cache[key][method]);
				if (hit) {
					if (method === 'GET') { delete cache[key]; }
					return Promise.resolve(options.parse === false ? new Response(JSON.stringify(hit.body), { status: 200, headers: hit.headers }) : hit.body);
				}
			}
			return next(options);
		};
	};
	apiFetch.fetchAllMiddleware = function (options, next) { return next(options); };
	apiFetch.mediaUploadMiddleware = function (options, next) { return next(options); };
	global.wp = global.wp || {};
	global.wp.apiFetch = apiFetch;
})(window);
