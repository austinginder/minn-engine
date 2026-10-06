<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Refusal;
use Minn\Runtime\Runtime;

/**
 * wp/v2/block-renderer as the reference answers it (probe
 * rest-block-renderer), the route the editor's ServerSideRender asks: a
 * dynamic block rendered from the attributes sent (query or body), checked
 * against the block's own attributes and filled in with their defaults,
 * inside the post named by post_id when there is one (it becomes the
 * global post, and the block's postId and postType context). Only the edit
 * context is accepted, and asking for none is asking for the view context,
 * which is refused, as on the reference. Arguments are judged before the
 * caller: someone who can edit posts may render, or edit the post named.
 */
final readonly class BlockRendererController
{
    private const ARGS = [
        'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['edit'], 'default' => 'view', 'required' => false, Args::HANDLER_VALIDATES => true],
        'attributes' => ['description' => 'Attributes for the block.', 'type' => 'object', 'default' => [], 'required' => false, Args::HANDLER_VALIDATES => true],
        'post_id' => ['description' => 'ID of the post context.', 'type' => 'integer', 'required' => false],
    ];

    public function __construct(private Schema $schema, private Caller $caller)
    {
    }

    /** The block's markup, as {"rendered": "..."}. */
    #[Route(Method::Get, '/wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+}', policy: new Policy(Access::Public), args: [self::ARGS])]
    #[Route(Method::Post, '/wp/v2/block-renderer/{name:[a-z0-9-]+/[a-z0-9-]+}', policy: new Policy(Access::Public), args: [self::ARGS], body: [self::ARGS])]
    public function render(Request $request, string $name): Response
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
        $values = $request->method === Method::Post ? $request->json() + $request->query : $request->query;
        $type = \WP_Block_Type_Registry::get_instance()->get_registered($name);
        $schema = ['type' => 'object', 'properties' => $type instanceof \WP_Block_Type ? $type->get_attributes() : [], 'additionalProperties' => false];
        $this->refuseInvalid(['context' => [$values['context'] ?? 'view', self::ARGS['context']], 'attributes' => [$values['attributes'] ?? [], $type instanceof \WP_Block_Type ? $schema : self::ARGS['attributes']]]);
        ['post_id' => $postId] = $values + ['post_id' => 0];
        $postId = (int) $postId;
        $this->permit($postId);
        if (!$type instanceof \WP_Block_Type || !$type->is_dynamic()) {
            throw new RestError('block_invalid', 'Invalid block.', 404);
        }
        $attributes = (array) $this->schema->sanitize((array) ($values['attributes'] ?? []), $schema, 'attributes');
        return Reply::item(['rendered' => self::rendered($type, $attributes, $postId)], null);
    }

    /** @param array<string, array{0: mixed, 1: array<string, mixed>}> $args value and schema by name */
    private function refuseInvalid(array $args): void
    {
        $invalid = [];
        $details = [];
        foreach ($args as $name => [$value, $schema]) {
            // The attributes are judged as a value of their own: a bad one is named "[count]", as on the reference.
            $verdict = $this->schema->validate($value, $schema, $name === 'attributes' ? '' : $name);
            if ($verdict instanceof Refusal) {
                $invalid[$name] = $verdict->message;
                $details[$name] = ['code' => $verdict->code, 'message' => $verdict->message, 'data' => $verdict->data];
            }
        }
        if ($invalid !== []) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): ' . implode(', ', array_keys($invalid)), 400, ['params' => $invalid, 'details' => $details]);
        }
    }

    /** Someone who can edit posts may render; with a post named, someone who can edit that post. */
    private function permit(int $postId): void
    {
        if ($postId > 0) {
            if (!\get_post($postId) instanceof \WP_Post || !$this->caller->can('edit_post', $postId)) {
                throw $this->caller->refuse('block_cannot_read', 'Sorry, you are not allowed to read blocks of this post.');
            }
            return;
        }
        if (!$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('block_cannot_read', 'Sorry, you are not allowed to read blocks as this user.');
        }
    }

    /** The block rendered on its own, inside the post when one is named (the global post put back after). @param array<string, mixed> $attributes */
    private static function rendered(\WP_Block_Type $type, array $attributes, int $postId): string
    {
        $outer = $GLOBALS['post'] ?? null;
        $outerRuntime = Runtime::current()->get('post');
        $context = [];
        if ($postId > 0) {
            $GLOBALS['post'] = \get_post($postId);
            \setup_postdata($GLOBALS['post']);
            $context = ['postId' => $postId, 'postType' => \get_post_type($postId)];
        }
        try {
            $block = new \WP_Block(['blockName' => $type->name, 'attrs' => $attributes, 'innerHTML' => '', 'innerContent' => []], $context);
            return (string) $block->render();
        } finally {
            $GLOBALS['post'] = $outer;
            Runtime::current()->set('post', $outerRuntime);
            if ($outer instanceof \WP_Post) {
                \setup_postdata($outer);
            }
        }
    }
}
