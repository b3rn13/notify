<?php

declare(strict_types=1);

namespace Notify\Tests\Message\Sender;

use PHPUnit\Framework\TestCase;
use Notify\Message\Sender\PlivoSMS;
use GuzzleHttp\ClientInterface;
use Notify\Message\SMSMessage;
use Notify\Message\Actor\Actor;
use Notify\Tests\TestAsset\Message\DummyMessage;
use GuzzleHttp\Psr7\Response;
use Notify\Message\Sender\Exception\UnsupportedMessageException;
use Notify\Message\Sender\Exception\IncompleteMessageException;
use Notify\Message\Sender\Exception\RuntimeException;

class PlivoSMSTest extends TestCase
{
    private function getPlivoSMS(ClientInterface $httpClient = null)
    {
        return new PlivoSMS('token', 'id', $httpClient);
    }

    private function getHttpClientWithSuccessResponse(SMSMessage $message)
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('POST'),
                $this->callback(function ($url) {
                    return preg_match('[' . PlivoSMS::API_BASE_URL . '|id]', $url);
                }),
                $this->callback(function ($options) use ($message) {
                    if (!is_array($options)) {
                        return false;
                    }

                    if (!isset($options['auth'])) {
                        return false;
                    }

                    if (!isset($options['json'])) {
                        return false;
                    }

                    if (!isset($options['json']['src'], $options['json']['dst'], $options['json']['text'])) {
                        return false;
                    }

                    if ($options['json']['src'] !== $message->getFrom()->getContact()) {
                        return false;
                    }

                    if ($options['json']['dst'] !== $message->getTo()->getContact()) {
                        return false;
                    }

                    if ($options['json']['text'] != $message->getText()) {
                        return false;
                    }

                    return true;
                })
            )
            ->will($this->returnValue(new Response(202)));

        return $httpClient;
    }

    private function getHttpClientWithErrorResponse()
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->will($this->returnValue(new Response(403)));

        return $httpClient;
    }

    public function testSendSuccess()
    {
        $message = new SMSMessage(
            new Actor('+12222222222'),
            'test test test',
            new Actor('+11111111111')
        );

        $this->getPlivoSMS($this->getHttpClientWithSuccessResponse($message))->send($message);
    }

    public function testSendResultsInError()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMS not sent');

        $message = new SMSMessage(
            new Actor('+12222222222'),
            'test test test',
            new Actor('+11111111111')
        );

        $this->getPlivoSMS($this->getHttpClientWithErrorResponse())->send($message);
    }

    public function testExceptionIsRaisedInCaseOfUnsupportedMessageType()
    {
        $this->expectException(UnsupportedMessageException::class);

        $message = new DummyMessage(
            [
                new Actor('+12222222222')
            ],
            'test test test'
        );

        $this->getPlivoSMS()->send($message);
    }

    public function testExceptionIsRaisedIfMessageSenderIsMissing()
    {
        $this->expectException(IncompleteMessageException::class);
        $this->expectExceptionMessage('Message sender is missing');

        $message = new SMSMessage(
            new Actor('+12222222222'),
            'test test test'
        );

        $this->getPlivoSMS()->send($message);
    }
}
