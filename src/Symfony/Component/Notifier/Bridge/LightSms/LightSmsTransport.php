<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LightSms;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class LightSmsTransport extends AbstractTransport
{
    protected const HOST = 'www.lightsms.com';

    private $login;
    private $password;
    private $from;

    private const ERROR_CODES = [
        1 => 'Missing Signature',
        2 => 'Login not specified',
        3 => 'Text not specified',
        4 => 'Phone number not specified',
        5 => 'Sender not specified',
        6 => 'Invalid signature',
        7 => 'Invalid login',
        8 => 'Invalid sender name',
        9 => 'Sender name not registered',
        10 => 'Sender name not approved',
        11 => 'There are forbidden words in the text',
        12 => 'Error in SMS sending',
        13 => 'Phone number is in the blackist. SMS sending to this number is forbidden.',
        14 => 'There are more than 50 numbers in the request',
        15 => 'List not specified',
        16 => 'Invalid phone number',
        17 => 'SMS ID not specified',
        18 => 'Status not obtained',
        19 => 'Empty response',
        20 => 'The number already exists',
        21 => 'No name',
        22 => 'Template already exists',
        23 => 'Missing Month (Format: YYYY-MM)',
        24 => 'Timestamp not specified',
        25 => 'Error in access to the list',
        26 => 'There are no numbers in the list',
        27 => 'No valid numbers',
        28 => 'Missing start date (Format: YYYY-MM-DD)',
        29 => 'Missing end date (Format: YYYY-MM-DD)',
        30 => 'No date (format: YYYY-MM-DD)',
        31 => 'Closing direction to the user',
        32 => 'Not enough money',
        33 => 'Missing phone number',
        34 => 'Phone is in stop list',
        35 => 'Not enough money',
        36 => 'Can not obtain information about phone',
        37 => 'Base Id is not set',
        38 => 'Phone number already exists in this database',
        39 => 'Phone number does not exist in this database',
        999 => 'Unknown Error',
    ];

    public function __construct(string $login, string $password, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->password = $password;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('lightsms://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $timestamp = time();
        $data = [
            'login' => $this->login,
            'phone' => $this->escapePhoneNumber($message->getPhone()),
            'text' => $message->getSubject(),
            'sender' => $this->from,
            'timestamp' => $timestamp,
        ];
        $data['signature'] = $this->generateSignature($data, $timestamp);

        $endpoint = sprintf('https://%s/external/get/send.php', $this->getEndpoint());
        $response = $this->client->request(
            'GET',
            $endpoint,
            [
                'query' => $data,
            ]
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new TransportException('Unable to send the SMS.', $response);
        }

        $content = $response->toArray(false);

        // it happens if the host without www
        if (isset($content['']['error'])) {
            throw new TransportException('Unable to send the SMS: '.$this->getErrorMsg((int) $content['']['error']), $response);
        }

        if (isset($content['error'])) {
            throw new TransportException('Unable to send the SMS: '.$this->getErrorMsg((int) $content['error']), $response);
        }

        $phone = $this->escapePhoneNumber($message->getPhone());
        if (32 === (int) $content[$phone]['error']) {
            throw new TransportException('Unable to send the SMS: '.$this->getErrorMsg((int) $content[$phone]['error']), $response);
        }

        if (0 === (int) $content[$phone]['error']) {
            $sentMessage = new SentMessage($message, (string) $this);
            if (isset($content[$phone]['id_sms'])) {
                $sentMessage->setMessageId($content[$phone]['id_sms']);
            }

            return $sentMessage;
        }

        throw new TransportException('Unable to send the SMS.', $response);
    }

    private function generateSignature(array $data, int $timestamp): string
    {
        $params = [
            'timestamp' => $timestamp,
            'login' => $this->login,
            'phone' => $data['phone'],
            'sender' => $this->from,
            'text' => $data['text'],
        ];

        ksort($params);
        reset($params);

        return md5(implode('', $params).$this->password);
    }

    private function escapePhoneNumber(string $phoneNumber): string
    {
        return str_replace('+', '00', $phoneNumber);
    }

    private function getErrorMsg(int $errorCode): string
    {
        return self::ERROR_CODES[$errorCode] ?? self::ERROR_CODES[999];
    }
}
