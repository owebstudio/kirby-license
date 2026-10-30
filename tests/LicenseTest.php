<?php

namespace Oweb\License\Tests;

use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Plugin\License as PluginLicense;
use Kirby\Plugin\Plugin;
use Oweb\License\Activation;
use Oweb\License\License;
use Oweb\License\Status;
use Oweb\License\Storage;
use RuntimeException;

class LicenseTest extends TestCase
{
	public function testStatusMissing(): void
	{
		$this->app();
		$license = $this->license();

		$this->assertSame(Status::Missing, $license->status());
		$this->assertTrue($license->isLocked());
		$this->assertFalse($license->isLicensed());
		$this->assertNull($license->activation());
		$this->assertSame([], $this->requests);
	}

	public function testStatusExempt(): void
	{
		$this->app('http://example.test');
		$license = $this->license();

		$this->assertSame(Status::Exempt, $license->status());
		$this->assertFalse($license->isLocked());
		$this->assertTrue($license->isLicensed());
		$this->assertNull($license->message());
	}

	public function testStatusActive(): void
	{
		$this->app();
		$this->store([]);

		$license = $this->license();

		$this->assertSame(Status::Active, $license->status());
		$this->assertFalse($license->isLocked());
		$this->assertNull($license->message());
		$this->assertSame([], $this->requests);
	}

	public function testStatusActiveOnStagingSubdomain(): void
	{
		$this->app('https://staging.example.com');
		$this->store([]);

		$this->assertSame(Status::Active, $this->license()->status());
	}

	public function testStatusActiveForBundle(): void
	{
		$this->app();
		$this->store(['product' => static::BUNDLE]);

		$this->assertSame(Status::Active, $this->license([100])->status());
		$this->assertSame(Status::Active, $this->license([200], 'owebstudio/other')->status());
	}

	public function testStatusIgnoresOtherProducts(): void
	{
		$this->app();
		$this->store(['product' => 200]);

		$this->assertSame(Status::Missing, $this->license([100])->status());
		$this->assertSame(Status::Active, $this->license([200], 'owebstudio/other')->status());
	}

	public function testStatusIgnoresOtherStores(): void
	{
		$this->app();
		$this->store(['store' => 2]);

		$this->assertSame(Status::Missing, $this->license()->status());
	}

	public function testStatusIgnoresOtherDomains(): void
	{
		$this->app('https://new-domain.com');
		$this->store([]);

		$this->assertSame(Status::Missing, $this->license()->status());
	}

	public function testStatusWithoutDomain(): void
	{
		// CLI without the `url` option: any domain counts
		$this->app(null);
		$this->store(['domain' => 'some-other-site.com']);

		$this->assertSame(Status::Active, $this->license()->status());
	}

	public function testStatusInvalid(): void
	{
		$this->app();
		$this->store(['valid' => false, 'error' => 'disabled']);

		$license = $this->license();

		$this->assertSame(Status::Invalid, $license->status());
		$this->assertTrue($license->isLocked());
	}

	public function testStatusPrefersValidActivation(): void
	{
		$this->app();
		$this->store(
			['valid' => false, 'key' => 'old-key'],
			['product' => static::BUNDLE]
		);

		$license = $this->license();

		$this->assertSame(Status::Active, $license->status());
		$this->assertSame(static::KEY, $license->activation()->key);
	}

	public function testStatusExemptBeatsInvalid(): void
	{
		$this->app('http://example.test');
		$this->store(['domain' => 'example.test', 'valid' => false]);

		$this->assertSame(Status::Exempt, $this->license()->status());
	}

	public function testStatusWithBrokenFile(): void
	{
		$this->app();
		file_put_contents($this->file(), 'nope');

		$this->assertSame(Status::Missing, $this->license()->status());
	}

	public function testDefaultStoreAndBundle(): void
	{
		$license = new License(plugin: 'owebstudio/test', products: [15537]);

		$this->assertSame(8696, $license->store());
		$this->assertSame([15537, 15623], $license->products());
		$this->assertTrue($license->accepts(8696, 15537));
		$this->assertTrue($license->accepts(8696, 15623));
		$this->assertFalse($license->accepts(8696, 15546));
		$this->assertFalse($license->accepts(1, 15537));
		$this->assertFalse($license->accepts(null, 15537));
	}

