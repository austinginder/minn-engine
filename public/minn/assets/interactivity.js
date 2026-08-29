/*! Minn Engine interactivity runtime | MIT | A from-scratch client for the data-wp-* directive grammar. */

// ---- Reactivity: proxies that record which effect read which key, and re-run it on writes.

const RAW = Symbol('raw');
const proxies = new WeakMap();
const deps = new WeakMap();
let activeEffect = null;
const effectStack = [];

function track(target, key) {
	if (!activeEffect) return;
	let keys = deps.get(target);
	if (!keys) deps.set(target, (keys = new Map()));
	let set = keys.get(key);
	if (!set) keys.set(key, (set = new Set()));
	set.add(activeEffect);
	activeEffect.deps.add(set);
}

function trigger(target, key) {
	const keys = deps.get(target);
	if (!keys) return;
	const run = new Set();
	const add = (set) => set && set.forEach((e) => e !== activeEffect && run.add(e));
	add(keys.get(key));
	if (Array.isArray(target)) add(keys.get('length'));
	run.forEach((e) => e.schedule());
}

function isPlain(value) {
	if (value === null || typeof value !== 'object') return false;
	const proto = Object.getPrototypeOf(value);
	return proto === Object.prototype || proto === Array.prototype || proto === null;
}

function reactive(target) {
	if (!isPlain(target)) return target;
	if (target[RAW]) return target;
	let proxy = proxies.get(target);
	if (proxy) return proxy;
	proxy = new Proxy(target, {
		get(t, key, receiver) {
			if (key === RAW) return t;
			track(t, key);
			const value = Reflect.get(t, key, receiver);
			return isPlain(value) ? reactive(value) : value;
		},
		set(t, key, value, receiver) {
			const raw = value && value[RAW] ? value[RAW] : value;
			const had = Object.prototype.hasOwnProperty.call(t, key);
			const old = t[key];
			const ok = Reflect.set(t, key, raw, receiver);
			if (!had || old !== raw) trigger(t, key);
			return ok;
		},
		deleteProperty(t, key) {
			const had = Object.prototype.hasOwnProperty.call(t, key);
			const ok = Reflect.deleteProperty(t, key);
			if (had) trigger(t, key);
			return ok;
		},
		has(t, key) {
			track(t, key);
			return Reflect.has(t, key);
		},
		ownKeys(t) {
			track(t, 'length');
			return Reflect.ownKeys(t);
		},
	});
	proxies.set(target, proxy);
	return proxy;
}

const queue = new Set();
let flushing = false;
function flush() {
	flushing = false;
	const pending = [...queue];
	queue.clear();
	pending.forEach((e) => e.run());
}

function effect(fn) {
	const e = {
		deps: new Set(),
		active: true,
		cleanup: null,
		run() {
			if (!e.active) return;
			e.deps.forEach((set) => set.delete(e));
			e.deps.clear();
			if (typeof e.cleanup === 'function') {
				const c = e.cleanup;
				e.cleanup = null;
				c();
			}
			effectStack.push(activeEffect);
			activeEffect = e;
			try {
				const result = fn();
				if (typeof result === 'function') e.cleanup = result;
			} finally {
				activeEffect = effectStack.pop();
			}
		},
		schedule() {
			queue.add(e);
			if (!flushing) {
				flushing = true;
				queueMicrotask(flush);
			}
		},
		stop() {
			e.active = false;
			e.deps.forEach((set) => set.delete(e));
			e.deps.clear();
			if (typeof e.cleanup === 'function') e.cleanup();
		},
	};
	e.run();
	return () => e.stop();
}

// ---- Scope: which element, namespace, and context a piece of store code runs for.

let scope = null;

function runWith(next, fn, thisArg, args) {
	const prev = scope;
	scope = next;
	try {
		return fn.apply(thisArg, args);
	} finally {
		scope = prev;
	}
}

function isGenerator(value) {
	return value && typeof value.next === 'function' && typeof value.throw === 'function' && typeof value[Symbol.iterator] === 'function';
}

// A generator action keeps its scope across every await.
function drive(gen, s) {
	return new Promise((resolve, reject) => {
		const step = (method, arg) => {
			let result;
			try {
				result = runWith(s, () => gen[method](arg), null, []);
			} catch (error) {
				reject(error);
				return;
			}
			if (result.done) {
				resolve(result.value);
				return;
			}
			Promise.resolve(result.value).then((v) => step('next', v), (err) => step('throw', err));
		};
		step('next', undefined);
	});
}

