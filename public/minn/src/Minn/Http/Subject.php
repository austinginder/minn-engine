<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The record a route capture names, so a policy can have it looked up
 * before the caller is judged: the reference answers "no such post" ahead
 * of "you may not", and the 404 it sends is the record's own. A kind that
 * reads its post type or taxonomy from the {base} capture says so.
 */
enum Subject
{
    /** A post of the type the {base} capture names (posts, pages, blocks, media, navigation, menu-items); a post when there is none. */
    case Post;
    /** The same lookup, refused as an invalid parent (revisions and autosaves). */
    case PostParent;
    case Attachment;
    case Block;
    case Navigation;
    case MenuItem;
    case GlobalStyles;
    /** A global styles post, refused as an invalid parent (its revisions). */
    case GlobalStylesParent;
    /** A term of the taxonomy the {base} capture names (categories, tags, wp_pattern_category). */
    case Term;
    case Menu;
    /** A user by id, or "me" for the caller, who must then be signed in. */
    case User;
    case Comment;

    /** The reference's error code for a record that does not exist. */
    public function missingCode(): string
    {
        return match ($this) {
            self::Post, self::Attachment, self::Block, self::Navigation, self::MenuItem => 'rest_post_invalid_id',
            self::PostParent, self::GlobalStylesParent => 'rest_post_invalid_parent',
            self::GlobalStyles => 'rest_global_styles_not_found',
            self::Term, self::Menu => 'rest_term_invalid',
            self::User => 'rest_user_invalid_id',
            self::Comment => 'rest_comment_invalid_id',
        };
    }

    /** The reference's message for a record that does not exist. */
    public function missingMessage(): string
    {
        return match ($this) {
            self::Post, self::Attachment, self::Block, self::Navigation, self::MenuItem => 'Invalid post ID.',
            self::PostParent, self::GlobalStylesParent => 'Invalid post parent ID.',
            self::GlobalStyles => 'No global styles config exists with that ID.',
            self::Term, self::Menu => 'Term does not exist.',
            self::User => 'Invalid user ID.',
            self::Comment => 'Invalid comment ID.',
        };
    }
}
