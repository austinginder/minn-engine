<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\Subject;
use Minn\Json;
use Minn\Objects\Errors;
use Minn\RestError;
use Minn\Runtime\FileUpload;
use Minn\Runtime\Handoff;
use Minn\Runtime\MetaData;
use Minn\Runtime\PostEvents;
use Minn\Runtime\PostWrites;
use Minn\Runtime\Runtime;
use Minn\Support\Escape;
use Minn\Support\Slashes;
use Minn\Theme\FontNames;

/**
 * wp/v2/font-families written (probe font-writes): a family created and
 * updated, a face created under its family, each saved as the reference
 * saves it. The settings are judged before the caller (Rest\FontSettings);
 * only someone who may edit theme options writes. A family is stored as
 * its name (the title), its slug and the rest of its settings as JSON
 * content, refused when another family has the slug (an empty slug matches
 * any); its update merges what it names over what is stored. A face is
 * stored under its identity (Theme\FontNames::faceSlug), refused when a
 * face of any family already has it, its uploaded files moved into the fonts
 * folder in the order its sources name them (a refused file leaves the
 * ones before it in place) and recorded once the answer is shaped. Each
 * save fires the post's own hooks, then rest_insert_{type},
 * rest_after_insert_{type} and wp_after_insert_post.
 */
