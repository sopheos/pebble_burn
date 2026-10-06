<?php

use Pebble\Burn\Services;
use PHPUnit\Framework\TestCase;

class AppServices extends Services
{
    public int $stopped = 0;

    public function stop()
    {
        $this->stopped++;
    }
}

class OtherServices extends Services
{
}

class ServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        AppServices::destroy();
        OtherServices::destroy();
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public function testGetInstanceIsOnePerSubclass()
    {
        self::assertSame(AppServices::getInstance(), AppServices::getInstance());
        self::assertNotSame(AppServices::getInstance(), OtherServices::getInstance());
    }

    public function testDestroyDropsTheInstance()
    {
        $first = AppServices::getInstance();
        AppServices::destroy();

        self::assertNotSame($first, AppServices::getInstance());
    }

    public function testStopIsCalledOnDestruct()
    {
        $services = new AppServices();
        $services->__destruct();

        self::assertSame(1, $services->stopped);
    }

    public function testGetInstanceOnTheAbstractClassFails()
    {
        $this->expectException(Error::class);

        Services::getInstance();
    }

    // -------------------------------------------------------------------------
    // Config & env
    // -------------------------------------------------------------------------

    public function testConfigReturnsNullForUnknownKeys()
    {
        $services = AppServices::getInstance()->setConfig(['a' => 1]);

        self::assertSame(1, $services->config('a'));
        self::assertNull($services->config('b'));
    }

    public function testSetConfigReplacesTheWholeArray()
    {
        $services = AppServices::getInstance()->setConfig(['a' => 1])->setConfig(['b' => 2]);

        self::assertNull($services->config('a'));
    }

    public function testEnvThrowsWhenNotDefined()
    {
        $this->expectException(InvalidArgumentException::class);

        AppServices::getInstance()->isProd();
    }

    public function testEnvHelpers()
    {
        $services = AppServices::getInstance()->setEnv(Services::ENV_DEV);

        self::assertSame('developpement', $services->env());
        self::assertTrue($services->isDev());
        self::assertFalse($services->isProd());
        self::assertFalse($services->isTest());
    }

    public function testPathIsAPlainConcatenation()
    {
        $services = AppServices::getInstance();

        self::assertSame('/var/app', $services->path('/var/app'));

        $services->setConfig(['path' => '/var/app']);
        self::assertSame('/var/appconfig', $services->path('config'));
    }
}
