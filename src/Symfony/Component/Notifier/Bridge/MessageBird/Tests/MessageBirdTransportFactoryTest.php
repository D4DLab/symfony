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
            'messagebird://:authToken@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'messagebird://authToken@default?from=0611223344'];
        yield [false, 'somethingElse://authToken@default?from=0611223344'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['messagebird://:authToken@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authToken@default?from=0611223344'];
        yield ['somethingElse://authToken@default']; // missing "from" option
    }
}
