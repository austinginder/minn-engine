/*! Minn Engine wp-hooks | MIT | The wp.hooks API: named actions and filters with priorities and namespaces. */
(function (global) {
	function createHooks() {
		var actions = {}, filters = {};
		var running = { actions: [], filters: [] };
		function store(kind) { return kind === 'actions' ? actions : filters; }
		function add(kind, name, ns, cb, priority) {
			if (typeof cb !== 'function' || typeof name !== 'string' || typeof ns !== 'string' || ns.indexOf('/') === -1) { return; }
			priority = typeof priority === 'number' ? priority : 10;
			var list = store(kind)[name] = store(kind)[name] || { handlers: [], runs: 0 };
			var i = list.handlers.length;
			while (i > 0 && list.handlers[i - 1].priority > priority) { i--; }
			list.handlers.splice(i, 0, { callback: cb, priority: priority, namespace: ns });
			if (name !== 'hookAdded') { doRun(actions, 'hookAdded', [name, ns, cb, priority], true); }
		}
		function remove(kind, name, ns, all) {
			var list = store(kind)[name];
			if (!list) { return 0; }
			var before = list.handlers.length;
			list.handlers = all ? [] : list.handlers.filter(function (h) { return h.namespace !== ns; });
			if (name !== 'hookRemoved') { doRun(actions, 'hookRemoved', [name, ns], true); }
			return before - list.handlers.length;
		}
		function doRun(map, name, args, isAction) {
			var list = map[name];
			if (!list) { return isAction ? undefined : args[0]; }
			list.runs++;
			var value = args[0];
			var handlers = list.handlers.slice();
			var stack = running[map === actions ? 'actions' : 'filters'];
			stack.push(name);
			try {
				for (var i = 0; i < handlers.length; i++) {
					var result = handlers[i].callback.apply(null, isAction ? args : [value].concat(args.slice(1)));
					if (!isAction) { value = result; }
				}
			} finally {
				stack.pop();
			}
			return isAction ? undefined : value;
		}
		function has(kind, name, ns) {
			var list = store(kind)[name];
			if (!list) { return false; }
			if (ns) { return list.handlers.some(function (h) { return h.namespace === ns; }); }
			return list.handlers.length > 0;
		}
		function did(kind, name) { var list = store(kind)[name]; return list ? list.runs : 0; }
		var api = {
			addAction: function (n, ns, cb, p) { add('actions', n, ns, cb, p); },
			addFilter: function (n, ns, cb, p) { add('filters', n, ns, cb, p); },
			removeAction: function (n, ns) { return remove('actions', n, ns, false); },
			removeFilter: function (n, ns) { return remove('filters', n, ns, false); },
			removeAllActions: function (n) { return remove('actions', n, '', true); },
			removeAllFilters: function (n) { return remove('filters', n, '', true); },
			hasAction: function (n, ns) { return has('actions', n, ns); },
			hasFilter: function (n, ns) { return has('filters', n, ns); },
			doAction: function (n) { doRun(actions, n, Array.prototype.slice.call(arguments, 1), true); },
			doActionAsync: function (n) { var a = Array.prototype.slice.call(arguments, 1); return Promise.resolve().then(function () { doRun(actions, n, a, true); }); },
			applyFilters: function (n) { return doRun(filters, n, Array.prototype.slice.call(arguments, 1), false); },
			applyFiltersAsync: function (n) { var a = Array.prototype.slice.call(arguments, 1); return Promise.resolve().then(function () { return doRun(filters, n, a, false); }); },
			currentAction: function () { return running.actions[running.actions.length - 1] || null; },
			currentFilter: function () { return running.filters[running.filters.length - 1] || null; },
			doingAction: function (n) { return n ? running.actions.indexOf(n) !== -1 : running.actions.length > 0; },
			doingFilter: function (n) { return n ? running.filters.indexOf(n) !== -1 : running.filters.length > 0; },
			didAction: function (n) { return did('actions', n); },
			didFilter: function (n) { return did('filters', n); },
			actions: actions,
			filters: filters,
		};
		return api;
	}
	var defaultHooks = createHooks();
	defaultHooks.createHooks = createHooks;
	defaultHooks.defaultHooks = defaultHooks;
	global.wp = global.wp || {};
	global.wp.hooks = defaultHooks;
})(window);