function wrapFunction(fn) {
	if (fn.__minnWrapped) return fn;
	const wrapped = function (...args) {
		const s = scope;
		const result = runWith(s, fn, this, args);
		return isGenerator(result) ? drive(result, s) : result;
	};
	wrapped.__minnWrapped = true;
	return wrapped;
}

// ---- Stores.

const stores = new Map();
const serverState = {};
const serverConfig = {};

(function readServerData() {
	const el = document.getElementById('wp-script-module-data-@wordpress/interactivity');
	if (!el) return;
	try {
		const data = JSON.parse(el.textContent);
		Object.assign(serverState, data.state || {});
		Object.assign(serverConfig, data.config || {});
	} catch (e) {
		/* no server data */
	}
})();

function deepClone(value) {
	return value === null || typeof value !== 'object' ? value : JSON.parse(JSON.stringify(value));
}

function merge(target, source) {
	for (const key of Reflect.ownKeys(source)) {
		const descriptor = Object.getOwnPropertyDescriptor(source, key);
		if (descriptor.get || descriptor.set) {
			Object.defineProperty(target, key, { ...descriptor, configurable: true, enumerable: true });
			trigger(target, key);
			continue;
		}
		const value = descriptor.value;
		if (typeof value === 'function') {
			target[key] = wrapFunction(value);
			trigger(target, key);
		} else if (isPlain(value) && !Array.isArray(value)) {
			if (!isPlain(target[key]) || Array.isArray(target[key])) {
				target[key] = {};
				trigger(target, key);
			}
			merge(target[key], value);
		} else {
			target[key] = value;
			trigger(target, key);
		}
	}
}

function storeFor(namespace) {
	let entry = stores.get(namespace);
	if (!entry) {
		const raw = { state: {}, actions: {}, callbacks: {} };
		if (serverState[namespace]) merge(raw.state, deepClone(serverState[namespace]));
		entry = { raw, proxy: reactive(raw) };
		stores.set(namespace, entry);
	}
	return entry;
}

export function store(namespace, definition = {}, options = {}) {
	const entry = storeFor(namespace);
	merge(entry.raw, definition);
	return entry.proxy;
}

export function getContext(namespace) {
	if (!scope) throw new Error('getContext() can only be called inside store actions, callbacks, and derived state.');
	return contextFor(scope.element, namespace || scope.namespace);
}

export function getElement() {
	if (!scope) throw new Error('getElement() can only be called inside store actions, callbacks, and derived state.');
	const el = scope.element;
	const attributes = {};
	for (const attr of el.attributes) attributes[attr.name] = attr.value;
	return { ref: el, attributes };
}

export function getConfig(namespace) {
	return serverConfig[namespace || (scope && scope.namespace)] || {};
}

export function getServerState(namespace) {
	return serverState[namespace || (scope && scope.namespace)] || {};
}

export function getServerContext(namespace) {
	if (!scope) return {};
	const raw = serverContexts.get(scope.element);
	return (raw && raw[namespace || scope.namespace]) || {};
}

export function withScope(fn) {
	const s = scope;
	return function (...args) {
		const result = runWith(s, fn, this, args);
		return isGenerator(result) ? drive(result, s) : result;
	};
}

export function withSyncEvent(fn) {
	fn.sync = true;
	return fn;
}

export function splitTask() {
	return new Promise((resolve) => setTimeout(resolve, 0));
}

const unsupported = (name) => () => {
	throw new Error(name + '() is not available in the Minn Engine interactivity runtime; it renders directives without a virtual DOM.');
};
export const useState = unsupported('useState');
export const useEffect = unsupported('useEffect');
export const useLayoutEffect = unsupported('useLayoutEffect');
export const useMemo = unsupported('useMemo');
export const useCallback = unsupported('useCallback');
export const useRef = unsupported('useRef');
export const useContext = unsupported('useContext');
export const useWatch = unsupported('useWatch');
export const useInit = unsupported('useInit');
export const privateApis = () => ({});

// ---- Contexts: one reactive layer per element that declares data-wp-context, inheriting from the nearest ancestor layer.

const contexts = new WeakMap();
const serverContexts = new WeakMap();

