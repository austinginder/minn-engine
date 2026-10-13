<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Comments;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\I18n\Gettext;
use Minn\Objects\Comment;
use Minn\Objects\Errors;
use Minn\Objects\Post;
use Minn\Objects\Term;
use Minn\Support\Sanitize;

/**
 * Posts, terms and comments as WordPress hands them to plugins: found by
 * id (or the one being read), made from a row or an object, and given back
 * as the object, an associative array (ARRAY_A) or a list (ARRAY_N); and
 * whether a value is a list of errors.
 */
final class Records
{
    /**
     * A post (get_post): the one being read when none is named; a post
     * object as given; one made from a row, an object or an array with an
     * ID; else by id, a copy of the cached one (what one plugin changes is
     * not what the next reader sees). Then put in the context asked for.
     */
    public static function post(mixed $post = null, mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed
    {
        if ($post === null || $post === 0 || $post === '' || $post === false) {
            // Only the global post: once a caller clears it there is no current post.
            $post = $GLOBALS['post'] ?? null;
            if ($post === null) {
                return null;
            }
        }
        if ($post instanceof Post) {
            $object = $post;
        } elseif ($post instanceof PostRecord) {
            $object = Handoff::newPost((object) $post->row());
        } elseif (is_object($post) && isset($post->ID)) {
            $object = Handoff::newPost($post);
        } elseif (is_array($post) && isset($post['ID'])) {
            $object = Handoff::newPost((object) $post);
        } else {
            $found = false;
            $cached = Handoff::cacheGet((int) $post, 'posts', false, $found);
            if ($found && is_object($cached)) {
                $object = $cached instanceof Post ? clone $cached : Handoff::newPost($cached);
            } else {
                $row = (int) $post > 0 ? (new Posts(Runtime::current()->db))->find((int) $post) : null;
                if ($row === null) {
                    return null;
                }
                $object = Handoff::newPost((object) $row->row());
                Handoff::cacheSet((int) $post, clone $object, 'posts');
            }
        }
        return self::shaped($object->filter($filter === null || $filter === '' ? 'raw' : (string) $filter), $output);
    }

    /**
     * A term (get_term): an error for none or an unknown taxonomy; a term
     * object as given, one made from an object, else by id (null when there
     * is none). get_term and get_{taxonomy} filter the raw term, told its own
     * taxonomy; then it is put in the context asked for, on a copy of a term
     * the caller handed in.
     */
    public static function term(mixed $term, mixed $taxonomy = '', mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed
    {
        if (empty($term)) {
            return Handoff::newError('invalid_term', 'Empty Term.');
        }
        if ($taxonomy !== '' && !Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        if ($term instanceof Term) {
            $object = $term;
        } elseif (is_object($term)) {
            $object = self::termNumbers(Handoff::newTerm($term));
        } else {
            $row = (new TermQuery(Runtime::current()->db, static fn (string $s): string => Sanitize::title($s)))->row((int) $term, $taxonomy === '' ? null : (string) $taxonomy);
            if ($row === null) {
                return null;
            }
            $object = self::termNumbers(Handoff::newTerm((object) $row));
        }
        $object = Runtime::hooks()->filter('get_term', [$object, (string) $object->taxonomy]);
        $object = Runtime::hooks()->filter("get_{$object->taxonomy}", [$object, $object->taxonomy]);
        if ($filter !== 'raw' && $object instanceof Term) {
            $object = $object === $term ? clone $object : $object;
            $object->filter((string) $filter);
        }
        return self::shaped($object, $output);
    }

    /**
     * A comment (get_comment): the one being read for any empty id; a
     * comment object as given; one made from an object with a comment_ID;
     * else by id, its fields text as the database hands them over (null when
     * there is none). Then get_comment.
     */
    public static function comment(mixed $comment = null, mixed $output = 'OBJECT'): mixed
    {
        if (empty($comment)) {
            $comment = $GLOBALS['comment'] ?? null;
        }
        if ($comment instanceof Comment) {
            $object = $comment;
        } elseif (is_object($comment) && isset($comment->comment_ID)) {
            $object = Handoff::newComment($comment);
        } else {
            $row = (int) $comment > 0 ? (new Comments(Runtime::current()->db))->find((int) $comment) : null;
            if ($row === null) {
                return null;
            }
            $object = Handoff::newComment((object) array_map(static fn (mixed $value): mixed => is_int($value) || is_float($value) ? (string) $value : $value, $row->row()));
        }
        return self::shaped(Runtime::hooks()->filter('get_comment', [$object]), $output);
    }

    /** Whether a value is a list of errors (is_wp_error); one that is is told to is_wp_error_instance. */
    public static function isError(mixed $thing): bool
    {
        $is = $thing instanceof Errors;
        if ($is) {
            Runtime::hooks()->action('is_wp_error_instance', [$thing]);
        }
        return $is;
    }

    /** A term's numbers as integers, as the database's text would not give them. */
    private static function termNumbers(Term $term): Term
    {
        foreach (['term_id', 'term_group', 'term_taxonomy_id', 'parent', 'count'] as $field) {
            $term->{$field} = (int) $term->{$field};
        }
        return $term;
    }

    /** A record as the output asks: an associative array (ARRAY_A), a list (ARRAY_N), else the object. */
    private static function shaped(mixed $object, mixed $output): mixed
    {
        if ($output === 'ARRAY_A') {
            return $object->to_array();
        }
        if ($output === 'ARRAY_N') {
            return array_values($object->to_array());
        }
        return $object;
    }
}
