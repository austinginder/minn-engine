<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/** Languages: what is installed, what a person reads in, what the site defaults to. */
final readonly class LanguageController
{
    public function __construct(
        private Translations $translations,
        private Users $users,
        private Site $site,
        private Capabilities $capabilities,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/minn-admin/v1/languages')]
    public function languages(Request $request): Response
    {
        $self = $this->caller->requireFloor();
        $for = (int) ($request->query('user') ?? 0);
        if ($for > 0 && ($for === $self || !$this->caller->can('edit_users'))) {
            $for = 0;
        }
        return Reply::answer($request, $this->translations->payload($for > 0 ? $for : $self, $this->caller->can('manage_options')));
    }

    /** The slice of the boot payload a language switch repaints from. */
    #[Route(Method::Get, '/minn-admin/v1/boot-locale')]
    public function bootLocale(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        $locale = $this->translations->localeOf($userId);
        [$i18n, $plural] = $this->translations->catalog($locale);
        return Reply::answer($request, [
            'i18n' => $i18n === [] ? new \stdClass() : $i18n,
            'i18nPlural' => $plural,
            'languages' => $this->translations->installed(),
            'userRole' => $this->capabilities->roles()->all()[$this->capabilities->rolesOf($userId)[0] ?? '']['name'] ?? '',
            'locale' => $locale,
            'rtl' => Translations::isRtl($locale),
        ]);
    }

    #[Route(Method::Post, '/minn-admin/v1/me/language')]
    public function mine(Request $request): Response
    {
        return $this->setUserLocale($request, $this->caller->requireFloor());
    }

    #[Route(Method::Post, '/minn-admin/v1/users/{id:\d+}/language')]
    public function user(Request $request, string $id): Response
    {
        $self = $this->caller->requireFloor();
        if ((int) $id !== $self && !$this->caller->can('edit_users')) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
        if ($this->users->find((int) $id) === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        return $this->setUserLocale($request, (int) $id);
    }

    #[Route(Method::Post, '/minn-admin/v1/site/language')]
    public function site(Request $request): Response
    {
        $this->caller->requireFloor();
        $this->caller->requireCap('manage_options');
        [$locale, $downloaded] = $this->ensure($request);
        $this->site->setOption('WPLANG', $locale === 'en_US' ? '' : $locale);
        return Reply::answer($request, ['ok' => true, 'locale' => $locale === 'en_US' ? '' : $locale, 'installed' => $downloaded]);
    }

    private function setUserLocale(Request $request, int $userId): Response
    {
        [$locale, $downloaded] = $this->ensure($request);
        if ($locale === '') {
            $this->users->deleteMeta($userId, 'locale');
        } else {
            $this->users->setMeta($userId, 'locale', $locale);
        }
        return Reply::answer($request, ['ok' => true, 'locale' => $locale, 'installed' => $downloaded]);
    }

    /** The requested locale, installed if it was not; '' means the site default. @return array{0: string, 1: bool} */
    private function ensure(Request $request): array
    {
        $locale = trim((string) ($request->json()['locale'] ?? $request->form['locale'] ?? ''));
        if ($locale === '' || $locale === 'en_US' || $this->translations->isInstalled($locale)) {
            return [$locale, false];
        }
        if (!$this->caller->can('manage_options')) {
            throw new RestError('minn_language_install', 'That language is not installed, and you cannot install languages on this site.', 403);
        }
        if (!$this->translations->install($locale)) {
            throw new RestError('minn_language_install', 'Minn Admin has no language pack for that locale.', 404);
        }
        return [$locale, true];
    }

}
