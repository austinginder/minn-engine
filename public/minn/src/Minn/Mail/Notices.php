<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The messages the engine itself sends, worded as the reference words them
 * and built in one place, so a controller and a CLI verb that announce the
 * same thing say the same thing.
 */
final readonly class Notices
{
    public function __construct(private string $siteName, private string $home)
    {
    }

    /** A new account's login details, with a link to choose a password. */
    public function loginDetails(string $login, string $email, string $resetLink): Message
    {
        return Message::to($email, $this->subject('Login Details'), <<<TEXT
            Username: {$login}

            To set your password, visit the following address:

            {$resetLink}

            {$this->home}/wp-login.php

            TEXT);
    }

    /** The password reset link, with the requester's address as the reference prints it. */
    public function passwordReset(string $login, string $email, string $resetLink, string $requestIp): Message
    {
        return Message::to($email, $this->subject('Password Reset'), <<<TEXT
            Someone has requested a password reset for the following account:

            Site Name: {$this->siteName}

            Username: {$login}

            If this was a mistake, ignore this email and nothing will happen.

            To reset your password, visit the following address:

            {$resetLink}

            This password reset request originated from the IP address {$requestIp}.

            TEXT);
    }

    /** A comment waiting in the queue, announced to the site's address. */
    public function moderation(string $adminEmail, string $postTitle, string $author, string $comment): Message
    {
        $text = strip_tags($comment);
        return Message::to($adminEmail, $this->subject('Please moderate: "' . $postTitle . '"'), <<<TEXT
            A new comment on the post "{$postTitle}" is waiting for your approval.

            Author: {$author}
            Comment:
            {$text}

            Moderate it in the admin:
            {$this->home}/minn-admin/

            TEXT);
    }

    /** Every notice the reference sends carries the site's name in brackets. */
    private function subject(string $subject): string
    {
        return '[' . $this->siteName . '] ' . $subject;
    }
}
