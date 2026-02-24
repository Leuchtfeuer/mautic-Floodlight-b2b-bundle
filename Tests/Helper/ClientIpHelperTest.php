<?php

namespace MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Tests\Helper;

use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Helper\ClientIpHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ClientIpHelperTest extends TestCase
{
    private ClientIpHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new ClientIpHelper();
    }

    public function testGetClientIpFromRemoteAddr(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $this->assertSame('192.168.1.1', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromCfConnectingIp(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('CF-Connecting-IP', '203.0.113.10');

        $this->assertSame('203.0.113.10', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromTrueClientIp(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('True-Client-IP', '203.0.113.20');

        $this->assertSame('203.0.113.20', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromFastlyClientIp(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Fastly-Client-IP', '203.0.113.30');

        $this->assertSame('203.0.113.30', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromXRealIp(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('X-Real-IP', '203.0.113.40');

        $this->assertSame('203.0.113.40', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromXForwardedFor(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('X-Forwarded-For', '203.0.113.50');

        $this->assertSame('203.0.113.50', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromXForwardedForMultiple(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('X-Forwarded-For', '203.0.113.10, 198.51.100.5, 192.0.2.7');

        // Should return the first IP (original client)
        $this->assertSame('203.0.113.10', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromForwardedHeader(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Forwarded', 'for=203.0.113.60; proto=https; by=proxy.example.com');

        $this->assertSame('203.0.113.60', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromForwardedHeaderMultiple(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Forwarded', 'for=203.0.113.10, for=198.51.100.5, for=192.0.2.7');

        // Should return the first IP (original client)
        $this->assertSame('203.0.113.10', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromForwardedHeaderWithQuotes(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Forwarded', 'for="203.0.113.70"; proto=https');

        $this->assertSame('203.0.113.70', $this->helper->getClientIp($request));
    }

    public function testGetClientIpFromForwardedHeaderWithIpv6(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Forwarded', 'for="[2001:db8::1]"; proto=https');

        $this->assertSame('2001:db8::1', $this->helper->getClientIp($request));
    }

    public function testHeaderPrecedence(): void
    {
        // CF-Connecting-IP should take precedence over all others
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('CF-Connecting-IP', '203.0.113.10');
        $request->headers->set('True-Client-IP', '203.0.113.20');
        $request->headers->set('X-Forwarded-For', '203.0.113.30');
        $request->headers->set('REMOTE_ADDR', '10.0.0.1');

        $this->assertSame('203.0.113.10', $this->helper->getClientIp($request));
    }

    public function testTrueClientIpPrecedenceOverXForwardedFor(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('True-Client-IP', '203.0.113.20');
        $request->headers->set('X-Forwarded-For', '203.0.113.30');

        $this->assertSame('203.0.113.20', $this->helper->getClientIp($request));
    }

    public function testForwardedPrecedenceOverXForwardedFor(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        $request->headers->set('Forwarded', 'for=203.0.113.50');
        $request->headers->set('X-Forwarded-For', '203.0.113.60');

        $this->assertSame('203.0.113.50', $this->helper->getClientIp($request));
    }

    public function testReturnsNullWhenRemoteAddrIsNotString(): void
    {
        $request = Request::create('/', 'GET');
        // Explicitly set REMOTE_ADDR to null to simulate the edge case
        $request->server->set('REMOTE_ADDR', null);

        $this->assertNull($this->helper->getClientIp($request));
    }

    public function testHandlesEmptyHeaders(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);
        $request->headers->set('X-Forwarded-For', '');
        $request->headers->set('CF-Connecting-IP', '');

        // Should fall back to REMOTE_ADDR
        $this->assertSame('192.168.1.1', $this->helper->getClientIp($request));
    }
}