function contextLayer(own, parent) {
	const raw = own;
	const proxyOwn = reactive(raw);
	if (!parent) return proxyOwn;
	return new Proxy(raw, {
		get(t, key) {
			if (key === RAW) return t;
			if (Object.prototype.hasOwnProperty.call(t, key) || !(key in parent)) return proxyOwn[key];
			return parent[key];
		},
		set(t, key, value) {
			if (!Object.prototype.hasOwnProperty.call(t, key) && key in parent) {
				parent[key] = value;
				return true;
			}
			proxyOwn[key] = value;
			return true;
		},
		has(t, key) {
			return key in proxyOwn || key in parent;
		},
		deleteProperty(t, key) {
			delete proxyOwn[key];
			return true;
		},
		ownKeys(t) {
			return [...new Set([...Reflect.ownKeys(t), ...Reflect.ownKeys(parent[RAW] || {})])];
		},
		getOwnPropertyDescriptor(t, key) {
			return Reflect.getOwnPropertyDescriptor(t, key) || Reflect.getOwnPropertyDescriptor(parent[RAW] || {}, key);
		},
	});
}

function contextFor(element, namespace) {
	let el = element;
	while (el) {
		const map = contexts.get(el);
		if (map && map.has(namespace)) return map.get(namespace);
		el = el.parentElement;
	}
	const global = globalContexts.get(namespace);
	if (global) return global;
	const layer = contextLayer({}, null);
	globalContexts.set(namespace, layer);
	return layer;
}
const globalContexts = new Map();

function parseNamespaced(value) {
	const match = /^([\w\-\/@.]+)::(.*)$/s.exec(value.trim());
	return match ? { namespace: match[1], value: match[2] } : { namespace: null, value: value.trim() };
}

function declareContext(element, namespace, json) {
	const parsed = parseNamespaced(json);
	const ns = parsed.namespace || namespace;
	let data;
	try {
		data = JSON.parse(parsed.value);
	} catch (e) {
		return;
	}
	if (!data || typeof data !== 'object' || !ns) return;
	const parentEl = element.parentElement;
	const parent = parentEl ? contextFor(parentEl, ns) : null;
	const layer = contextLayer(data, parent);
	let map = contexts.get(element);
	if (!map) contexts.set(element, (map = new Map()));
	map.set(ns, layer);
	let serverMap = serverContexts.get(element);
	if (!serverMap) serverContexts.set(element, (serverMap = {}));
	serverMap[ns] = deepClone(data);
}

// ---- Evaluation of directive values.

function evaluate(reference, s) {
	const parsed = parseNamespaced(reference);
	const namespace = parsed.namespace || s.namespace;
	let path = parsed.value;
	let negate = false;
	if (path.startsWith('!')) {
		negate = true;
		path = path.slice(1);
	}
	if (!namespace || !path) return undefined;
	const segments = path.split('.');
	const root = segments.shift();
	const entry = storeFor(namespace);
	let current;
	if (root === 'context') current = contextFor(s.element, namespace);
	else current = entry.proxy[root];
	const result = runWith({ ...s, namespace }, () => {
		let value = current;
		for (const segment of segments) {
			if (value === null || value === undefined) return undefined;
			value = value[segment];
		}
		return value;
	}, null, []);
	return negate ? !result : result;
}

// ---- Directives.

const VOID_VALUE = (v) => v === null || v === undefined || v === false;

function applyBind(el, attribute, value) {
	const special = attribute.startsWith('aria-') || attribute.startsWith('data-');
	if (value === null || value === undefined || (value === false && !special)) {
		el.removeAttribute(attribute);
		if (attribute === 'value' && 'value' in el) el.value = '';
		return;
	}
	if (typeof value === 'boolean') {
		if (special) el.setAttribute(attribute, String(value));
		else el.setAttribute(attribute, '');
		if (attribute in el && typeof el[attribute] === 'boolean') el[attribute] = value;
		return;
	}
	if (typeof value === 'object') return;
	el.setAttribute(attribute, String(value));
	if (attribute === 'value' && 'value' in el) el.value = String(value);
}

function directivesOf(el) {
	const list = [];
	for (const attr of el.attributes) {
		const name = attr.name;
		if (!name.startsWith('data-wp-')) continue;
		const body = name.slice(8);
		const dash = body.indexOf('--');
		const kind = dash === -1 ? body : body.slice(0, dash);
		const suffix = dash === -1 ? null : body.slice(dash + 2);
		list.push({ kind, suffix, value: attr.value });
	}
	return list;
}

const hydrated = new WeakSet();

