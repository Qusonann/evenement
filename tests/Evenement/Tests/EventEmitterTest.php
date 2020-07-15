<?php

/** @noinspection UnusedFunctionResultInspection */
declare(strict_types=1);

/*
 * This file is part of Evenement.
 *
 * (c) Igor Wiedler <igor@wiedler.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Evenement\Tests;

use Evenement\EventEmitter;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TypeError;
use Evenement\Tests\Listener;

class EventEmitterTest extends TestCase
{
    /**
     * @var EventEmitter
     */
    private $emitter;

    public function setUp(): void
    {
        $this->emitter = new EventEmitter();
    }

    public function testAddListenerWithLambda(): void
    {
        $this->emitter->on('foo', static function () {});

        self::assertCount(1, $this->emitter->listeners('foo'));
    }

    public function testAddListenerWithMethod(): void
    {
        $listener = new Listener();
        $this->emitter->on('foo', [$listener, 'onFoo']);

        self::assertCount(1, $this->emitter->listeners('foo'));
    }

    public function testAddListenerWithStaticMethod(): void
    {
        $this->emitter->on('bar', [Listener::class, 'onBar']);

        $this->assertCount(1, $this->emitter->listeners('bar'));
    }

    public function testOnce(): void
    {
        $listenerCalled = 0;

        $this->emitter->once('foo', function () use (&$listenerCalled) {
            $listenerCalled++;
        });

        $this->assertSame(0, $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame(1, $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame(1, $listenerCalled);
    }

    public function testOnceWithArguments(): void
    {
        $capturedArgs = [];

        $this->emitter->once('foo', function ($a, $b) use (&$capturedArgs) {
            $capturedArgs = array($a, $b);
        });

        $this->emitter->emit('foo', array('a', 'b'));

        self::assertSame(array('a', 'b'), $capturedArgs);
    }

    public function testOncePre(): void
    {
        $listenerCalled = [];

        $this->emitter->onceBefore('foo', function () use (&$listenerCalled) {
            $this->assertSame([], $listenerCalled);
            $listenerCalled[] = 1;
        });

        $this->emitter->on('foo', function () use (&$listenerCalled) {
            $this->assertSame([1], $listenerCalled);
            $listenerCalled[] = 2;
        });

        $this->emitter->once('foo', function () use (&$listenerCalled) {
            $this->assertSame([1, 2], $listenerCalled);
            $listenerCalled[] = 3;
        });

        $this->assertSame([], $listenerCalled);

        $this->emitter->emit('foo');

        $this->assertSame([1, 2, 3], $listenerCalled);
    }

    public function testEmitWithoutArguments(): void
    {
        $listenerCalled = false;

        $this->emitter->on('foo', function () use (&$listenerCalled) {
            $listenerCalled = true;
        });

        $this->assertSame(false, $listenerCalled);
        $this->emitter->emit('foo');
        $this->assertSame(true, $listenerCalled);
    }

    public function testEmitWithOneArgument(): void
    {
        $test = $this;

        $listenerCalled = false;

        $this->emitter->on('foo', function ($value) use (&$listenerCalled, $test) {
            $listenerCalled = true;

            $test->assertSame('bar', $value);
        });

        self::assertFalse($listenerCalled);
        $this->emitter->emit('foo', ['bar']);
        self::assertTrue($listenerCalled);
    }

    public function testEmitWithTwoArguments(): void
    {
        $test = $this;

        $listenerCalled = false;

        $this->emitter->on('foo', static function ($arg1, $arg2) use (&$listenerCalled, $test) {
            $listenerCalled = true;

            $test->assertSame('bar', $arg1);
            $test->assertSame('baz', $arg2);
        });

        self::assertFalse($listenerCalled);
        $this->emitter->emit('foo', ['bar', 'baz']);
        self::assertTrue($listenerCalled);
    }

    public function testEmitWithTwoListeners(): void
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', static function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->on('foo', static function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(2, $listenersCalled);
    }

    public function testRemoveListenerMatching(): void
    {
        $listenersCalled = 0;

        $listener = function () use (&$listenersCalled) {
            $listenersCalled++;
        };

        $this->emitter->on('foo', $listener);
        $this->emitter->removeListener('foo', $listener);

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(0, $listenersCalled);
    }

    public function testRemoveListenerNotMatching(): void
    {
        $listenersCalled = 0;

        $listener = function () use (&$listenersCalled) {
            $listenersCalled++;
        };

        $this->emitter->on('foo', $listener);
        $this->emitter->removeListener('bar', $listener);

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(1, $listenersCalled);
    }

    public function testRemoveAllListenersMatching(): void
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners('foo');

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(0, $listenersCalled);
    }

    public function testRemoveAllListenersNotMatching(): void
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners('bar');

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->assertSame(1, $listenersCalled);
    }

    public function testRemoveAllListenersWithoutArguments(): void
    {
        $listenersCalled = 0;

        $this->emitter->on('foo', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->on('bar', function () use (&$listenersCalled) {
            $listenersCalled++;
        });

        $this->emitter->removeAllListeners();

        $this->assertSame(0, $listenersCalled);
        $this->emitter->emit('foo');
        $this->emitter->emit('bar');
        $this->assertSame(0, $listenersCalled);
    }

    public function testCallablesClosure(): void
    {
        $calledWith = null;

        $this->emitter->on('foo', function ($data) use (&$calledWith) {
            $calledWith = $data;
        });

        $this->emitter->emit('foo', ['bar']);

        self::assertSame('bar', $calledWith);
    }

    public function testCallablesClass(): void
    {
        $listener = new Listener();
        $this->emitter->on('foo', [$listener, 'onFoo']);

        $this->emitter->emit('foo', ['bar']);

        self::assertSame(['bar'], $listener->getData());
    }


    public function testCallablesClassInvoke(): void
    {
        $listener = new Listener();
        $this->emitter->on('foo', $listener);

        $this->emitter->emit('foo', ['bar']);

        self::assertSame(['bar'], $listener->getMagicData());
    }

    public function testCallablesStaticClass(): void
    {
        $this->emitter->on('foo', '\Evenement\Tests\Listener::onBar');

        $this->emitter->emit('foo', ['bar']);

        self::assertSame(['bar'], Listener::getStaticData());
    }

    public function testCallablesFunction(): void
    {
        $this->emitter->on('foo', '\Evenement\Tests\setGlobalTestData');

        $this->emitter->emit('foo', ['bar']);

        self::assertSame('bar', $GLOBALS['evenement-evenement-test-data']);

        unset($GLOBALS['evenement-evenement-test-data']);
    }

    public function testListeners(): void
    {
        $onA = function () {};
        $onB = function () {};
        $onC = function () {};
        $onceA = function () {};
        $onceB = function () {};
        $onceC = function () {};

        self::assertCount(0, $this->emitter->listeners('event'));
        $this->emitter->on('event', $onA);
        self::assertCount(1, $this->emitter->listeners('event'));
        self::assertSame([$onA], $this->emitter->listeners('event'));
        $this->emitter->once('event', $onceA);
        self::assertCount(2, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onceA], $this->emitter->listeners('event'));
        $this->emitter->once('event', $onceB);
        self::assertCount(3, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onceA, $onceB], $this->emitter->listeners('event'));
        $this->emitter->on('event', $onB);
        self::assertCount(4, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onB, $onceA, $onceB], $this->emitter->listeners('event'));
        $this->emitter->removeListener('event', $onceA);
        self::assertCount(3, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onB, $onceB], $this->emitter->listeners('event'));
        $this->emitter->once('event', $onceC);
        self::assertCount(4, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onB, $onceB, $onceC], $this->emitter->listeners('event'));
        $this->emitter->on('event', $onC);
        self::assertCount(5, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onB, $onC, $onceB, $onceC], $this->emitter->listeners('event'));
        $this->emitter->once('event', $onceA);
        self::assertCount(6, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onB, $onC, $onceB, $onceC, $onceA], $this->emitter->listeners('event'));
        $this->emitter->removeListener('event', $onB);
        self::assertCount(5, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onC, $onceB, $onceC, $onceA], $this->emitter->listeners('event'));
        $this->emitter->emit('event');
        self::assertCount(2, $this->emitter->listeners('event'));
        self::assertSame([$onA, $onC], $this->emitter->listeners('event'));
    }

    public function testOnceCallIsNotRemovedWhenWorkingOverOnceListeners(): void
    {
        $aCalled = false;
        $aCallable = function () use (&$aCalled) {
            $aCalled = true;
        };
        $bCalled = false;
        $bCallable = function () use (&$bCalled, $aCallable) {
            $bCalled = true;
            $this->emitter->once('event', $aCallable);
        };
        $this->emitter->once('event', $bCallable);

        self::assertFalse($aCalled);
        self::assertFalse($bCalled);
        $this->emitter->emit('event');

        self::assertFalse($aCalled);
        self::assertTrue($bCalled);
        $this->emitter->emit('event');

        self::assertTrue($aCalled);
        self::assertTrue($bCalled);
    }

    public function testEventNameMustBeStringOn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event name must not be null');

        $this->emitter->on(null, function () {
        });
    }

    public function testEventNameMustBeStringOnce(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event name must not be null');

        $this->emitter->once(null, function () {});
    }

    public function testEventNameMustBeStringRemoveListener(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event name must not be null');

        $this->emitter->removeListener(null, function () {});
    }

    public function testEventNameMustBeStringEmit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('event name must not be null');

        $this->emitter->emit(null);
    }

    public function testListenersGetAll(): void
    {
        $a = function () {};
        $b = function () {};
        $c = function () {};
        $d = function () {};

        $this->emitter->once('event2', $c);
        $this->emitter->on('event', $a);
        $this->emitter->once('event', $b);
        $this->emitter->on('event', $c);
        $this->emitter->once('event', $d);

        self::assertSame(
            [
                'event' => [
                    $a,
                    $c,
                    $b,
                    $d,
                ],
                'event2' => [
                    $c,
                ],
            ],
            $this->emitter->listeners()
        );
    }

    public function testOnceNestedCallRegression(): void
    {
        $first = 0;
        $second = 0;

        $this->emitter->once('event', function () use (&$first, &$second) {
            $first++;
            $this->emitter->once('event', function () use (&$second) {
                $second++;
            });
            $this->emitter->emit('event');
        });
        $this->emitter->emit('event');

        self::assertSame(1, $first);
        self::assertSame(1, $second);
    }

    public function testInheritance(): void
    {
        $child = new EventEmitter();
        $this->emitter->forward($child);
        $child->on('hello', function ($data) {
            self::assertSame('hello from parent', $data);
        });
        $this->emitter->emit('hello', ['hello from parent']);
    }

    public function testOff(): void
    {
        self::assertSame([], $this->emitter->listeners());

        $listener = function () {
        };
        $this->emitter->on('event', $listener);
        $this->emitter->on('tneve', $listener);
        self::assertSame(
            [
                'event' => [
                    $listener,
                ],
                'tneve' => [
                    $listener,
                ],
            ],
            $this->emitter->listeners()
        );

        $this->emitter->off('tneve', $listener);
        self::assertSame(
            [
                'event' => [
                    $listener,
                ],
            ],
            $this->emitter->listeners()
        );

        $this->emitter->off('event');
        self::assertSame([], $this->emitter->listeners());
    }

    public function testNestedOn(): void
    {
        $emitter = $this->emitter;

        $first = 0;
        $second = 0;
        $third = 0;

        $emitter->on('event', function () use (&$emitter, &$first, &$second, &$third) {
            $first++;

            $emitter->on('event', function () use (&$second, &$third) {
                $second++;
            })
                ->once('event', function () use (&$third) {
                    $third++;
                });
        });

        $emitter->emit('event');
        $this->assertEquals(1, $first);
        $this->assertEquals(0, $second);
        $this->assertEquals(0, $third);
        $emitter->emit('event');
        $this->assertEquals(2, $first);
        $this->assertEquals(1, $second);
        $this->assertEquals(1, $third);
    }

    public function testEventNames(): void
    {
        $emitter = $this->emitter;

        $emitter->on('event1', function () {});
        $emitter->on('event2', function () {});
        $emitter->once('event3', function () {});

        $this->assertEquals(['event1', 'event2', 'event3'], $emitter->eventNames());
    }
}
