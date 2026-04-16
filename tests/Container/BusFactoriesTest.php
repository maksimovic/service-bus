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

namespace ProophTest\ServiceBus\Factory;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\ServiceBus\Async\AsyncMessage;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\Container\AbstractBusFactory;
use Prooph\ServiceBus\Container\CommandBusFactory;
use Prooph\ServiceBus\Container\EventBusFactory;
use Prooph\ServiceBus\Container\QueryBusFactory;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Exception\InvalidArgumentException;
use Prooph\ServiceBus\Exception\RuntimeException;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\Plugin;
use Prooph\ServiceBus\Plugin\Router\RegexRouter;
use Prooph\ServiceBus\QueryBus;
use ProophTest\ServiceBus\Mock\NoopMessageProducer;
use Psr\Container\ContainerInterface;

class BusFactoriesTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_bus_without_needing_a_application_config(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', false],
            [MessageFactory::class, false],
        ]);

        $bus = $busFactory($container);

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_bus_without_needing_prooph_config(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', []],
        ]);

        $bus = $busFactory($container);

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_new_bus_with_all_plugins_attached_using_a_container_and_configuration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $firstPlugin = $this->createMock(Plugin::class);
        $secondPlugin = $this->createMock(Plugin::class);

        $firstPlugin->expects($this->once())->method('attachToMessageBus')->with($this->isInstanceOf(MessageBus::class));
        $secondPlugin->expects($this->once())->method('attachToMessageBus')->with($this->isInstanceOf(MessageBus::class));

        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturnMap([
            ['config', true],
            ['first_plugin_service_id', true],
            ['second_plugin_service_id', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'plugins' => [
                                'first_plugin_service_id',
                                'second_plugin_service_id',
                            ],
                        ],
                    ],
                ],
            ]],
            ['first_plugin_service_id', $firstPlugin],
            ['second_plugin_service_id', $secondPlugin],
        ]);

        $bus = $busFactory($container);

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_throws_a_runtime_exception_if_plugin_is_not_registered_in_container(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $this->expectException(RuntimeException::class);

        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturnMap([
            ['config', true],
            ['plugin_service_id', false],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'plugins' => [
                                'plugin_service_id',
                            ],
                        ],
                    ],
                ],
            ]],
        ]);

        $busFactory($container);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_bus_with_the_default_router_attached_if_routes_are_configured(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $message = $this->createMock(Message::class);
        $message->method('messageName')->willReturn('test_message');

        $handlerWasCalled = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'routes' => [
                                    'test_message' => function (Message $message) use (&$handlerWasCalled): void {
                                        $handlerWasCalled = true;
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
        ]);

        $bus = $busFactory($container);

        $bus->dispatch($message);

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_bus_and_attaches_the_router_defined_via_configuration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $message = $this->createMock(Message::class);
        $message->method('messageName')->willReturn('test_message');

        $handlerWasCalled = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'type' => RegexRouter::class,
                                'routes' => [
                                    '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                        $handlerWasCalled = true;
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
        ]);

        $bus = $busFactory($container);

        $bus->dispatch($message);

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_creates_a_bus_and_attaches_the_message_factory_defined_via_configuration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $message = $this->createMock(Message::class);
        $messageFactory = $this->createMock(MessageFactory::class);

        $message->method('messageName')->willReturn('test_message');
        $handlerWasCalled = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            ['custom_message_factory', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'type' => RegexRouter::class,
                                'routes' => [
                                    '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                        $handlerWasCalled = true;
                                    },
                                ],
                            ],
                            'message_factory' => 'custom_message_factory',
                        ],
                    ],
                ],
            ]],
            ['custom_message_factory', $messageFactory],
        ]);

        $bus = $busFactory($container);

        $bus->dispatch($message);

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_decorates_router_with_async_switch_and_pulls_async_message_producer_from_container(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $messageProducer = new NoopMessageProducer();

        $message = $this->createMock(AsyncMessage::class);
        $messageFactory = $this->createMock(MessageFactory::class);

        $message->method('messageName')->willReturn('test_message');
        $message->method('metadata')->willReturn([]);
        $message->method('withAddedMetadata')->with('handled-async', true)->willReturn($message);
        $handlerWasCalled = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            ['custom_message_factory', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'async_switch' => 'noop_message_producer',
                                'type' => RegexRouter::class,
                                'routes' => [
                                    '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                        $handlerWasCalled = true;
                                    },
                                ],
                            ],
                            'message_factory' => 'custom_message_factory',
                        ],
                    ],
                ],
            ]],
            ['noop_message_producer', $messageProducer],
            ['custom_message_factory', $messageFactory],
        ]);

        $bus = $busFactory($container);

        $bus->dispatch($message);

        $this->assertFalse($handlerWasCalled);
        $this->assertTrue($messageProducer->isInvoked());
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_enables_handler_location_by_default(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $message = $this->createMock(Message::class);
        $message->expects($this->atLeastOnce())->method('messageName')->willReturn('test_message');

        $handlerWasCalled = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            ['handler_service_id', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'routes' => [
                                    'test_message' => 'handler_service_id',
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            ['handler_service_id', function (Message $message) use (&$handlerWasCalled): void {
                $handlerWasCalled = true;
            }],
        ]);

        $bus = $busFactory($container);

        $bus->dispatch($message);

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_provides_possibility_to_disable_handler_location(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $message = $this->createMock(Message::class);
        $message->method('messageName')->willReturn('test_message');

        $handlerServiceIdLookedUp = false;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id) use (&$handlerServiceIdLookedUp) {
            if ($id === 'handler_service_id') {
                $handlerServiceIdLookedUp = true;
                return false;
            }
            if ($id === 'config') {
                return true;
            }
            if ($id === MessageFactory::class) {
                return false;
            }
            return false;
        });
        $container->method('get')->willReturnMap([
            ['config', [
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'router' => [
                                'routes' => [
                                    'test_message' => 'handler_service_id',
                                ],
                            ],
                            'enable_handler_location' => false,
                        ],
                    ],
                ],
            ]],
        ]);

        $bus = $busFactory($container);

        $bus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );

        $bus->dispatch($message);

        $this->assertFalse($handlerServiceIdLookedUp, 'handler_service_id should not be looked up when handler location is disabled');
    }

    /**
     * @test
     * @dataProvider provideBuses
     */
    public function it_can_handle_application_config_being_of_type_array_access(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $firstPlugin = $this->createMock(Plugin::class);
        $firstPlugin->expects($this->once())->method('attachToMessageBus')->with($this->isInstanceOf(MessageBus::class));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            ['first_plugin_service_id', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', new \ArrayObject([
                'prooph' => [
                    'service_bus' => [
                        $busConfigKey => [
                            'plugins' => [
                                'first_plugin_service_id',
                            ],
                        ],
                    ],
                ],
            ])],
            ['first_plugin_service_id', $firstPlugin],
        ]);

        $bus = $busFactory($container);

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @test
     * @dataProvider provideBusFactoryClasses
     */
    public function it_creates_a_bus_from_static_call(string $busClass, string $busFactoryClass): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['config', true],
            [MessageFactory::class, false],
        ]);
        $container->method('get')->willReturnMap([
            ['config', []],
        ]);

        $factory = [$busFactoryClass, 'other_config_id'];
        $this->assertInstanceOf($busClass, $factory($container));
    }

    /**
     * @test
     */
    public function it_throws_invalid_argument_exception_without_container_on_static_call(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The first argument must be of type Psr\Container\ContainerInterface');

        CommandBusFactory::other_config_id();
    }

    public static function provideBusFactoryClasses(): array
    {
        return [
            [CommandBus::class, CommandBusFactory::class],
            [EventBus::class, EventBusFactory::class],
            [QueryBus::class, QueryBusFactory::class],
        ];
    }

    public static function provideBuses(): array
    {
        return [
            [CommandBus::class, 'command_bus', new CommandBusFactory()],
            [EventBus::class, 'event_bus', new EventBusFactory()],
            [QueryBus::class, 'query_bus', new QueryBusFactory()],
        ];
    }
}
