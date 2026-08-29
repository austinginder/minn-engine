/*! Minn Engine interactivity router | MIT | Full page loads; the client-side navigation contract is honoured with real navigation. */
import { store } from '@wordpress/interactivity';

export const { state, actions } = store('core/router', {
	state: {
		url: window.location.href,
		navigation: { hasStarted: false, hasFinished: false },
	},
	actions: {
		*navigate(href) {
			state.navigation.hasStarted = true;
			window.location.href = href;
		},
		*prefetch() {},
	},
});
