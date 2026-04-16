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

namespace ProophTest\ServiceBus\Plugin;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Event\ActionEvent;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Plugin\Router\EventRouter;
use Prooph\ServiceBus\Plugin\ServiceLocatorPlugin;
use ProophTest\ServiceBus\Mock\MessageHandler;
use ProophTest\ServiceBus\Mock\SomethingDone;
use Psr\Container\ContainerInterface;

class ServiceLocatorPluginTest extends TestCase
{
    /**
     * @test
     */
    public function it_locates_a_service_using_the_message_handler_param_of_the_action_event(): void
    {
        $handler = new MessageHandler();

        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->atLeastOnce())->method('has')->with('custom-handler')->willReturn(true);
        $container->expects($this->atLeastOnce())->method('get')->with('custom-handler')->willReturn($handler);

        $commandBus = new CommandBus();

        $locatorPlugin = new ServiceLocatorPlugin($container);
        $locatorPlugin->attachToMessageBus($commandBus);

        $commandBus->attach(
            CommandBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $actionEvent->setParam(CommandBus::EVENT_PARAM_MESSAGE_HANDLER, 'custom-handler');
            },
            CommandBus::PRIORITY_INITIALIZE
        );

        $commandBus->dispatch('foo');
    }

    /**
     * @test
     */
    public function it_doesnt_override_previous_event_handlers(): void
    {
        $handledOne = false;

        $handlerOne = function (SomethingDone $event) use (&$handledOne): void {
            $handledOne = true;
        };

        $handlerTwo = new MessageHandler();

        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->atLeastOnce())->method('has')->with('custom-handler')->willReturn(true);
        $container->expects($this->atLeastOnce())->method('get')->with('custom-handler')->willReturn($handlerTwo);

        $eventBus = new EventBus();

        $router = new EventRouter();
        $router->route(SomethingDone::class)->to($handlerOne)->andTo('custom-handler');

        $router->attachToMessageBus($eventBus);

        $locatorPlugin = new ServiceLocatorPlugin($container);

        $locatorPlugin->attachToMessageBus($eventBus);

        $eventBus->dispatch(new SomethingDone(['foo' => 'bar']));

        $this->assertTrue($handledOne);
        $this->assertSame(1, $handlerTwo->getInvokeCounter());
    }

    /**
     * @test
     */
    public function make_sure_servicenames_do_not_end_up_as_listener_instance(): void
    {
        $handlerOne = new MessageHandler();
        $handlerTwo = new MessageHandler();
        $eventBus = new EventBus();
        $router = new EventRouter();

        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturnMap([
            ['handler-one', true],
            ['handler-two', true],
        ]);
        $container->method('get')->willReturnMap([
            ['handler-one', $handlerOne],
            ['handler-two', $handlerTwo],
        ]);

        $router->route(SomethingDone::class)->to('handler-one');
        $router->route(SomethingDone::class)->to('handler-two');

        $router->attachToMessageBus($eventBus);

        $locatorPlugin = new ServiceLocatorPlugin($container);

        $locatorPlugin->attachToMessageBus($eventBus);

        $eventBus->attach(EventBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $listeners = $actionEvent->getParam(EventBus::EVENT_PARAM_EVENT_LISTENERS);

                $this->assertCount(2, $listeners);
                $this->assertContainsOnly(MessageHandler::class, $listeners);
            }, PHP_INT_MIN);

        $eventBus->dispatch(new SomethingDone(['foo' => 'bar']));
    }
}