	public function testActivate(): void
	{
		$this->app();
		$this->respond($this->body('valid', instance: null));
		$this->respond($this->body('activated', instance: 'new-instance'));

		$license    = $this->license();
		$activation = $license->activate('  ' . static::KEY . ' ');

		$this->assertSame('new-instance', $activation->instance);
		$this->assertSame(Status::Active, $license->status());

		$this->assertSame('validate', $this->requests[0]['action']);
		$this->assertSame(['license_key' => static::KEY], $this->requests[0]['data']);
		$this->assertSame('activate', $this->requests[1]['action']);
		$this->assertSame(['license_key' => static::KEY, 'instance_name' => 'example.com'], $this->requests[1]['data']);

		$stored = $this->stored()['activations'][0];
		$this->assertSame(static::KEY, $stored['key']);
		$this->assertSame('example.com', $stored['domain']);
		$this->assertSame('new-instance', $stored['instance']);
		$this->assertSame(1, $stored['store']);
		$this->assertSame(100, $stored['product']);
		$this->assertSame('Product 100', $stored['productName']);
		$this->assertSame('Regular', $stored['variantName']);
		$this->assertTrue($stored['valid']);
		$this->assertNotNull($stored['validatedAt']);
	}

	public function testActivateUsesNormalizedDomain(): void
	{
		$this->app('https://www.example.com');
		$this->respond($this->body());
		$this->respond($this->body('activated'));

		$this->license()->activate(static::KEY);

		$this->assertSame('example.com', $this->requests[1]['data']['instance_name']);
	}

	public function testActivateReusesActivation(): void
	{
		$this->app();
		$this->store(['product' => static::BUNDLE]);

		// a second plugin with the same bundle key
		$license    = $this->license([200], 'owebstudio/other');
		$activation = $license->activate(static::KEY);

		$this->assertSame('instance-1', $activation->instance);
		$this->assertSame([], $this->requests);
		$this->assertCount(1, $this->stored()['activations']);
	}

	public function testActivateReusedKeyForOtherProduct(): void
	{
		$this->app();
		$this->store(['product' => 200]);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The license key is not valid.');

		$this->license([100])->activate(static::KEY);
	}

	public function testActivateBundleKey(): void
	{
		$this->app();
		$this->respond($this->body(product: static::BUNDLE));
		$this->respond($this->body('activated', product: static::BUNDLE));

		$this->license([100])->activate(static::KEY);

		// every plugin is unlocked with one activation
		$this->assertSame(Status::Active, $this->license([200], 'owebstudio/other')->status());
		$this->assertCount(2, $this->requests);
	}

	public function testStatusSeesChangesOfOtherPlugins(): void
	{
		$this->app();
		$this->respond($this->body(product: static::BUNDLE));
		$this->respond($this->body('activated', product: static::BUNDLE));
		$this->respond($this->body('deactivated', product: static::BUNDLE));

		$a = $this->license([100], 'owebstudio/a');
		$b = $this->license([200], 'owebstudio/b');

		$this->assertSame(Status::Missing, $a->status());
		$this->assertSame(Status::Missing, $b->status());

		$a->activate(static::KEY);
		$this->assertSame(Status::Active, $b->status());

		// in the same request
		$b->deactivate();
		$this->assertSame(Status::Missing, $a->status());
	}

	public function testActivateWrongProduct(): void
	{
		$this->app();
		$this->respond($this->body(product: 300));

		try {
			$this->license([100])->activate(static::KEY);
			$this->fail('No exception');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('The license key is not valid.', $e->getMessage());
		}

		// no activation used
		$this->assertCount(1, $this->requests);
		$this->assertFileDoesNotExist($this->file());
	}

	public function testActivateWrongStore(): void
	{
		$this->app();
		$this->respond($this->body(store: 2));

		try {
			$this->license()->activate(static::KEY);
			$this->fail('No exception');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('The license key is not valid.', $e->getMessage());
		}

		$this->assertCount(1, $this->requests);
	}

	public function testActivateUnknownKey(): void
	{
		$this->app();
		$this->respond([
			'valid'       => false,
			'error'       => 'license_key not found.',
			'license_key' => null,
			'meta'        => null
		], 404);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The license key is not valid.');

		$this->license()->activate('unknown');
	}

