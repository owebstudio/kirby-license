<?php

namespace Oweb\License\Tests;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\LogicException;
use Kirby\Exception\PermissionException;
use Oweb\License\Dialog;

class DialogTest extends TestCase
{
	protected function dialog(): Dialog
	{
		return new Dialog($this->license());
	}

	protected function load(string|null $user = 'admin@example.com'): array
	{
		if ($user !== null) {
			App::instance()->impersonate($user);
		}

		return $this->dialog()->load();
	}

	public function testLoadMissing(): void
	{
		$this->app();
		$dialog = $this->load();

		$this->assertSame('k-form-dialog', $dialog['component']);
		$this->assertSame(['domain', 'key'], array_keys($dialog['props']['fields']));
		$this->assertStringContainsString('<strong>example.com</strong>', (string)$dialog['props']['fields']['domain']['text']);
		$this->assertSame('text', $dialog['props']['fields']['key']['type']);
		$this->assertTrue($dialog['props']['fields']['key']['required']);
		$this->assertStringContainsString('href="https://owebstudio.com/test"', (string)$dialog['props']['fields']['key']['help']);
		$this->assertSame('Activate', $dialog['props']['submitButton']['text']);
		$this->assertNull($dialog['props']['value']['key']);
	}

	public function testLoadMissingForEditor(): void
	{
		$this->app();
		$dialog = $this->load('editor@example.com');

		$this->assertSame(['admin'], array_keys($dialog['props']['fields']));
		$this->assertFalse($dialog['props']['submitButton']);
	}

	public function testLoadInvalid(): void
	{
		$this->app();
		$this->store(['valid' => false, 'error' => 'disabled']);

		$dialog = $this->load();

		$this->assertSame(['invalid', 'domain', 'key'], array_keys($dialog['props']['fields']));
		$this->assertSame('negative', $dialog['props']['fields']['invalid']['theme']);
		$this->assertStringContainsString('The license key has been disabled.', (string)$dialog['props']['fields']['invalid']['text']);
	}

	public function testLoadInvalidWithApiError(): void
	{
		$this->app();
		$this->store(['valid' => false, 'error' => '<b>instance</b> not found']);

		$dialog = $this->load();

		$this->assertStringContainsString('&lt;b&gt;instance&lt;/b&gt; not found', (string)$dialog['props']['fields']['invalid']['text']);
	}

	public function testLoadOtherDomain(): void
	{
		$this->app('https://new-domain.com');
		$this->store(['domain' => 'old-domain.com']);

		$dialog = $this->load();

		$this->assertSame(['domain', 'other', 'key'], array_keys($dialog['props']['fields']));
		$this->assertStringContainsString('old-domain.com', (string)$dialog['props']['fields']['other']['text']);
		$this->assertSame(static::KEY, $dialog['props']['value']['key']);
	}

	public function testLoadOtherDomainForEditor(): void
	{
		$this->app('https://new-domain.com');
		$this->store(['domain' => 'old-domain.com']);

		$dialog = $this->load('editor@example.com');

		// the key is never sent to non-admins
		$this->assertNull($dialog['props']['value']['key']);
		$this->assertStringNotContainsString(static::KEY, json_encode($dialog));
	}

	public function testLoadActive(): void
	{
		$this->app();
		$this->store(['productName' => '<b>Instagram Feed</b>', 'variantName' => 'Regular']);

		$dialog = $this->load();
		$text   = (string)$dialog['props']['fields']['license']['text'];

		$this->assertSame(['license', 'deactivate'], array_keys($dialog['props']['fields']));
		$this->assertStringContainsString('<strong>Test Plugin</strong> is activated for <strong>example.com</strong>.', $text);
		$this->assertStringContainsString('Product: &lt;b&gt;Instagram Feed&lt;/b&gt; (Regular)', $text);
		$this->assertStringContainsString('License key: 38b1…4d51', $text);
		$this->assertStringNotContainsString(static::KEY, json_encode($dialog));
		$this->assertSame('toggle', $dialog['props']['fields']['deactivate']['type']);
		$this->assertTrue($dialog['props']['fields']['deactivate']['required']);
		$this->assertSame('Deactivate', $dialog['props']['submitButton']['text']);
		$this->assertSame('negative', $dialog['props']['submitButton']['theme']);
	}

