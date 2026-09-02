# Contract: abilities (`wp-abilities/v1`)

Status: implemented. Suite: `tests/abilities.test.php` (16 checks, every one
a diff against the reference). Fixtures:
`contracts/fixtures/rest/abilities.json`, `ability-categories.json`.

An ability is a named unit of work a site can be asked to do: a label, a
description, a category, an input schema, an output schema, a permission
callback and an execute callback. The reference has served this namespace
since core 6.9, and it is what an AI client reads first, so the engine
speaks it rather than inventing its own.

## Routes

| Route | Method | Answers |
|---|---|---|
| `/wp-abilities/v1/abilities` | GET | every registered ability; `?category=` narrows |
| `/wp-abilities/v1/abilities/{name}` | GET | one, by its `namespace/name` |
| `/wp-abilities/v1/abilities/{name}/run` | GET or POST | runs it and returns its output |
| `/wp-abilities/v1/categories` | GET | every category |
| `/wp-abilities/v1/categories/{slug}` | GET | one category |

## Who may

- Reading the catalogue needs a session and nothing more: an author sees the
  same list an administrator sees. Anonymous is `rest_forbidden` at 401.
- Running one is the ability's own decision, through its
  `permission_callback`; a refusal is `rest_ability_cannot_execute` at 403.
  An author asking to run `core/get-site-info` is refused, since reading a
  site's settings wants `manage_options`.
- A name nobody registered is `rest_ability_not_found` at 404, a category
  `rest_ability_category_not_found`.

## The method a run takes

An ability whose `meta.annotations.readonly` is true runs on **GET**, with
its input in the `input` query parameter (`input[fields][0]=name`), because
running it changes nothing. Anything else runs on **POST** with `{"input":
{...}}` as the body. The wrong method is `rest_ability_invalid_method` at
405, worded as the reference words it ("Read-only abilities require GET
method.").

## What a plain site carries

The three the reference registers in core, in two categories (`site`,
`user`), with their labels, descriptions and schemas captured:

- `core/get-site-info` (manage_options): name, description, url, wpurl,
  admin_email, charset, language, version.
- `core/get-user-info` (any signed-in caller, their own profile): id,
  display_name, user_nicename, user_login, roles, locale, first_name,
  last_name, nickname, description, user_url.
- `core/get-environment-info` (manage_options): environment, php_version,
  db_server_info, wp_version.

Each takes an optional `fields` list and returns every field when it is
absent. Registrations live in `wp-api/defaults/abilities.php`; a plugin adds
its own with `wp_register_ability()` exactly as on the reference.

## Why this matters

The WordPress MCP adapter is built on this namespace. An engine that serves
it serves that adapter unmodified, which is the whole of the
agent-compatibility play: the client ecosystem is inherited through the
interface rather than rebuilt.

## Known gaps

- `output_schema` is published but not enforced on the way out, and
  `input_schema` is not validated before a callback runs; the reference
  validates both. `Rest\Schema` already has the validator this needs.
- The catalogue is not paged, since core registers three abilities and a
  site with hundreds is not a shape anyone has yet.
