/*! Minn Engine a11y module | MIT | speak() writes into the live regions the page prints. */
export function speak(message, ariaLive = 'polite') {
	const region = document.getElementById(ariaLive === 'assertive' ? 'a11y-speak-assertive' : 'a11y-speak-polite');
	if (!region) return;
	document.querySelectorAll('.a11y-speak-region').forEach((node) => {
		node.textContent = '';
	});
	region.textContent = String(message).replace(/<[^<>]+>/g, ' ');
}
