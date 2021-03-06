<?php declare(strict_types=1);

namespace IgniTestFunctional\Http;

use Igni\Http\Server;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        self::assertInstanceOf(Server::class, new Server());
    }

    public function testAddingListeners(): void
    {
        // Mock listeners.
        $noopListener = Mockery::mock(Server\Listener::class);
        $onRequestListener = Mockery::mock(Server\OnRequest::class);
        $onConnectListener = Mockery::mock(Server\OnConnect::class);
        $onShutDownListener = Mockery::mock(Server\OnShutdown::class);
        $onStartListener = Mockery::mock(Server\OnStart::class);

        $server = new Server();
        $swoole = $this->initializeSwoole($server);
        $swoole->shouldReceive('on')
            ->with('Request', Mockery::any());
        $swoole->shouldReceive('on')
            ->with('Connect', Mockery::any());
        $swoole->shouldReceive('on')
            ->with('Shutdown', Mockery::any());
        $swoole->shouldReceive('on')
            ->with('Start', Mockery::any());
        $swoole->shouldReceive('set');
        $swoole->shouldReceive('start');
        $server->addListener($noopListener);
        self::assertCount(1, self::readAttribute($server, 'listeners'));
        self::assertTrue($server->hasListener($noopListener));

        $server->addListener($onRequestListener);
        self::assertCount(2, self::readAttribute($server, 'listeners'));
        self::assertTrue($server->hasListener($onRequestListener));

        $server->addListener($onConnectListener);
        $server->addListener($onShutDownListener);
        $server->addListener($onStartListener);
        self::assertCount(5, self::readAttribute($server, 'listeners'));
        $server->start();
    }

    public function testGetClientStats(): void
    {
        $server = new Server();
        $swoole = $this->initializeSwoole($server);
        $swoole->shouldReceive('getClientInfo')
            ->withArgs([1])
            ->andReturn([]);

        self::assertInstanceOf(Server\ClientStats::class, $server->getClientStats(1));
    }

    public function testGetServerStats(): void
    {
        $server = new Server();
        $swoole = $this->initializeSwoole($server);
        $swoole->shouldReceive('stats')
            ->andReturn([]);

        self::assertInstanceOf(Server\ServerStats::class, $server->getServerStats());
    }

    public function testStartWithSsl(): void
    {
        $settings = new Server\HttpConfiguration();
        $settings->enableSsl('a', 'b');
        $server = new Server($settings);
        $swoole = $this->initializeSwoole($server);
        $swoole->shouldReceive('start');
        $swoole->shouldReceive('set')
            ->withArgs(function (array $config) {
                self::assertSame(
                    [
                        'address' => '0.0.0.0',
                        'port' => 80,
                        'ssl_cert_file' => 'a',
                        'ssl_key_file' => 'b',
                    ],
                    $config
                );

                return true;
            });

        self::assertNull($server->start());
    }

    public function testStop(): void
    {
        $server = new Server();
        $swoole = $this->initializeSwoole($server);
        $swoole->shouldReceive('shutdown');

        self::assertNull($server->stop());
    }


    private function initializeSwoole(Server $server): Mockery\MockInterface
    {
        // Replace swoole instance with generic mock object.
        $swoole = Mockery::mock(\stdClass::class);

        $reflectionApi = new \ReflectionClass(Server::class);
        $handlerProperty = $reflectionApi->getProperty('handler');
        $handlerProperty->setAccessible(true);
        $handlerProperty->setValue($server, $swoole);

        return $swoole;
    }
}
