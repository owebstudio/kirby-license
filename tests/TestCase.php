<?php

namespace Oweb\License\Tests;

use Kirby\Cms\App;
use Oweb\License\Client;
use Oweb\License\License;
use Oweb\License\Storage;
use Oweb\License\Translation;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;
use Throwable;

abstract class TestCase extends BaseTestCase
{
	public const KEY    = '38b1460a-5104-4067-a91d-77b872934d51';
	public const STORE  = 1;
	public const BUNDLE = 999;

	protected string $tmp;
	protected array $requests  = [];
	protected array $responses = [];

	protected function setUp(): void
	{
		$this->tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/oweb-license-' . bin2hex(random_bytes(6));
		mkdir($this->tmp . '/site/config', 0777, true);

		Translation::$locale = 'en';
	}

	protected function tearDown(): void
	{
		Translation::$locale = null;

		App::destroy();

		$this->remove($this->tmp);
	}

	/**
	 * Creates a Kirby instance for the URL
	 */
	protected function app(string|null $url = 'https://example.com', array $props = []): App
	{
		$options = $props['options'] ?? [];

		if ($url !== null) {
			$options['url'] = $url;
		}

		unset($props['options']);

		return new App([
			'roots'   => ['index' => $this->tmp],
			'options' => $options,
			'roles'   => [
				['name' => 'admin', 'title' => 'Admin'],
				['name' => 'editor', 'title' => 'Editor']
			],
			'users' => [
				['email' => 'admin@example.com', 'role' => 'admin'],
				['email' => 'editor@example.com', 'role' => 'editor']
			],
			...$props
		]);
	}

	/**
	 * Fake client that records the requests
	 * and returns the queued responses
	 */
	protected function client(): Client
	{
		return new Client(function (string $url, array $data) {
			$this->requests[] = [
				'url'    => $url,
				'action' => basename($url),
				'data'   => $data
			];

			if ($this->responses === []) {
				throw new RuntimeException('Unexpected request to ' . $url);
			}

			$response = array_shift($this->responses);

			if ($response instanceof Throwable) {
				throw $response;
			}

			return $response;
		});
	}

	protected function file(): string
	{
		return $this->tmp . '/site/config/' . Storage::FILENAME;
	}

	protected function license(
		array $products = [100],
		string $plugin = 'owebstudio/test',
		string $name = 'Test Plugin'
	): License {
		return new License(
			plugin: $plugin,
			products: $products,
			name: $name,
			url: 'https://owebstudio.com/test',
			store: static::STORE,
			bundle: [static::BUNDLE],
			client: $this->client()
		);
	}

	/**
	 * Builds a response body like the License API does
	 */
	protected function body(
		string $flag = 'valid',
		bool $value = true,
		int $product = 100,
		int $store = self::STORE,
		string $status = 'active',
		string|null $error = null,
		string|null $instance = 'instance-1'
	): array {
		return [
			$flag         => $value,
			'error'       => $error,
			'license_key' => [
				'id'               => 1,
				'status'           => $status,
				'key'              => static::KEY,
				'activation_limit' => 5,
				'activation_usage' => 1,
				'expires_at'       => null
			],
			'instance' => $instance === null ? null : [
				'id'   => $instance,
				'name' => 'example.com'
			],
			'meta' => [
				'store_id'     => $store,
				'product_id'   => $product,
				'product_name' => 'Product ' . $product,
				'variant_id'   => 1,
				'variant_name' => 'Regular'
			]
		];
	}

	protected function respond(array|string|null $body, int $code = 200): void
	{
		$this->responses[] = [
			'code' => $code,
			'body' => is_array($body) === true ? json_encode($body) : $body
		];
	}

	protected function respondWith(Throwable $exception): void
	{
		$this->responses[] = $exception;
	}

	/**
	 * Writes activations directly to the license file
	 */
	protected function store(array ...$activations): void
	{
		$defaults = [
			'key'         => static::KEY,
			'domain'      => 'example.com',
			'instance'    => 'instance-1',
			'store'       => static::STORE,
			'product'     => 100,
			'productName' => 'Product 100',
			'valid'       => true,
			'activatedAt' => date(DATE_ATOM),
			'validatedAt' => date(DATE_ATOM),
			'checkedAt'   => date(DATE_ATOM)
		];

		file_put_contents($this->file(), json_encode([
			'version'     => 1,
			'activations' => array_map(fn ($item) => [...$defaults, ...$item], $activations)
		]));
	}

	protected function stored(): array
	{
		return json_decode(file_get_contents($this->file()), true);
	}

	private function remove(string $dir): void
	{
		if (is_dir($dir) === false) {
			return;
		}

		foreach (scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $dir . '/' . $item;
			is_dir($path) === true ? $this->remove($path) : unlink($path);
		}

		rmdir($dir);
	}
}
