<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird\Tests;

use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class MessageBirdTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return MessageBirdTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new MessageBirdTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'messagebird://host.test?from=0611223344',
            'messagebird://accountSid:authToken@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'messagebird://accountSid:authToken@default?from=0611223344'];
        yield [false, 'somethingElse://accountSid:authToken@default?from=0611223344'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['messagebird://accountSid:authToken@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@default?from=0611223344'];
        yield ['somethingElse://accountSid:authToken@default']; // missing "from" option
    }
}
