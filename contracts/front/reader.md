# Readers: comments, post passwords, private posts, previews

Suite: `tests/reader.test.php` (31 checks, most of them the same request on both
stacks with the same cookie). Code: `Minn\Content\Reader` (who is reading this
request), `Minn\Front\CommentPostController`, `Minn\Auth\PortableHash`,
`Minn\Content\PasswordGate`, the resolver and the post blocks.

## wp-comments-post.php

The comment form's target, answering as the reference does:

| Case | Answer |
|---|---|
| Not POST | 405, `Allow: POST` |
| Unknown post | 404 page |
| Comments closed, or the post not readable | 403 page "Sorry, comments are closed for this item." |
| Name or email missing (`require_name_email`) | 200 page "Error: Please fill the required fields." |
| Empty comment | 200 page "Error: Please type your comment text." |
| Same words on the same post from the same person | 409 page "Duplicate comment detected…" (checked before the flood window) |
| Another comment from the same address or email within fifteen seconds | 429 page "You are posting comments too quickly. Slow down." |
| Accepted | 302 to `{permalink}#comment-{id}`; when held and no author cookies are being set, `?unapproved={id}&moderation-hash={hash}` precedes the anchor |

Approval: a signed-in moderator's comment is approved; `comment_moderation` holds
everything; `comment_previously_approved` approves only a name and email that already
have an approved comment (a first-time commenter is held); otherwise approved. A
signed-in commenter's name, email, url, and `user_id` come from the account and no
author cookies are set; anonymous commenters get `comment_author_`,
`comment_author_email_`, and `comment_author_url_` cookies (keyed by the site hash,
one year, path `/`) when they tick the consent box or the site does not ask. Content
goes through the comment allowlist with `rel="nofollow ugc"` on links; a held comment
is announced to `admin_email` when `moderation_notify` is on. `comment_registration`
refuses anonymous comments. The moderation hash is the engine's own HMAC (the
reference's is not readable from output); the pending comment is not shown back to
its author yet.

## Post passwords

`POST wp-login.php?action=postpass` with `post_password` and `redirect_to` sets
`wp-postpass_{COOKIEHASH}` for ten days on `/` and sends the reader back (same-site
only); a wrong password leaves the post locked. The cookie holds a portable phpass
hash of the password, `$P$B…` with 2^13 iterations, implemented from the published
algorithm in `PortableHash`: the reference's cookie unlocks the post on the engine
and the engine's cookie unlocks it on the reference (the reference accepts no weaker
iteration count). Unlocked, the content shows while the title keeps its
"Protected: " prefix.

## Private posts

Anonymous readers and authors without `read_private_posts` get 404 on both stacks;
an editor reads the post with a "Private: " title prefix, and listings and feeds
include private posts for that reader (`Reader::listableStatuses`). A signed-in
reader's page carries the `logged-in` body class; the reference also adds
`admin-bar no-customize-support`, which the engine, having no admin bar, does not.

## Previews

An autosave's `preview_link` carries a real `post_preview_{id}` nonce for the
caller's session (the nonce scheme is the reference's, so the reference accepts the
engine's link and shows the same autosave). On `?p={id}&preview_id={id}&preview_nonce=…&preview=true`
the resolver marks the resolution a preview when the nonce is the reader's and they
may edit the post; the post-title and post-content blocks then read the reader's
newest autosave. Anyone else gets the plain draft rules (404 for anonymous readers).
