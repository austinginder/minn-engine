/*! Minn Engine image block view | MIT | The core/image store the lightbox markup calls into. */
import { store, getContext, getElement, getConfig, withScope } from '@wordpress/interactivity';

// The overlay's margin around the enlarged image, on each side.
const MARGIN = 40;
// What opened the lightbox (its trigger or the image), focused again when it closes; the image it shows.
let opener = null;
let shown = null;
let preload = null;

// The images of a gallery in the order the page shows them; just the one image outside a gallery.
function sequence(imageId) {
	const galleryId = state.metadata[imageId]?.galleryId;
	if (!galleryId) return [imageId];
	return [...document.querySelectorAll('[data-wp-interactive="core/image"][data-wp-key]')]
		.map((figure) => figure.getAttribute('data-wp-key'))
		.filter((id) => state.metadata[id]?.galleryId === galleryId);
}

const { state, actions, callbacks } = store('core/image', {
	state: {
		currentImageId: null,
		overlayEnabled: false,
		overlayOpened: false,
		overlayStyles: '',
		triggerPositions: {},
		get thisImage() {
			const { imageId } = getContext();
			return { ...(state.metadata[imageId] || {}), buttonRight: state.triggerPositions[imageId]?.right, buttonTop: state.triggerPositions[imageId]?.top };
		},
		// Nothing is selected while the lightbox is closed: its image has an empty alt and no source.
		get selectedImage() {
			return state.metadata[state.currentImageId] || { alt: '' };
		},
		get isContentHidden() {
			const { imageId } = getContext();
			return state.overlayEnabled && state.currentImageId === imageId;
		},
		get isContentVisible() {
			const { imageId } = getContext();
			return !state.overlayEnabled && state.currentImageId === imageId;
		},
		get roleAttribute() {
			return state.overlayEnabled ? 'dialog' : null;
		},
		get ariaModal() {
			return state.overlayEnabled ? 'true' : null;
		},
		// The overlay and its buttons are labelled while it is open.
		get ariaLabel() {
			return state.overlayEnabled ? state.selectedImage.customAriaLabel || getConfig('core/image').defaultAriaLabel || null : null;
		},
		get closeButtonAriaLabel() {
			return state.overlayEnabled ? getConfig('core/image').closeButtonText : null;
		},
		get prevButtonAriaLabel() {
			return state.overlayEnabled ? getConfig('core/image').prevButtonText : null;
		},
		get nextButtonAriaLabel() {
			return state.overlayEnabled ? getConfig('core/image').nextButtonText : null;
		},
		get hasNavigation() {
			return state.currentImageId !== null && sequence(state.currentImageId).length > 1;
		},
		get hasNavigationIcon() {
			const type = state.selectedImage.navigationButtonType || 'icon';
			return type === 'icon' || type === 'both';
		},
		get hasNavigationText() {
			const type = state.selectedImage.navigationButtonType || 'icon';
			return type === 'text' || type === 'both';
		},
		get enlargedSrc() {
			return state.selectedImage.uploadedSrc || null;
		},
		get enlargedSrcset() {
			return state.selectedImage.lightboxSrcset || null;
		},
		get figureStyles() {
			return state.selectedImage.figureStyles || '';
		},
		// The enlarged image fills its box the way the page's own fills its own, a set object-fit aside.
		get imgStyles() {
			return (state.selectedImage.imgStyles || '').replace(/object-fit\s*:[^;]*;?/g, '') + 'object-fit: cover;';
		},
	},
	actions: {
		showLightbox() {
			const { imageId } = getContext();
			const meta = state.metadata[imageId];
			if (!meta) return;
			const figure = getElement().ref.closest('figure');
			shown = figure && figure.querySelector('img');
			if (shown) meta.currentSrc = shown.currentSrc || shown.src;
			opener = figure && figure.querySelector('button.lightbox-trigger');
			state.currentImageId = imageId;
			callbacks.setOverlayStyles();
			state.overlayEnabled = true;
			state.overlayOpened = true;
		},
		hideLightbox() {
			if (!state.overlayEnabled) return;
			state.overlayEnabled = false;
			state.overlayOpened = false;
			state.currentImageId = null;
		},
		handleKeydown(event) {
			if (!state.overlayEnabled) return;
			if (event.key === 'Escape') {
				event.preventDefault();
				actions.hideLightbox();
			} else if (event.key === 'ArrowLeft' && state.hasNavigation) {
				actions.showPreviousImage(event);
			} else if (event.key === 'ArrowRight' && state.hasNavigation) {
				actions.showNextImage(event);
			} else if (event.key === 'Tab') {
				// Focus stays inside the open lightbox.
				const buttons = [...getElement().ref.querySelectorAll('button:not([hidden])')];
				if (buttons.length === 0) return;
				const at = buttons.indexOf(document.activeElement);
				event.preventDefault();
				buttons[event.shiftKey ? (at <= 0 ? buttons.length - 1 : at - 1) : (at + 1) % buttons.length].focus();
			}
		},
		showPreviousImage(event) {
			event?.stopPropagation();
			actions.step(-1);
		},
		showNextImage(event) {
			event?.stopPropagation();
			actions.step(1);
		},
		step(by) {
			const ids = sequence(state.currentImageId);
			const next = ids[(ids.indexOf(state.currentImageId) + by + ids.length) % ids.length];
			const figure = document.querySelector(`[data-wp-interactive="core/image"][data-wp-key="${CSS.escape(next)}"]`);
			shown = figure && figure.querySelector('img');
			if (shown) state.metadata[next].currentSrc = shown.currentSrc || shown.src;
			state.currentImageId = next;
			callbacks.setOverlayStyles();
		},
		handleTouchStart() {},
		handleTouchMove() {},
		handleTouchEnd() {},
		handleScroll() {},
		preloadImage() {
			const { imageId } = getContext();
			const meta = state.metadata[imageId];
			if (!meta || meta.preloaded) return;
			const image = new Image();
			if (meta.lightboxSrcset) image.srcset = meta.lightboxSrcset;
			image.src = meta.uploadedSrc;
			meta.preloaded = true;
		},
		preloadImageWithDelay() {
			window.clearTimeout(preload);
			preload = window.setTimeout(withScope(() => actions.preloadImage()), 200);
		},
		cancelPreload() {
			window.clearTimeout(preload);
		},
	},
	callbacks: {
		// The trigger sits at the top right of the image as it is drawn, 16px in.
		setButtonStyles() {
			const { imageId } = getContext();
			const img = getElement().ref;
			const figure = img.closest('figure');
			if (!figure || !img.offsetWidth) return;
			const right = figure.clientWidth - (img.offsetLeft + img.offsetWidth);
			state.triggerPositions[imageId] = { right: `${16 + Math.max(0, right)}px`, top: `${16 + Math.max(0, img.offsetTop)}px` };
		},
		initTriggerButton() {},
		// Open, the overlay takes the focus; closed, it goes back to what opened it.
		setOverlayFocus() {
			if (state.overlayEnabled) {
				getElement().ref.focus({ preventScroll: true });
			} else if (opener) {
				// Once the page is reachable again (setInertElements runs in the same pass).
				const back = opener;
				opener = null;
				window.setTimeout(() => back.focus({ preventScroll: true }), 0);
			}
		},
		// Open, the rest of the page cannot be reached.
		setInertElements() {
			const overlay = getElement().ref;
			const open = state.overlayEnabled;
			for (const element of document.body.children) {
				if (element !== overlay && !element.contains(overlay)) element.toggleAttribute('inert', open);
			}
		},
		// The sizes the overlay's stylesheet works from: where the page's image is, and the enlarged box in the window.
		setOverlayStyles() {
			const meta = state.selectedImage;
			if (!shown || !meta) return;
			const rect = shown.getBoundingClientRect();
			const naturalWidth = meta.targetWidth !== 'none' && meta.targetWidth ? Number(meta.targetWidth) : shown.naturalWidth || rect.width;
			const naturalHeight = meta.targetHeight !== 'none' && meta.targetHeight ? Number(meta.targetHeight) : shown.naturalHeight || rect.height;
			const roomWidth = document.documentElement.clientWidth - MARGIN * 2;
			const roomHeight = window.innerHeight - MARGIN * 2;
			const ratio = Math.min(1, roomWidth / naturalWidth, roomHeight / naturalHeight);
			const width = naturalWidth * ratio;
			const height = naturalHeight * ratio;
			state.overlayStyles = [
				`--wp--lightbox-initial-top-position: ${rect.top}px;`,
				`--wp--lightbox-initial-left-position: ${rect.left}px;`,
				`--wp--lightbox-container-width: ${width + 1}px;`,
				`--wp--lightbox-container-height: ${height + 1}px;`,
				`--wp--lightbox-image-width: ${width}px;`,
				`--wp--lightbox-image-height: ${height}px;`,
				`--wp--lightbox-scale: ${rect.width / width};`,
				`--wp--lightbox-scrollbar-width: ${window.innerWidth - document.documentElement.clientWidth}px;`,
			].join(' ');
		},
	},
});
