<?php

declare(strict_types=1);

namespace Notify;

use Notify\Exception\UnsupportedChannelException;
use Notify\Message\Actor\Actor;

abstract class AbstractNotification implements NotificationInterface
{
    /**
     * @var array
     */
    private $messageFactories;

    public function getSupportedChannels() : array
    {
        return $this->getMessageFactoryNames();
    }

    public function getMessage(string $channel, RecipientInterface $recipient)
    {
        $messageFactory = $this->getMessageFactory($channel);
        
        $recipients = [
            new Actor(
                $recipient->getRecipientContact($channel),
                $recipient->getRecipientName()
            ),
        ];

        return $this->$messageFactory($recipients);
    }

    final protected function getMessageFactoryNames()
    {
        $this->initMessageFactories();

        return array_keys($this->messageFactories);
    }

    final protected function getMessageFactory(string $channel)
    {
        $this->initMessageFactories();

        if (! array_key_exists($channel, $this->messageFactories)) {
            throw UnsupportedChannelException::forNotificationAndChannel($this, $channel);
        }

        return $this->messageFactories[$channel];
    }

    final protected function initMessageFactories()
    {
        if (null !== $this->messageFactories) {
            return;
        }

        $this->messageFactories = [];

        foreach (get_class_methods($this) as $methodName) {
            $matches = [];

            if (preg_match('/^create(?P<channel>.+)Message$/', $methodName, $matches)) {
                $channel = strtolower($matches['channel']);
                $this->messageFactories[$channel] = $methodName;
            }
        }
    }
}