	public function testLoadActiveForEditor(): void
	{
		$this->app();
		$this->store([]);

		$dialog = $this->load('editor@example.com');

		$this->assertSame(['license'], array_keys($dialog['props']['fields']));
		$this->assertFalse($dialog['props']['submitButton']);
	}

	public function testLoadExempt(): void
	{
		$this->app('http://example.test');

		$dialog = $this->load();

		$this->assertSame(['exempt'], array_keys($dialog['props']['fields']));
		$this->assertStringContainsString('<strong>example.test</strong>', (string)$dialog['props']['fields']['exempt']['text']);
		$this->assertFalse($dialog['props']['submitButton']);
	}

	public function testLoadRefreshes(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		$this->respond($this->body(value: false, status: 'disabled'));

		$dialog = $this->load();

		$this->assertCount(1, $this->requests);
		$this->assertArrayHasKey('invalid', $dialog['props']['fields']);
	}

	public function testSubmitActivate(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['key' => static::KEY]]
		]);
		App::instance()->impersonate('admin@example.com');

		$this->respond($this->body());
		$this->respond($this->body('activated'));

		$this->assertSame(['message' => 'The license has been activated'], $this->dialog()->submit());
		$this->assertSame(['validate', 'activate'], array_column($this->requests, 'action'));
	}

	public function testSubmitActivateError(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['key' => '']]
		]);
		App::instance()->impersonate('admin@example.com');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Please enter your license key.');

		$this->dialog()->submit();
	}

	public function testSubmitAsEditor(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['key' => static::KEY]]
		]);
		App::instance()->impersonate('editor@example.com');

		$this->expectException(PermissionException::class);
		$this->dialog()->submit();
	}

	public function testSubmitWithoutUser(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['key' => static::KEY]]
		]);

		$this->expectException(PermissionException::class);
		$this->dialog()->submit();
	}

	public function testSubmitDeactivate(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['deactivate' => true]]
		]);
		App::instance()->impersonate('admin@example.com');
		$this->store([]);
		$this->respond($this->body('deactivated'));

		$this->assertSame(['message' => 'The license has been deactivated'], $this->dialog()->submit());
		$this->assertSame([], $this->stored()['activations']);
	}

	public function testSubmitDeactivateWithoutConfirmation(): void
	{
		$this->app('https://example.com', [
			'request' => ['body' => ['deactivate' => false]]
		]);
		App::instance()->impersonate('admin@example.com');
		$this->store([]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Please confirm the deactivation.');

		$this->dialog()->submit();
	}

	public function testSubmitExempt(): void
	{
		$this->app('http://example.test', [
			'request' => ['body' => ['key' => static::KEY]]
		]);
		App::instance()->impersonate('admin@example.com');

		$this->expectException(LogicException::class);
		$this->dialog()->submit();
	}

	public function testRoutesSurviveRebinding(): void
	{
		$this->app();
		App::instance()->impersonate('admin@example.com');

		$routes = $this->dialog()->routes();
		$route  = $routes['oweb-license/owebstudio/test'];

		// Kirby calls route actions with `Closure::call($route)`
		$dialog = $route['load']->call(new class () {});

		$this->assertSame('k-form-dialog', $dialog['component']);
	}

	public function testTrusted(): void
	{
		$html = Dialog::trusted('<b>a</b>');

		$this->assertSame('<b>a</b>', (string)$html);

		if (class_exists('Kirby\Toolkit\HtmlString') === true) {
			$this->assertInstanceOf('Kirby\Toolkit\HtmlString', $html);
		} else {
			$this->assertIsString($html);
		}
	}
}
