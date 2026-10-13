/*! Minn Engine accordion block view | MIT | The core/accordion store the block's markup calls into. */
import { store, getContext } from '@wordpress/interactivity';

// The entry an accordion keeps for one of its items ({ id, isOpen }), from the item's own context.
function entryOf(ctx) {
	return (ctx.accordionItems || []).find((item) => item.id === ctx.id);
}

const { state, actions, callbacks } = store('core/accordion', {
	state: {
		get isOpen() {
			const ctx = getContext();
			const entry = entryOf(ctx);
			return entry ? entry.isOpen : !!ctx.openByDefault;
		},
		// A closed panel stays findable: the browser's find in page opens it (actions.handleBeforeMatch).
		get isHidden() {
			return state.isOpen ? false : 'until-found';
		},
	},
	actions: {
		toggle() {
			actions.setOpen(!state.isOpen);
		},
		// Opening an item closes the others when the accordion opens one at a time.
		setOpen(open) {
			const ctx = getContext();
			const entry = entryOf(ctx);
			if (!entry) return;
			if (open && ctx.autoclose) {
				ctx.accordionItems.forEach((item) => {
					if (item !== entry) item.isOpen = false;
				});
			}
			entry.isOpen = open;
		},
		handleBeforeMatch() {
			actions.setOpen(true);
		},
	},
	callbacks: {
		initAccordionItems() {
			const ctx = getContext();
			if (!Array.isArray(ctx.accordionItems)) return;
			if (!entryOf(ctx)) ctx.accordionItems.push({ id: ctx.id, isOpen: !!ctx.openByDefault });
			callbacks.hashChange();
		},
		// An address naming an item's panel opens the item.
		hashChange() {
			const ctx = getContext();
			if (decodeURIComponent(window.location.hash.slice(1)) === ctx.id + '-panel') actions.setOpen(true);
		},
	},
});
