/*! Minn Engine tabs block view | MIT | The core/tabs store the block's markup calls into. */
import { store, getContext, getElement } from '@wordpress/interactivity';

// The tab buttons of the tabs block an element belongs to (not a nested block's), in order.
function tabsOf(element) {
	const root = element.closest('[data-wp-interactive="core/tabs"]');
	if (!root) return [];
	return [...root.querySelectorAll('[data-wp-on--click="actions.handleTabClick"]')].filter((button) => button.closest('[data-wp-interactive="core/tabs"]') === root);
}

const { state } = store('core/tabs', {
	state: {
		// A tab is active when its index is the block's; a panel when its id is the one at that index in the block's list.
		get isActiveTab() {
			const ctx = getContext();
			if (typeof ctx.tabIndex === 'number') return ctx.tabIndex === ctx.activeTabIndex;
			const { ref } = getElement();
			const ids = state[ctx.tabsId] || [];
			return ids.indexOf(ref.id) === ctx.activeTabIndex;
		},
		// Only the active tab is in the page's tab order; the arrow keys reach the rest.
		get tabIndexAttribute() {
			return state.isActiveTab ? 0 : -1;
		},
	},
	actions: {
		handleTabClick() {
			const ctx = getContext();
			if (typeof ctx.tabIndex === 'number') ctx.activeTabIndex = ctx.tabIndex;
		},
		// The arrow keys move the focus along the tabs, round from either end, and Home and End to the first and last;
		// a tab is chosen when it is clicked (Enter and Space click a button).
		handleTabKeyDown(event) {
			if (event.defaultPrevented) return;
			const tabs = tabsOf(getElement().ref);
			const current = tabs.indexOf(event.target);
			if (current === -1) return;
			const last = tabs.length - 1;
			const next = { ArrowRight: current === last ? 0 : current + 1, ArrowLeft: current === 0 ? last : current - 1, Home: 0, End: last }[event.key];
			if (next === undefined) return;
			event.preventDefault();
			tabs[next].focus();
		},
	},
	callbacks: {
		// A page opened at a panel's address shows that panel.
		onTabsInit() {
			const ctx = getContext();
			const hash = decodeURIComponent(window.location.hash.slice(1));
			const index = hash ? (state[ctx.tabsId] || []).indexOf(hash) : -1;
			if (index !== -1) ctx.activeTabIndex = index;
		},
	},
});
