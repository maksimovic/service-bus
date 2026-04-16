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

namespace ProophTest\ServiceBus\Plugin\Guard;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Event\ActionEvent;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Exception\MessageDispatchException;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\Plugin\Guard\AuthorizationService;
use Prooph\ServiceBus\Plugin\Guard\FinalizeGuard;
use Prooph\ServiceBus\Plugin\Guard\UnauthorizedException;
use Prooph\ServiceBus\QueryBus;

class FinalizeGuardTest extends TestCase
{
    /**
     * @var MessageBus
     */
    protected $messageBus;

    protected function setUp(): void
    {
        $this->messageBus = new QueryBus();
    }

    /**
     * @test
     */
    public function it_allows_when_authorization_service_grants_access_without_deferred(): void
    {
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->expects($this->once())->method('isGranted')->with('test_event')->willReturn(true);

        $messageBus = new EventBus();

        $routeGuard = new FinalizeGuard($authorizationService);
        $routeGuard->attachToMessageBus($messageBus);

        $messageBus->dispatch('test_event');
    }

    /**
     * @test
     */
    public function it_allows_when_authorization_service_grants_access_with_deferred(): void
    {
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->expects($this->once())->method('isGranted')->with('test_event', 'result')->willReturn(true);

        $routeGuard = new FinalizeGuard($authorizationService);
        $routeGuard->attachToMessageBus($this->messageBus);

        $this->messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $deferred = $actionEvent->getParam(QueryBus::EVENT_PARAM_DEFERRED);
                $deferred->resolve('result');
                $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            QueryBus::PRIORITY_LOCATE_HANDLER + 1000
        );

        $promise = $this->messageBus->dispatch('test_event');
        $promise->then(function ($result) {
            $this->assertNotNull($result);
            $this->assertEquals('result', $result);
        });
    }

    /**
     * @test
     */
    public function it_stops_propagation_and_throws_unauthorizedexception_when_authorization_service_denies_access_without_deferred(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('You are not authorized to access this resource');

        $this->messageBus = new EventBus();

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('isGranted')->with('test_event')->willReturn(false);

        $routeGuard = new FinalizeGuard($authorizationService);
        $routeGuard->attachToMessageBus($this->messageBus);

        $this->messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            QueryBus::PRIORITY_LOCATE_HANDLER + 1000
        );

        try {
            $promise = $this->messageBus->dispatch('test_event');
            $promise->done();
        } catch (MessageDispatchException $exception) {
            throw $exception->getPrevious();
        }
    }

    /**
     * @test
     */
    public function it_stops_propagation_and_throws_unauthorizedexception_when_authorization_service_denies_access_with_deferred(): void
    {
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('isGranted')->with('test_event', 'result')->willReturn(false);

        $routeGuard = new FinalizeGuard($authorizationService);
        $routeGuard->attachToMessageBus($this->messageBus);

        $this->messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $deferred = $actionEvent->getParam(QueryBus::EVENT_PARAM_DEFERRED);
                $deferred->resolve('result');
                $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            QueryBus::PRIORITY_LOCATE_HANDLER + 1000
        );

        $caughtException = null;
        $promise = $this->messageBus->dispatch('test_event');
        $promise->catch(function (\Throwable $e) use (&$caughtException): void {
            $caughtException = $e instanceof MessageDispatchException && $e->getPrevious() !== null
                ? $e->getPrevious()
                : $e;
        });

        $this->assertInstanceOf(UnauthorizedException::class, $caughtException);
        $this->assertEquals('You are not authorized to access this resource', $caughtException->getMessage());
    }

    /**
     * @test
     */
    public function it_stops_propagation_and_throws_unauthorizedexception_when_authorization_service_denies_access_without_deferred_and_exposes_message_name(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('You are not authorized to access the resource "test_event"');

        $this->messageBus = new EventBus();

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('isGranted')->with('test_event')->willReturn(false);

        $routeGuard = new FinalizeGuard($authorizationService, true);
        $routeGuard->attachToMessageBus($this->messageBus);

        $this->messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            QueryBus::PRIORITY_LOCATE_HANDLER + 1000
        );

        try {
            $promise = $this->messageBus->dispatch('test_event');
            $promise->done();
        } catch (MessageDispatchException $exception) {
            throw $exception->getPrevious();
        }
    }

    /**
     * @test
     */
    public function it_stops_propagation_and_throws_unauthorizedexception_when_authorization_service_denies_access_with_deferred_and_exposes_message_name(): void
    {
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService->method('isGranted')->with('test_event', 'result')->willReturn(false);

        $finalizeGuard = new FinalizeGuard($authorizationService, true);
        $finalizeGuard->attachToMessageBus($this->messageBus);

        $this->messageBus->attach(
            QueryBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $deferred = $actionEvent->getParam(QueryBus::EVENT_PARAM_DEFERRED);
                $deferred->resolve('result');
                $actionEvent->setParam(QueryBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            QueryBus::PRIORITY_LOCATE_HANDLER + 1000
        );

        $caughtException = null;
        $promise = $this->messageBus->dispatch('test_event');
        $promise->catch(function (\Throwable $e) use (&$caughtException): void {
            $caughtException = $e;
        });

        $this->assertInstanceOf(UnauthorizedException::class, $caughtException);
        $this->assertEquals('You are not authorized to access the resource "test_event"', $caughtException->getMessage());
    }
}
