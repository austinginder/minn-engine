/*! Minn Engine navigation block view | MIT | The core/navigation store the block's markup calls into. */
import { store, getContext, getElement } from '@wordpress/interactivity';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const { state, actions } = store('core/navigation', {
	state: {
		get roleAttribute() {
			const ctx = getContext();
			return ctx.type === 'overlay' && state.isMenuOpen ? 'dialog' : null;
		},
		get ariaModal() {
			const ctx = getContext();
			return ctx.type === 'overlay' && state.isMenuOpen ? 'true' : null;
		},
		get ariaLabel() {
			const ctx = getContext();
			return ctx.type === 'overlay' && state.isMenuOpen ? ctx.ariaLabel : null;
		},
		get menuOpenedBy() {
			const ctx = getContext();
			return ctx.type === 'overlay' ? ctx.overlayOpenedBy : ctx.submenuOpenedBy;
		},
		get isMenuOpen() {
			const by = state.menuOpenedBy || {};
			return Object.values(by).some(Boolean);
		},
		get isSubmenuOpen() {
			return state.isMenuOpen;
		},
	},
	actions: {
		openMenuOnHover() {
			const ctx = getContext();
			if (ctx.type === 'submenu' && !state.menuOpenedBy.click && !state.menuOpenedBy.focus) {
				actions.openMenu('hover');
			}
		},
		closeMenuOnHover() {
			const ctx = getContext();
			if (ctx.type === 'submenu' && !state.menuOpenedBy.click && !state.menuOpenedBy.focus) {
				actions.closeMenu('hover');
			}
		},
		openMenuOnClick() {
			const ctx = getContext();
			ctx.previousFocus = getElement().ref;
			actions.openMenu('click');
		},
		closeMenuOnClick() {
			actions.closeMenu('click');
			actions.closeMenu('focus');
		},
		openMenuOnFocus() {
			actions.openMenu('focus');
		},
		toggleMenuOnClick() {
			const ctx = getContext();
			const { ref } = getElement();
			if (window.document.activeElement !== ref) {
				ctx.previousFocus = ref;
			}
			if (state.menuOpenedBy.click || state.menuOpenedBy.focus) {
				actions.closeMenu('click');
				actions.closeMenu('focus');
			} else {
				ctx.previousFocus = ref;
				actions.openMenu('click');
			}
		},
		handleMenuKeydown(event) {
			const ctx = getContext();
			if (!state.isMenuOpen) return;
			if (event.key === 'Escape') {
				actions.closeMenu('click');
				actions.closeMenu('focus');
				return;
			}
			if (ctx.type === 'overlay' && event.key === 'Tab' && ctx.modal) {
				const focusable = ctx.modal.querySelectorAll(FOCUSABLE);
				const first = focusable[0];
				const last = focusable[focusable.length - 1];
				if (event.shiftKey && window.document.activeElement === first) {
					event.preventDefault();
					last && last.focus();
				} else if (!event.shiftKey && window.document.activeElement === last) {
					event.preventDefault();
					first && first.focus();
				}
			}
			if (ctx.type === 'submenu' && event.key === 'Tab' && ctx.modal) {
				const focusable = ctx.modal.querySelectorAll(FOCUSABLE);
				const last = focusable[focusable.length - 1];
				if (!event.shiftKey && window.document.activeElement === last) {
					actions.closeMenu('click');
					actions.closeMenu('focus');
				}
			}
		},
		handleMenuFocusout(event) {
			const ctx = getContext();
			const { ref } = getElement();
			if (ctx.type === 'overlay' && ctx.modal) return;
			if (!ref.contains(event.relatedTarget) && event.target !== window.document.activeElement) {
				actions.closeMenu('click');
				actions.closeMenu('focus');
			}
		},
		openMenu(by = 'click') {
			const ctx = getContext();
			state.menuOpenedBy[by] = true;
			if (ctx.type === 'overlay') {
				window.document.documentElement.classList.add('has-modal-open');
			}
		},
		closeMenu(by = 'click') {
			const ctx = getContext();
			if (state.menuOpenedBy[by]) {
				state.menuOpenedBy[by] = false;
			}
			if (!state.isMenuOpen) {
				if (ctx.modal && ctx.modal.contains(window.document.activeElement) && ctx.previousFocus) {
					ctx.previousFocus.focus();
				}
				ctx.modal = null;
				ctx.previousFocus = null;
				if (ctx.type === 'overlay') {
					window.document.documentElement.classList.remove('has-modal-open');
				}
			}
		},
	},
	callbacks: {
		initMenu() {
			const ctx = getContext();
			const { ref } = getElement();
			if (state.isMenuOpen) {
				ctx.modal = ref;
			}
		},
		focusFirstElement() {
			const { ref } = getElement();
			if (state.isMenuOpen) {
				const first = ref.querySelector('.wp-block-navigation-item > *:first-child');
				first && first.focus();
			}
		},
	},
});
