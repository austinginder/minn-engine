<?php

declare(strict_types=1);

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\RouteRow;
use Minn\Http\Subject;

/** The route table row: read from the attribute and the method alone, named after the handler unless the route names itself, and written as data. */
final class RouteRowProbe
{
    /**
     * Lists the things. Nothing else to say.
     *
     * @param Request $request the request
     */
    #[Route(Method::Get, '/things', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function list(Request $request): Response
    {
        return Response::html('');
    }

    /** Edits one thing */
    #[Route(Method::Post, '/things/{id:\d+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, refuse: 'rest_cannot_edit', message: 'No.'), name: 'things.edit', body: [['title' => ['type' => 'string']]])]
    public function edit(Request $request, string $id): Response
    {
        return Response::html('');
    }
}

$rows = [];
foreach ((new ReflectionClass(RouteRowProbe::class))->getMethods() as $method) {
    foreach ($method->getAttributes(Route::class) as $attribute) {
        $rows[] = RouteRow::of($attribute->newInstance(), $method);
    }
}

return [
    'a row takes its name from the handler unless the route names itself' => static fn (): bool|string => $rows[0]->name === 'RouteRowProbe::list' && $rows[1]->name === 'things.edit' && $rows[1]->handler === 'RouteRowProbe::edit' ? true : json_encode([$rows[0]->name, $rows[1]->name]),
    'the summary is the docblock\'s first sentence' => static fn (): bool|string => $rows[0]->summary === 'Lists the things.' && $rows[1]->summary === 'Edits one thing' ? true : json_encode([$rows[0]->summary, $rows[1]->summary]),
    'the row as data carries the structured policy, the args, and the body' => static function () use ($rows): bool|string {
        $data = $rows[1]->toArray();
        $policy = $data['policy'];
        return $data['method'] === 'POST' && $data['pattern'] === '/things/{id:\d+}' && array_keys($data['body']) === ['title']
            && $policy['access'] === 'Own' && $policy['cap'] === 'edit_post' && $policy['subject'] === 'Post'
            && $policy['missing'] === ['code' => 'rest_post_invalid_id', 'message' => 'Invalid post ID.']
            && $policy['refuse'] === ['code' => 'rest_cannot_edit', 'message' => 'No.']
            && $rows[0]->toArray()['policy'] === ['access' => 'Public', 'describe' => 'public']
            && array_keys($rows[0]->toArray()['args']) === ['context']
            ? true : json_encode($data);
    },
];
