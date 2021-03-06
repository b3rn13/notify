<?php

/*
 * This file is part of the Notify package.
 *
 * Copyright (c) Nikola Posa <posa.nikola@gmail.com>
 *
 * For full copyright and license information, please refer to the LICENSE file,
 * located at the package root folder.
 */

namespace Notify\Example;

use Notify\NotificationInterface;
use Notify\RecipientInterface;
use Notify\AbstractNotification;
use Notify\Message\EmailMessage;
use Notify\Message\SMSMessage;
use Notify\Recipients;
use Notify\Notifier;
use Notify\Tests\TestAsset\Message\TestMessageSender;

require_once __DIR__ . '/../vendor/autoload.php';

final class User implements RecipientInterface
{
    private $username;

    private $firstName;

    private $lastName;

    private $email;

    private $phoneNumber;

    public function __construct(
        $username,
        $firstName,
        $lastName,
        $email,
        $phoneNumber
    ) {
        $this->username = $username;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getFirstName()
    {
        return $this->firstName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    public function getRecipientContact(string $channel) : string
    {
        switch ($channel) {
            case 'email':
                return $this->email;
            case 'sms':
                return $this->phoneNumber;
            default:
                throw new \RuntimeException(sprintf(
                    'User does not accept notifications through %s channel',
                    $channel
                ));
        }
    }

    public function getRecipientName() : string
    {
        return $this->username;
    }

    public function shouldReceive(NotificationInterface $notification, string $channel) : bool
    {
        return in_array($channel, ['email', 'sms'], true);
    }
}

final class Post
{
    private $title;

    private $content;

    private $author;

    private $comments;

    public function __construct(
        $title,
        $content,
        User $author,
        array $comments = []
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->author = $author;
        $this->comments = $comments;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function comment(Comment $comment)
    {
        $this->comments[] = $comment;
    }
}

final class Comment
{
    private $authorName;

    private $authorEmail;

    private $content;

    public function __construct(
        $authorName,
        $authorEmail,
        $content
    ) {
        $this->authorName = $authorName;
        $this->authorEmail = $authorEmail;
        $this->content = $content;
    }

    public function getAuthorName()
    {
        return $this->authorName;
    }

    public function getAuthorEmail()
    {
        return $this->authorEmail;
    }

    public function getContent()
    {
        return $this->content;
    }
}

final class NewCommentNotification extends AbstractNotification
{
    private $post;

    private $comment;

    public function __construct(Post $post, Comment $comment)
    {
        $this->post = $post;
        $this->comment = $comment;
    }

    protected function createEmailMessage(array $recipients)
    {
        return new EmailMessage(
            $recipients,
            'New comment',
            sprintf('%s left a new comment on your "%s" blog post', $this->comment->getAuthorName(), $this->post->getTitle())
        );
    }

    protected function createSmsMessage(array $recipients)
    {
        return new SMSMessage(
            $recipients,
            sprintf('You have a new comment on your "%s" blog post', $this->post->getTitle())
        );
    }
}

$author = new User('admin', 'John', 'Doe', 'jd@example.com', '+12222222222');
$post = new Post('Lorem Ipsum', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.', $author);

$comment = new Comment('Jane', 'jane@example.com', 'Nice article!');
$post->comment($comment);

$defaultMessageSender = new TestMessageSender();

$notifyStrategy = new Notifier([
    'email' => $defaultMessageSender,
    'sms' => $defaultMessageSender,
]);

$newCommentNotification = new NewCommentNotification($post, $comment);

$notifyStrategy->notify(
    Recipients::fromArray([
        $author
    ]),
    $newCommentNotification
);

foreach ($defaultMessageSender->getMessages() as $message) {
    echo get_class($message) . ': ';
    echo $message->getContent();
    echo "\n\n";
}
