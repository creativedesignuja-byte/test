<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/proxy-test', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'host' => $request->getHost(),
        ]);
    }

    public function test_configured_proxy_preserves_https_and_client_ip_without_trusting_forwarded_host(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
            'HTTP_X_FORWARDED_HOST' => 'untrusted.example',
        ])->getJson('http://rasta.example/proxy-test')->assertExactJson([
            'secure' => true,
            'ip' => '198.51.100.20',
            'host' => 'rasta.example',
        ]);
    }

    public function test_untrusted_sender_cannot_spoof_https_or_client_ip(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
        ])->getJson('http://rasta.example/proxy-test')->assertExactJson([
            'secure' => false,
            'ip' => '203.0.113.7',
            'host' => 'rasta.example',
        ]);
    }
}