function hydrate(el, inherited) {
	if (el.nodeType !== 1 || hydrated.has(el)) return;
	if (el.hasAttribute('data-wp-ignore')) return;
	hydrated.add(el);
	const directives = directivesOf(el);
	let namespace = inherited;
	const interactive = directives.find((d) => d.kind === 'interactive');
	if (interactive) {
		const raw = interactive.value.trim();
		if (raw.startsWith('{')) {
			try {
				const parsed = JSON.parse(raw);
				if (parsed && typeof parsed.namespace === 'string' && parsed.namespace) namespace = parsed.namespace;
			} catch (e) {
				/* keep inherited */
			}
		} else if (raw) {
			namespace = raw;
		}
	}
	for (const d of directives) {
		if (d.kind === 'context') declareContext(el, namespace, d.value);
	}
	const s = { element: el, namespace };
	const isTemplate = el.tagName === 'TEMPLATE';
	for (const d of directives) {
		switch (d.kind) {
			case 'bind':
				effect(() => applyBind(el, d.suffix, evaluate(d.value, s)));
				break;
			case 'class':
				effect(() => {
					if (evaluate(d.value, s)) el.classList.add(d.suffix);
					else el.classList.remove(d.suffix);
				});
				break;
			case 'style':
				effect(() => {
					const value = evaluate(d.value, s);
					if (VOID_VALUE(value) || value === '') el.style.removeProperty(d.suffix);
					else el.style.setProperty(d.suffix, String(value));
				});
				break;
			case 'text':
				effect(() => {
					const value = evaluate(d.value, s);
					el.textContent = typeof value === 'string' || typeof value === 'number' ? String(value) : '';
				});
				break;
			case 'on':
			case 'on-window':
			case 'on-document': {
				const target = d.kind === 'on' ? el : d.kind === 'on-window' ? window : document;
				target.addEventListener(d.suffix, (event) => {
					const handler = evaluate(d.value, s);
					if (typeof handler === 'function') runWith(s, handler, null, [event]);
				});
				break;
			}
			case 'init':
				queueMicrotask(() => {
					const callback = evaluate(d.value, s);
					if (typeof callback === 'function') runWith(s, callback, null, []);
				});
				break;
			case 'watch':
				effect(() => {
					const callback = evaluate(d.value, s);
					if (typeof callback === 'function') return runWith(s, callback, null, []);
				});
				break;
			case 'each':
				if (isTemplate) renderEach(el, d, s);
				break;
			default:
				break;
		}
	}
	if (isTemplate) return;
	for (const child of [...el.children]) hydrate(child, namespace);
}

function renderEach(template, d, s) {
	const key = d.suffix ? d.suffix.replace(/-([a-z])/g, (m, c) => c.toUpperCase()) : 'item';
	const parsed = parseNamespaced(d.value);
	const namespace = parsed.namespace || s.namespace;
	let generated = [];
	let first = true;
	effect(() => {
		const list = evaluate(d.value, s);
		const items = Array.isArray(list) ? [...list] : [];
		if (first) {
			first = false;
			let sibling = template.nextElementSibling;
			const server = [];
			while (sibling && sibling.hasAttribute('data-wp-each-child')) {
				server.push(sibling);
				sibling = sibling.nextElementSibling;
			}
			if (server.length) {
				generated = server;
				server.forEach((child, index) => {
					const own = { [key]: items[index] };
					const parent = contextFor(template.parentElement, namespace);
					let map = contexts.get(child);
					if (!map) contexts.set(child, (map = new Map()));
					map.set(namespace, contextLayer(own, parent));
					hydrate(child, s.namespace);
				});
				return;
			}
		}
		generated.forEach((node) => node.remove());
		generated = [];
		let anchor = template;
		items.forEach((item) => {
			const fragment = template.content.cloneNode(true);
			const nodes = [...fragment.children];
			nodes.forEach((node) => {
				node.setAttribute('data-wp-each-child', namespace + '::' + parsed.value);
				const parent = contextFor(template.parentElement, namespace);
				let map = contexts.get(node);
				if (!map) contexts.set(node, (map = new Map()));
				map.set(namespace, contextLayer({ [key]: item }, parent));
			});
			anchor.after(fragment);
			nodes.forEach((node) => {
				generated.push(node);
				hydrate(node, s.namespace);
				anchor = node;
			});
		});
	});
}

function init(root = document.body) {
	if (!root) return;
	for (const child of [...root.children]) hydrate(child, null);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => init());
} else {
	queueMicrotask(() => init());
}

export { init as hydrate };