	public function testActivateDisabledKey(): void
	{
		$this->app();
		$this->respond($this->body(value: false, status: 'disabled'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The license key has been disabled.');

		$this->license()->activate(static::KEY);
	}

	public function testActivateExpiredKey(): void
	{
		$this->app();
		$this->respond($this->body(value: false, status: 'expired'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The license key has expired.');

		$this->license()->activate(static::KEY);
	}

	public function testActivateLimitReached(): void
	{
		$this->app();
		$this->respond($this->body());
		$this->respond($this->body('activated', false, error: 'This license key has reached the activation limit.', instance: null), 400);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The license could not be activated: This license key has reached the activation limit.');

		$this->license()->activate(static::KEY);
	}

	public function testActivateOffline(): void
	{
		$this->app();
		$this->respondWith(new RuntimeException('Connection timed out'));

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The license server could not be reached. Please try again later.');

		$this->license()->activate(static::KEY);
	}

	public function testActivateEmptyKey(): void
	{
		$this->app();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Please enter your license key.');

		$this->license()->activate('   ');
	}

	public function testActivateWithoutDomain(): void
	{
		$this->app(null);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The domain of the site could not be detected.');

		$this->license()->activate(static::KEY);
	}

	public function testActivateGivesActivationBackIfFileCannotBeWritten(): void
	{
		$this->app();
		mkdir($this->file());

		$this->respond($this->body());
		$this->respond($this->body('activated', instance: 'new-instance'));
		$this->respond($this->body('deactivated'));

		try {
			$this->license()->activate(static::KEY);
			$this->fail('No exception');
		} catch (Exception $e) {
			$this->assertStringContainsString('could not be saved', $e->getMessage());
		}

		$this->assertSame('deactivate', $this->requests[2]['action']);
		$this->assertSame('new-instance', $this->requests[2]['data']['instance_id']);
	}

	public function testActivateReplacesInvalidActivation(): void
	{
		$this->app();
		$this->store(['valid' => false, 'instance' => 'old-instance', 'error' => 'license_key instance not found.']);

		$this->respond($this->body());
		$this->respond($this->body('deactivated', false), 404);
		$this->respond($this->body('activated', instance: 'new-instance'));

		$license = $this->license();
		$license->activate(static::KEY);

		$this->assertSame(['validate', 'deactivate', 'activate'], array_column($this->requests, 'action'));
		$this->assertSame('old-instance', $this->requests[1]['data']['instance_id']);

		$stored = $this->stored()['activations'];
		$this->assertCount(1, $stored);
		$this->assertSame('new-instance', $stored[0]['instance']);
		$this->assertSame(Status::Active, $license->status());
	}

	public function testDeactivate(): void
	{
		$this->app();
		$this->store([], ['domain' => 'other.com']);
		$this->respond($this->body('deactivated'));

		$license = $this->license();
		$license->deactivate();

		$this->assertSame('deactivate', $this->requests[0]['action']);
		$this->assertSame(['license_key' => static::KEY, 'instance_id' => 'instance-1'], $this->requests[0]['data']);
		$this->assertSame(Status::Missing, $license->status());

		$stored = $this->stored()['activations'];
		$this->assertCount(1, $stored);
		$this->assertSame('other.com', $stored[0]['domain']);
	}

	public function testDeactivateUnknownInstance(): void
	{
		$this->app();
		$this->store([]);
		$this->respond(['deactivated' => false, 'error' => 'license_key instance not found.'], 404);

		$license = $this->license();
		$license->deactivate();

		$this->assertSame(Status::Missing, $license->status());
	}

	public function testDeactivateOffline(): void
	{
		$this->app();
		$this->store([]);
		$this->respond('', 503);

		$license = $this->license();

		try {
			$license->deactivate();
			$this->fail('No exception');
		} catch (Exception $e) {
			$this->assertSame('The license server could not be reached. Please try again later.', $e->getMessage());
		}

		$this->assertSame(Status::Active, $license->status());
	}

	public function testDeactivateMissing(): void
	{
		$this->app();

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('There is no license to deactivate.');

		$this->license()->deactivate();
	}

	public function testRefreshNotDue(): void
	{
		$this->app();
		$this->store([]);

		$this->license()->refresh();

		$this->assertSame([], $this->requests);
	}

	public function testRefresh(): void
	{
		$this->app();
		$this->store([
			'validatedAt' => date(DATE_ATOM, time() - Activation::LIFETIME - 60),
			'checkedAt'   => date(DATE_ATOM, time() - Activation::LIFETIME - 60)
		]);
		$this->respond($this->body());

		$license = $this->license();
		$license->refresh();

		$this->assertSame('validate', $this->requests[0]['action']);
		$this->assertSame(['license_key' => static::KEY, 'instance_id' => 'instance-1'], $this->requests[0]['data']);

		$stored = Activation::fromArray($this->stored()['activations'][0]);
		$this->assertTrue($stored->valid);
		$this->assertEqualsWithDelta(time(), $stored->validatedAt, 5);
		$this->assertSame(Status::Active, $license->status());

		// only once per request
		$license->refresh();
		$this->assertCount(1, $this->requests);
	}

	public function testRefreshForce(): void
	{
		$this->app();
		$this->store([]);
		$this->respond($this->body());

		$this->license()->refresh(force: true);

		$this->assertCount(1, $this->requests);
	}

	public function testRefreshOfflineKeepsState(): void
	{
		$this->app();
		$validated = time() - Activation::LIFETIME - 60;

		$this->store([
			'validatedAt' => date(DATE_ATOM, $validated),
			'checkedAt'   => date(DATE_ATOM, $validated)
		]);
		$this->respond('', 500);

		$license = $this->license();
		$license->refresh();

		$stored = Activation::fromArray($this->stored()['activations'][0]);
		$this->assertTrue($stored->valid);
		$this->assertSame($validated, $stored->validatedAt);
		$this->assertEqualsWithDelta(time(), $stored->checkedAt, 5);
		$this->assertSame(Status::Active, $license->status());

		// retried a day later, not on the next request
		$this->assertFalse($stored->isDue());
		$this->assertTrue($stored->isDue(time() + Activation::RETRY + 60));
	}

	public function testRefreshOfflineSkipsOtherActivations(): void
	{
		$this->app();
		$this->store(
			['key' => 'key-1', 'validatedAt' => null, 'checkedAt' => null],
			['key' => 'key-2', 'product' => static::BUNDLE, 'validatedAt' => null, 'checkedAt' => null]
		);
		$this->respond('', 0);

		$this->license()->refresh();

		// the second activation is not tried after the connection error
		$this->assertCount(1, $this->requests);
		$this->assertEqualsWithDelta(time(), (new Storage($this->file()))->offline(), 5);

		// nor by other plugins or in later requests
		App::destroy();
		$this->app();
		$this->license([200], 'owebstudio/other')->refresh();
		$this->assertCount(1, $this->requests);

		// unless forced
		$this->respond($this->body(product: static::BUNDLE));
		$this->license([200], 'owebstudio/other')->refresh(force: true);
		$this->assertCount(2, $this->requests);
	}

	public function testRefreshAfterOfflineDay(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		(new Storage($this->file()))->markOffline(time() - Activation::RETRY - 60);
		$this->respond($this->body());

		$this->license()->refresh();

		$this->assertCount(1, $this->requests);
	}

	public function testRefreshRateLimitKeepsState(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		$this->respond(['error' => 'Too Many Attempts.'], 429);

		$license = $this->license();
		$license->refresh();

		$this->assertSame(Status::Active, $license->status());
	}

	public function testRefreshInstanceNotFound(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		$this->respond($this->body(value: false, error: 'license_key instance not found.', instance: null), 404);

		$license = $this->license();
		$license->refresh();

		$this->assertSame(Status::Invalid, $license->status());
		$this->assertSame('license_key instance not found.', $license->activation()->error);
	}

	public function testRefreshDisabledKey(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		$this->respond($this->body(value: false, status: 'disabled'));

		$license = $this->license();
		$license->refresh();

		$this->assertSame(Status::Invalid, $license->status());
		$this->assertSame('disabled', $license->activation()->error);
		$this->assertSame('The license key has been disabled.', License::explain('disabled'));
	}

	public function testRefreshValidAgain(): void
	{
		$this->app();
		$this->store(['valid' => false, 'error' => 'disabled', 'validatedAt' => null, 'checkedAt' => null]);
		$this->respond($this->body());

		$license = $this->license();
		$license->refresh();

		$this->assertSame(Status::Active, $license->status());
		$this->assertNull($license->activation()->error);
	}

	public function testRefreshNeverThrows(): void
	{
		$this->app();
		$this->store(['validatedAt' => null, 'checkedAt' => null]);
		$this->respond($this->body());

		// the file can be read but not written
		$license = new class (
			plugin: 'owebstudio/test',
			products: [100],
			store: self::STORE,
			client: $this->client(),
			file: $this->file()
		) extends License {
			public function storage(): Storage
			{
				return new class ($this->file) extends Storage {
					protected function write(array $data): void
					{
						throw new RuntimeException('Disk full');
					}
				};
			}
		};

		$license->refresh();

		$this->assertCount(1, $this->requests);
		$this->assertSame(Status::Active, $license->status());
	}

	public function testRefreshOnlyOwnDomain(): void
	{
		$this->app();
		$this->store(['domain' => 'other.com', 'validatedAt' => null, 'checkedAt' => null]);

		$this->license()->refresh();

		$this->assertSame([], $this->requests);
	}

	public function testMessage(): void
	{
		$this->app();
		$license = $this->license();

		$this->assertSame(
			'Test Plugin is not activated on example.com. Please ask an administrator to activate it.',
			$license->message()
		);

		App::instance()->impersonate('admin@example.com');
		$this->assertSame('Test Plugin is not activated on example.com.', $license->message());

		$this->store(['valid' => false]);
		$license = $this->license();
		$this->assertSame('The license of Test Plugin is not valid on example.com.', $license->message());
	}

	public function testPanel(): void
	{
		$this->app();
		$license = $this->license();

		$this->assertSame([
			'button'  => 'Activate',
			'dialog'  => null,
			'link'    => 'https://owebstudio.com/test',
			'locked'  => true,
			'message' => 'Test Plugin is not activated on example.com. Please ask an administrator to activate it.',
			'status'  => 'missing',
			'theme'   => 'notice',
		], $license->panel());

		App::instance()->impersonate('admin@example.com');
		$this->assertSame('oweb-license/owebstudio/test', $license->panel()['dialog']);
	}

	public function testPanelRefreshesInPanelRequests(): void
	{
		$this->store(['validatedAt' => null, 'checkedAt' => null]);

		// frontend request: no request to the License API
		$this->app('https://example.com', ['path' => 'blog/article']);
		$this->license()->panel();
		$this->assertSame([], $this->requests);

		App::destroy();
		$this->app('https://example.com', ['path' => 'panel/pages/home']);
		$this->respond($this->body());
		$this->license()->panel();
		$this->assertCount(1, $this->requests);
	}

	public function testFor(): void
	{
		$license = $this->license(plugin: 'owebstudio/registry');

		$this->assertSame($license, License::for('owebstudio/registry'));
		$this->assertNull(License::for('owebstudio/unknown'));
	}

	public function testProducts(): void
	{
		$this->assertSame([100, 101, static::BUNDLE], $this->license([100, '101', 100])->products());
	}

	public function testExtend(): void
	{
		$extends = $this->license()->extend([
			'options' => ['a' => 1]
		]);

		$this->assertSame(['a' => 1], $extends['options']);

		$area = $extends['areas']['system']($this->app());
		$this->assertArrayHasKey('oweb-license/owebstudio/test', $area['dialogs']);
		$this->assertIsCallable($area['dialogs']['oweb-license/owebstudio/test']['load']);
		$this->assertIsCallable($area['dialogs']['oweb-license/owebstudio/test']['submit']);
	}

	public function testExtendKeepsSystemArea(): void
	{
		$extends = $this->license()->extend([
			'areas' => [
				'system' => fn () => ['dialogs' => ['my-dialog' => ['load' => fn () => []]]]
			]
		]);

		$area = $extends['areas']['system']($this->app());
		$this->assertArrayHasKey('my-dialog', $area['dialogs']);
		$this->assertArrayHasKey('oweb-license/owebstudio/test', $area['dialogs']);
	}

	public function testExtendRegistersDialogInKirby(): void
	{
		$license = $this->license(plugin: 'owebstudio/registered');

		$this->app('https://example.com', [
			'areas' => $license->extend()['areas']
		]);

		$areas = App::instance()->load()->areas();
		$this->assertArrayHasKey('oweb-license/owebstudio/registered', $areas['system']['dialogs']);

		// the core dialogs are still there
		$this->assertArrayHasKey('license', $areas['system']['dialogs']);
	}

	public function testArgs(): void
	{
		$license = $this->license();
		$args    = $license->args();

		if (class_exists(PluginLicense::class) === false) {
			$this->assertSame([], $args);
			return;
		}

		$this->app();

		$plugin = new Plugin(name: 'owebstudio/test', extends: [], root: $this->tmp, license: $args['license']);
		$info   = $plugin->license()->toArray();

		$this->assertSame('oweb studio license', $info['name']);
		$this->assertSame(License::TERMS, $info['link']);
		$this->assertSame('missing', $info['status']['value']);
		$this->assertSame('Please activate', $info['status']['label']);
		$this->assertSame('key', $info['status']['icon']);
		$this->assertSame('love', $info['status']['theme']);
		$this->assertSame('oweb-license/owebstudio/test', $info['status']['dialog']);
	}

	public function testArgsForActiveLicense(): void
	{
		if (class_exists(PluginLicense::class) === false) {
			$this->markTestSkipped('Kirby 4 has no plugin licenses');
		}

		$this->app();
		$this->store([]);

		$license = $this->license();
		$plugin  = new Plugin(name: 'owebstudio/test', extends: [], root: $this->tmp, license: $license->args()['license']);
		$status  = $plugin->license()->status();

		$this->assertSame('active', $status->value());
		$this->assertSame('Active', $status->label());
		$this->assertSame('positive', $status->theme());
	}
}
