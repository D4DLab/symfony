<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier;

use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\FailoverTransport;
use Symfony\Component\Notifier\Transport\NullTransportFactory;
use Symfony\Component\Notifier\Transport\RoundRobinTransport;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Component\Notifier\Transport\Transports;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Transport
{
    private const FACTORY_CLASSES = [
        SlackTransportFactory::class,
        TelegramTransportFactory::class,
        MattermostTransportFactory::class,
        NexmoTransportFactory::class,
        IqsmsTransportFactory::class,
        RocketChatTransportFactory::class,
        TwilioTransportFactory::class,
        AllMySmsTransportFactory::class,
        InfobipTransportFactory::class,
        OvhCloudTransportFactory::class,
        FirebaseTransportFactory::class,
        SinchTransportFactory::class,
        FreeMobileTransportFactory::class,
        ZulipTransportFactory::class,
        MobytTransportFactory::class,
        SmsapiTransportFactory::class,
        EsendexTransportFactory::class,
        SendinblueTransportFactory::class,
        DiscordTransportFactory::class,
        GatewayApiTransportFactory::class,
        OctopushTransportFactory::class,
        GitterTransportFactory::class,
        ClickatellTransportFactory::class,
        LightSmsTransportFactory::class,
    ];

    private $factories;

    public static function fromDsn(string $dsn, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null): TransportInterface
    {
        $factory = new self(self::getDefaultFactories($dispatcher, $client));

        return $factory->fromString($dsn);
    }

    public static function fromDsns(array $dsns, EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null): TransportInterface
    {
        $factory = new self(iterator_to_array(self::getDefaultFactories($dispatcher, $client)));

        return $factory->fromStrings($dsns);
    }

    /**
     * @param TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function fromStrings(array $dsns): Transports
    {
        $transports = [];
        foreach ($dsns as $name => $dsn) {
            $transports[$name] = $this->fromString($dsn);
        }

        return new Transports($transports);
    }

    public function fromString(string $dsn): TransportInterface
    {
        $dsns = preg_split('/\s++\|\|\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new FailoverTransport($this->createFromDsns($dsns));
        }

        $dsns = preg_split('/\s++&&\s++/', $dsn);
        if (\count($dsns) > 1) {
            return new RoundRobinTransport($this->createFromDsns($dsns));
        }

        return $this->fromDsnObject(new Dsn($dsn));
    }

    public function fromDsnObject(Dsn $dsn): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /**
     * @return TransportInterface[]
     */
    private function createFromDsns(array $dsns): array
    {
        $transports = [];
        foreach ($dsns as $dsn) {
            $transports[] = $this->fromDsnObject(new Dsn($dsn));
        }

        return $transports;
    }

    /**
     * @return TransportFactoryInterface[]
     */
    private static function getDefaultFactories(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null): iterable
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass($dispatcher, $client);
            }
        }

        yield new NullTransportFactory($dispatcher, $client);
    }
}
