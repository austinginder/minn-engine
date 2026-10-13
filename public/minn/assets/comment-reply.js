/**
 * Minn Engine: threaded comment replies. A reply link that names its
 * comment (data-commentid, data-postid, data-belowelement,
 * data-respondelement, data-replyto) moves the comment form to just after
 * the comment it answers, sets the form's parent and post, shows the
 * cancel link and words the heading as the link says. Cancelling puts the
 * form back where it was. Without this script the links still work: each
 * is an address (?replytocom=) that renders the form for that reply.
 */
(function () {
	'use strict';

	var PLACEHOLDER = 'wp-temp-form-div';
	var heading = null;

	function byId(id) {
		return id ? document.getElementById(id) : null;
	}

	/** The heading's own words: its first text node, before the cancel link. */
	function words(respond) {
		var title = respond.querySelector('#reply-title');
		var node = title ? title.firstChild : null;
		return node && node.nodeType === 3 ? node : null;
	}

	function reply(link) {
		var respond = byId(link.getAttribute('data-respondelement') || 'respond');
		var below = byId(link.getAttribute('data-belowelement'));
		var parent = respond ? respond.querySelector('#comment_parent') : null;
		if (!respond || !below || !parent) {
			return false;
		}
		if (!byId(PLACEHOLDER)) {
			var placeholder = document.createElement('div');
			placeholder.id = PLACEHOLDER;
			placeholder.style.display = 'none';
			respond.parentNode.insertBefore(placeholder, respond);
		}
		below.parentNode.insertBefore(respond, below.nextSibling);
		parent.value = link.getAttribute('data-commentid') || '0';
		var post = respond.querySelector('#comment_post_ID');
		if (post && link.getAttribute('data-postid')) {
			post.value = link.getAttribute('data-postid');
		}
		var cancel = respond.querySelector('#cancel-comment-reply-link');
		if (cancel) {
			cancel.style.display = '';
		}
		var text = words(respond);
		if (text && link.getAttribute('data-replyto')) {
			if (heading === null) {
				heading = text.textContent;
			}
			text.textContent = link.getAttribute('data-replyto') + ' ';
		}
		var field = respond.querySelector('#comment') || respond.querySelector('textarea');
		if (field) {
			field.focus();
		}
		return true;
	}

	function cancelReply(cancel) {
		var respond = cancel.closest('.comment-respond') || byId('respond');
		var placeholder = byId(PLACEHOLDER);
		if (!respond || !placeholder) {
			return false;
		}
		placeholder.parentNode.insertBefore(respond, placeholder);
		placeholder.parentNode.removeChild(placeholder);
		var parent = respond.querySelector('#comment_parent');
		if (parent) {
			parent.value = '0';
		}
		cancel.style.display = 'none';
		var text = words(respond);
		if (text && heading !== null) {
			text.textContent = heading;
		}
		return true;
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}
		var link = target.closest('.comment-reply-link');
		if (link && link.hasAttribute('data-commentid')) {
			if (reply(link)) {
				event.preventDefault();
			}
			return;
		}
		var cancel = target.closest('#cancel-comment-reply-link');
		if (cancel && cancelReply(cancel)) {
			event.preventDefault();
		}
	});
})();