final readonly class FontWritesController
{
    private const CREATE = 'Sorry, you are not allowed to create posts as this user.';
    private const EDIT = 'Sorry, you are not allowed to edit this post.';
    private const FILE_META = '_wp_font_face_file';

    public function __construct(private Posts $posts, private FontObject $object, private RestUrl $url)
    {
    }

    /** A font family created from its settings. */
    #[Route(Method::Post, '/wp/v2/font-families', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_create', signInMessage: self::CREATE, refuse: 'rest_cannot_create', message: self::CREATE), args: [FontArgs::FAMILY_CREATE], refuse: FontFamilyCreation::class)]
    public function createFamily(Request $request): Response
    {
        $settings = FontSettings::cleanFamily(FontSettings::newFamily($request));
        $slug = (string) $settings['slug'];
        if ($this->found(['post_type' => 'wp_font_family', 'name' => $slug])) {
            throw new RestError('rest_duplicate_font_family', sprintf('A font family with slug "%s" already exists.', $slug), 400);
        }
        $id = self::saved([
            'post_type' => 'wp_font_family',
            'post_status' => 'publish',
            'post_title' => (string) $settings['name'],
            'post_name' => $slug,
            'post_content' => Json::encode(array_diff_key($settings, ['name' => true, 'slug' => true]), Json::ESCAPED),
        ], $request, null);
        return Reply::answer($request, $this->object->family($this->post($id)), 201)
            ->withHeader('Location', $this->url->to("/wp/v2/font-families/{$id}"));
    }

    /** A font family's name, font-family list or preview changed; what the settings leave out stays. */
    #[Route(Method::Post, '/wp/v2/font-families/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::FontFamily, signIn: 'rest_cannot_edit', signInMessage: self::EDIT, refuse: 'rest_cannot_edit', message: self::EDIT), args: [FontArgs::FAMILY_UPDATE], refuse: FontFamilyChange::class)]
    #[Route(Method::Put, '/wp/v2/font-families/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::FontFamily, signIn: 'rest_cannot_edit', signInMessage: self::EDIT, refuse: 'rest_cannot_edit', message: self::EDIT), args: [FontArgs::FAMILY_UPDATE], refuse: FontFamilyChange::class)]
    #[Route(Method::Patch, '/wp/v2/font-families/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::FontFamily, signIn: 'rest_cannot_edit', signInMessage: self::EDIT, refuse: 'rest_cannot_edit', message: self::EDIT), args: [FontArgs::FAMILY_UPDATE], refuse: FontFamilyChange::class)]
    public function updateFamily(Request $request, string $id): Response
    {
        $family = $this->post((int) $id);
        $stored = Json::decode($family->content, or: null);
        $settings = array_merge(['name' => $family->title, 'slug' => $family->slug], is_array($stored) ? $stored : [], FontSettings::cleanFamily(FontSettings::familyChanges($request)));
        self::saved([
            'ID' => $family->id,
            'post_title' => (string) $settings['name'],
            'post_content' => Json::encode(array_diff_key($settings, ['name' => true, 'slug' => true]), Json::ESCAPED),
        ], $request, $family);
        return Reply::answer($request, $this->object->family($this->post($family->id)));
    }

    /** A font face created under its family, from remote sources or files the request uploads. */
    #[Route(Method::Post, '/wp/v2/font-families/{font_family_id:[\d]+}/font-faces', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_create', signInMessage: self::CREATE, refuse: 'rest_cannot_create', message: self::CREATE), args: [FontArgs::FACE_CREATE], refuse: FontFaceCreation::class)]
    public function createFace(Request $request, string $fontFamilyId): Response
    {
        $family = $this->posts->find((int) $fontFamilyId);
        if ($family === null || $family->type !== 'wp_font_family') {
            throw new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
        }
        $settings = FontSettings::cleanFace(FontSettings::newFace($request));
        $identity = FontNames::faceSlug($settings);
        // Any family's face by that identity counts, as on the reference, not only this family's.
        if ($this->found(['post_type' => 'wp_font_face', 'title' => $identity])) {
            throw new RestError('rest_duplicate_font_face', 'A font face matching those settings already exists.', 400);
        }
        [$settings['src'], $files] = self::stored($settings['src'], $request);
        $id = self::saved([
            'post_type' => 'wp_font_face',
            'post_status' => 'publish',
            'post_title' => $identity,
            'post_name' => $identity,
            'post_content' => Json::encode($settings, Json::ESCAPED),
            'post_parent' => $family->id,
        ], $request, null);
        $answer = Reply::answer($request, $this->object->face($this->post($id)), 201)
            // The reference names the face under its route's pattern, its family id unfilled.
            ->withHeader('Location', $this->url->to('/wp/v2/font-families/(?P<font_family_id>[\d]+)/font-faces/' . $id));
        foreach ($files as $file) {
            MetaData::add('post', $id, self::FILE_META, $file);
        }
        return $answer;
    }

    /**
     * A face's sources with each file the request uploads moved into the
     * fonts folder and named by its address (a remote one is cleaned), and
     * the stored files' names. A refused file ends the save; those before
     * it stay where they were moved.
     *
     * @return array{0: string|list<string>, 1: list<string>}
     */
    private static function stored(mixed $src, Request $request): array
    {
        $files = [];
        $sources = [];
        foreach (FontSettings::sources($src) as $source) {
            if (!isset($request->files[$source])) {
                $sources[] = Escape::url($source, null, 'db');
                continue;
            }
            $file = (array) $request->files[$source];
            // For the move alone, the fonts folder is the uploads folder and the font types are ones the site takes.
            $fontTypes = static fn (mixed $types): array => array_merge((array) $types, FontNames::MIME_TYPES);
            Runtime::hooks()->add('upload_dir', '_wp_filter_font_directory');
            Runtime::hooks()->add('upload_mimes', $fontTypes);
            try {
                $moved = FileUpload::handle($file, ['test_form' => false, 'mimes' => FontNames::MIME_TYPES], null, 'wp_handle_upload');
            } finally {
                Runtime::hooks()->remove('upload_mimes', $fontTypes);
                Runtime::hooks()->remove('upload_dir', '_wp_filter_font_directory');
            }
            if (isset($moved['error'])) {
                throw new RestError('rest_font_upload_invalid_file_type', (string) $moved['error'], 400);
            }
            $sources[] = (string) $moved['url'];
            $files[] = basename((string) $moved['file']);
        }
        return [is_array($src) ? $sources : $sources[0], $files];
    }

    /**
     * A font post saved through the post's own save, its hooks and then the
     * REST insert actions; a save that fails is answered with its error.
     *
     * @param array<string, mixed> $columns
     */
    private static function saved(array $columns, Request $request, ?PostRecord $before): int
    {
        $id = isset($columns['ID']) ? PostWrites::update(Slashes::add($columns), true, false) : PostWrites::insert(Slashes::add($columns), true, false);
        if ($id instanceof Errors) {
            throw new RestError((string) $id->get_error_code(), (string) $id->get_error_message(), 500);
        }
        $events = new PostEvents();
        $events->restInserted((int) $id, $request, $before);
        $events->restAfterInsert((int) $id, $request, $before);
        $events->afterInsert((int) $id, $before);
        return (int) $id;
    }

    /** Whether a post matches the query, as the reference looks for a family's slug or a face's identity (an empty value narrows nothing). @param array<string, mixed> $vars */
    private function found(array $vars): bool
    {
        return (array) Handoff::newQuery()->query($vars + ['fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false]) !== [];
    }

    /** The post just saved. */
    private function post(int $id): PostRecord
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        return $post;
    }
}
