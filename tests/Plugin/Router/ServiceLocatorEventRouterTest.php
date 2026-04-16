<?php

/**
 * This file is part of prooph/service-bus.
 * (c) 2014-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2015-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\ServiceBus\Plugin\Router;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Event\DefaultActionEvent;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\Router\ServiceLocatorEventRouter;
use ProophTest\ServiceBus\Mock\MessageHandler;
use Psr\Container\ContainerInterface;

class ServiceLocatorEventRouterTest extends TestCase
{
    /**
     * @test
     */
    public function it_routes()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('has')->with('event')->willReturn(true);
        $container->expects($this->once())->method('get')->with('event')->willReturn(new MessageHandler());

        $eventBus = new EventBus();

        $actionEvent = new DefaultActionEvent(
            MessageBus::EVENT_DISPATCH,
            new EventBus(),
            [
                EventBus::EVENT_PARAM_MESSAGE_NAME => 'event',
            ]
        );

        $router = new ServiceLocatorEventRouter($container);
        $router->attachToMessageBus($eventBus);

        $router->onRouteMessage($actionEvent);

        $this->assertInstanceOf(MessageHandler::class, $actionEvent->getParam(EventBus::EVENT_PARAM_EVENT_LISTENERS));
    }
}
