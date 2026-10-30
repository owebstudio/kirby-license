<?php

namespace Oweb\License\Tests;

use Kirby\Cms\App;
use Oweb\License\Host;
use PHPUnit\Framework\Attributes\DataProvider;

class HostTest extends TestCase
{
	public static function domainProvider(): array
	{
		return [
			['https://example.com', 'example.com'],
			['https://www.example.com', 'example.com'],
			['https://staging.example.com', 'example.com'],
			['https://dev.example.com', 'example.com'],
			['https://test.example.com', 'example.com'],
			['https://shop.example.com', 'shop.example.com'],
			['https://Example.COM/', 'example.com'],
			['https://example.com:8080', 'example.com:8080'],
			['https://www.example.com/shop', 'www.example.com/shop'],
			['http://example.com/shop/', 'example.com/shop'],
		];
	}

	#[DataProvider('domainProvider')]
	public function testDomain(string $url, string $domain): void
	{
		$host = new Host($this->app($url));
		$this->assertSame($domain, $host->domain());
	}

	public function testDomainWithoutUrl(): void
	{
		$host = new Host($this->app(null));
		$this->assertSame('', $host->domain());
		$this->assertSame('', $host->host());
		$this->assertFalse($host->isExempt());
	}

	public static function exemptProvider(): array
	{
		return [
			['http://localhost', true],
			['http://localhost:8000', true],
			['http://site.localhost', true],
			['http://127.0.0.1', true],
			['http://[::1]', true],
			['http://example.test', true],
			['http://example.local', true],
			['http://example.ddev.site', true],
			['https://staging.example.com', true],
			['https://dev.example.com', true],
			['https://test.example.com', true],
			['https://example.com', false],
			['https://www.example.com', false],
			['https://stagingexample.com', false],
			['https://example.com.test.org', false],
			['https://example.dev', false],
		];
	}

	#[DataProvider('exemptProvider')]
	public function testIsExempt(string $url, bool $exempt): void
	{
		$host = new Host($this->app($url));
		$this->assertSame($exempt, $host->isExempt());
	}

	public function testIsExemptDisabledByOption(): void
	{
		$host = new Host($this->app('http://example.test', [
			'options' => ['owebstudio.license.exempt' => false]
		]));

		$this->assertFalse($host->isExempt());
	}

	public function testIsStagingFromOption(): void
	{
		$options = [
			'owebstudio.license.staging' => ['preview.client.com', '*.client.dev']
		];

		$this->assertTrue((new Host($this->app('https://preview.client.com', ['options' => $options])))->isStaging());
		App::destroy();
		$this->assertTrue((new Host($this->app('https://a.b.client.dev', ['options' => $options])))->isStaging());
		App::destroy();
		$this->assertFalse((new Host($this->app('https://client.dev', ['options' => $options])))->isStaging());
		App::destroy();
		$this->assertFalse((new Host($this->app('https://client.com', ['options' => $options])))->isStaging());
	}

	public function testMatches(): void
	{
		$this->assertTrue(Host::matches('preview.example.com', 'preview.example.com'));
		$this->assertTrue(Host::matches('preview.example.com', 'PREVIEW.example.com'));
		$this->assertTrue(Host::matches('a.example.com', '*.example.com'));
		$this->assertFalse(Host::matches('example.com', '*.example.com'));
		$this->assertFalse(Host::matches('aexample.com', '*.example.com'));
		$this->assertFalse(Host::matches('example.com', ''));
		$this->assertFalse(Host::matches('exampleXcom', 'example.com'));
	}
}
