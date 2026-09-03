<?php

declare(strict_types=1);

use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Subject;

/** A policy that names a subject: the record's own 404, the describe line, and that the gate must run for it. */
return [
    'every subject names its 404 code and message' => static function (): bool|string {
        $pairs = [];
        foreach (Subject::cases() as $subject) {
            $pairs[$subject->name] = [$subject->missingCode(), $subject->missingMessage()];
        }
        return $pairs['Post'] === ['rest_post_invalid_id', 'Invalid post ID.']
            && $pairs['PostParent'] === ['rest_post_invalid_parent', 'Invalid post parent ID.']
            && $pairs['Term'] === ['rest_term_invalid', 'Term does not exist.']
            && $pairs['Menu'] === $pairs['Term']
            && $pairs['User'] === ['rest_user_invalid_id', 'Invalid user ID.']
            && $pairs['Comment'] === ['rest_comment_invalid_id', 'Invalid comment ID.']
            && $pairs['GlobalStyles'] === ['rest_global_styles_not_found', 'No global styles config exists with that ID.']
            && count($pairs) === 12
            ? true : json_encode($pairs);
    },
    'a policy with a subject is not public, and takes the subject\'s 404 unless it names its own' => static function (): bool|string {
        $own = new Policy(Access::Public, subject: Subject::Post, param: 'id');
        $named = new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, missing: 'rest_custom', missingMessage: 'Custom.');
        return !$own->isPublic() && $own->missingCode() === 'rest_post_invalid_id' && $own->missingText() === 'Invalid post ID.'
            && $named->missingCode() === 'rest_custom' && $named->missingText() === 'Custom.'
            && (new Policy())->isPublic()
            ? true : json_encode([$own->describe(), $named->describe()]);
    },
    'describe names the subject and the declared-type access' => static function (): bool|string {
        $lines = [
            (new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent))->describe(),
            (new Policy(Access::Type, param: 'base'))->describe(),
            (new Policy(Access::Public, subject: Subject::GlobalStyles, param: 'id', edit: new Policy(Access::Cap, 'edit_theme_options')))->describe(),
        ];
        return $lines === ['cap edit_post on {id}; post parent {id} must exist', 'declared type {base}', 'public; global styles {id} must exist; edit context: cap edit_theme_options'] ? true : json_encode($lines);
    },
];
