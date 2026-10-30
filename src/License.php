<?php

namespace Oweb\License;

use Closure;
use Kirby\Cms\App;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\LogicException;
use Kirby\Exception\NotFoundException;
use Kirby\Plugin\License as PluginLicense;
use Kirby\Plugin\LicenseStatus as PluginLicenseStatus;
use Kirby\Plugin\Plugin;
use Throwable;

/**
 * License of one paid plugin
 *
 * ```php
 * $license = new License(
 *     plugin: 'owebstudio/instagram-feed',
 *     products: [123456],
 *     name: 'Instagram Feed',
 *     url: 'https://owebstudio.lemonsqueezy.com/checkout/buy/29afd8d3-78d4-4cd5-a19b-a13c3d3b5e1b'
 * );
 *
 * Kirby::plugin('owebstudio/instagram-feed', $license->extend([
 *     // the plugin's extensions
 * ]), ...$license->args());
 * ```
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
class License
{
	public const VERSION = '1.0.1';

	/**
	 * LemonSqueezy store of oweb studio
	 */
	public const STORE = 8696;

	/**
	 * Products that unlock every paid plugin (the legacy bundle)
	 */
	public const BUNDLE = [15623];

	/**
	 * Terms of the commercial license
	 */
	public const TERMS = 'https://github.com/owebstudio/.github/blob/main/LICENSE.md';

	/**
	 * Reason codes for invalid activations
	 */
	public const REASONS = ['disabled', 'expired', 'invalid'];

	protected static array $instances = [];

	/**
	 * The Kirby instance (request) of the last refresh
	 */
	protected App|null $refreshed = null;

	/**
	 * @param string $plugin Plugin name, e.g. `owebstudio/instagram-feed`
	 * @param array $products LemonSqueezy product IDs of the plugin;
	 *                        the legacy bundle is added automatically
	 * @param string|null $name Plugin name for the Panel
	 * @param string|null $url Where to buy a license
	 * @param int|null $store Store ID (for tests)
	 * @param array|null $bundle Bundle product IDs (for tests)
	 * @param \Oweb\License\Client|null $client API client (for tests)
	 * @param string|null $file Path of the license file (for tests)
	 */
	public function __construct(
		protected string $plugin,
		protected array $products,
		protected string|null $name = null,
		protected string|null $url = null,
		protected int|null $store = null,
		protected array|null $bundle = null,
		protected Client|null $client = null,
		protected string|null $file = null
	) {
		static::$instances[$plugin] = $this;
	}

	/**
	 * Returns the license object of a plugin
	 */
	public static function for(string $plugin): self|null
	{
		return static::$instances[$plugin] ?? null;
	}

	/**
	 * Checks if the store and product unlock the plugin
	 */
	public function accepts(int|null $store, int|null $product): bool
	{
		return
			$store !== null &&
			$store === $this->store() &&
			in_array($product, $this->products(), true) === true;
	}

	/**
	 * Activates the key for the domain of the site. Keys that are already
	 * activated for this domain (e.g. a bundle key by another plugin)
	 * are reused without using another activation.
	 *
	 * @throws \Kirby\Exception\Exception
	 */
	public function activate(string $key): Activation
	{
		$key    = trim($key);
		$domain = $this->host()->domain();

		if ($key === '') {
			throw new InvalidArgumentException(Translation::get('error.key'));
		}

		if ($domain === '') {
			throw new LogicException(Translation::get('error.domain'));
		}

		$previous = null;

		foreach ($this->storage()->activations() as $activation) {
			if ($activation->key !== $key || $activation->domain !== $domain) {
				continue;
			}

			if ($activation->valid === false) {
				$previous = $activation;
				continue;
			}

			if ($this->accepts($activation->store, $activation->product) === false) {
				throw new InvalidArgumentException(static::explain('invalid'));
			}

			return $activation;
		}

		// check the key before it uses up an activation
		$this->check($this->request(fn (Client $client) => $client->validate($key)));

		// free the instance of an activation that is no longer valid
		if ($previous?->instance !== null) {
			try {
				$this->client()->deactivate($key, $previous->instance);
			} catch (Throwable) {
				// the instance may be gone already
			}
		}

		$response = $this->request(fn (Client $client) => $client->activate($key, $domain));
		$instance = $response['instance']['id'] ?? null;

		if (($response['activated'] ?? false) !== true || is_string($instance) === false) {
			throw new InvalidArgumentException(Translation::get('error.activate', [
				'error' => $response['error'] ?? '?'
			]));
		}

		$now = time();

		try {
			$meta       = $this->check($response);
			$activation = new Activation(
				key:         $key,
				domain:      $domain,
				instance:    $instance,
				store:       (int)$meta['store_id'],
				product:     (int)$meta['product_id'],
				productName: $meta['product_name'] ?? null,
				variantName: $meta['variant_name'] ?? null,
				valid:       true,
				activatedAt: $now,
				validatedAt: $now,
				checkedAt:   $now
			);

			$this->storage()->save($activation);
		} catch (Throwable $e) {
			// an activation without a stored instance is lost, give it back
			try {
				$this->client()->deactivate($key, $instance);
			} catch (Throwable) {
			}

			throw $e;
		}

		return $activation;
	}

	/**
	 * Returns the activation that unlocks the plugin on this domain:
	 * a valid one if there is one, otherwise an invalid one.
	 * The file is read every time, as plugins with their own
	 * copy of this package may have changed it.
	 */
	public function activation(): Activation|null
	{
		$invalid = null;

		foreach ($this->storage()->activations() as $activation) {
			if ($this->matches($activation) === false) {
				continue;
			}

			if ($activation->valid === true) {
				return $activation;
			}

			$invalid ??= $activation;
		}

		return $invalid;
	}

	/**
	 * Returns the arguments for `Kirby::plugin()`: the license
	 * for the Panel's System view on Kirby 5 and newer, nothing on
	 * Kirby 4, which has no plugin licenses
	 */
	public function args(): array
	{
		if (class_exists(PluginLicense::class) === false) {
			return [];
		}

		$license = $this;

		return [
			'license' => fn ($plugin) => $license->pluginLicense($plugin)
		];
	}

	/**
	 * Checks if the current user may activate and deactivate licenses
	 */
	public function canManage(): bool
	{
		try {
			return App::instance()->user()?->isAdmin() === true;
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Deactivates the activation of this domain
	 *
	 * @throws \Kirby\Exception\Exception
	 */
	public function deactivate(): void
	{
		$activation = $this->activation();

		if ($activation === null) {
			throw new NotFoundException(Translation::get('error.missing'));
		}

		// any answer of the API means the instance can go,
		// e.g. it was already deleted in LemonSqueezy
		if ($activation->instance !== null) {
			$this->request(fn (Client $client) => $client->deactivate($activation->key, $activation->instance));
		}

		$this->storage()->remove($activation);
	}

	/**
	 * Returns the Panel path of the license dialog
	 */
	public function dialog(): string
	{
		return 'oweb-license/' . $this->plugin;
	}

	/**
	 * Adds the license dialogs to the plugin's extensions
	 */
	public function extend(array $extends = []): array
	{
		$dialogs = (new Dialog($this))->routes();
		$area    = $extends['areas']['system'] ?? null;

		$extends['areas']['system'] = function (App $kirby) use ($area, $dialogs) {
			if ($area instanceof Closure) {
				$area = $area($kirby);
			}

			return array_replace_recursive(
				is_array($area) === true ? $area : [],
				['dialogs' => $dialogs]
			);
		};

		return $extends;
	}

	/**
	 * Returns the host detection for the current site
	 */
	public function host(): Host
	{
		return new Host(App::instance());
	}

	/**
	 * Checks if the plugin may be used without restrictions:
	 * a valid key is activated, or the site runs on a local
	 * or staging host. Never sends a request.
	 */
	public function isLicensed(): bool
	{
		return $this->isLocked() === false;
	}

	/**
	 * Checks if the plugin should lock its main features.
	 * Never sends a request.
	 */
	public function isLocked(): bool
	{
		return $this->status()->isLocked();
	}

	/**
	 * Returns the notice for the Panel if the plugin is locked
	 */
	public function message(): string|null
	{
		$status = $this->status();

		if ($status->isLocked() === false) {
			return null;
		}

		$message = Translation::get('notice.' . $status->value, [
			'domain' => $this->host()->domain(),
			'plugin' => $this->name()
		]);

		if ($this->canManage() === false) {
			$message .= ' ' . Translation::get('notice.admin');
		}

		return $message;
	}

	/**
	 * Returns the plugin name for the Panel
	 */
	public function name(): string
	{
		return $this->name ?? $this->plugin;
	}

	/**
	 * Returns the state for the plugin's Panel components
	 */
	public function panel(): array
	{
		if ($this->isPanelRequest() === true) {
			$this->refresh();
		}

		$status = $this->status();

		return [
			'button'  => Translation::get($status->isLocked() === true ? 'button.activate' : 'button.license'),
			'dialog'  => $this->canManage() === true ? $this->dialog() : null,
			'link'    => $this->url,
			'locked'  => $status->isLocked(),
			'message' => $this->message(),
			'status'  => $status->value,
			'theme'   => $status === Status::Invalid ? 'negative' : 'notice',
		];
	}

	/**
	 * Returns the plugin name, e.g. `owebstudio/instagram-feed`
	 */
	public function plugin(): string
	{
		return $this->plugin;
	}

	/**
	 * Returns the license for the Panel's System view (Kirby 5+)
	 */
	public function pluginLicense(Plugin $plugin): PluginLicense
	{
		if ($this->isPanelRequest() === true) {
			$this->refresh();
		}

		$status = $this->status();

		return new PluginLicense(
			plugin: $plugin,
			name: Translation::get('name'),
			link: static::TERMS,
			status: new PluginLicenseStatus(
				value: $status->value,
				icon: $status->icon(),
				label: $status->label(),
				dialog: $this->dialog(),
				theme: $status->theme()
			)
		);
	}

	/**
	 * Returns all product IDs that unlock the plugin
	 */
	public function products(): array
	{
		$products = [...$this->products, ...($this->bundle ?? static::BUNDLE)];
		$products = array_map('intval', $products);

		return array_values(array_unique($products));
	}

	/**
	 * Validates the activations of this domain again if their last
	 * validation is older than a week. Network errors keep the last
	 * known state and are retried a day later. Runs once per request
	 * and never throws.
	 *
	 * @param bool $force Validate even if the last validation is recent
	 */
	public function refresh(bool $force = false): void
	{
		try {
			$kirby = App::instance();

			if ($this->refreshed === $kirby && $force === false) {
				return;
			}

			$this->refreshed = $kirby;
			$storage         = $this->storage();

			// don't make every plugin wait for an unreachable API
			$offline = $storage->offline();

			if ($force === false && $offline !== null && $offline > time() - Activation::RETRY) {
				return;
			}

			foreach ($storage->activations() as $activation) {
				if (
					$this->matches($activation) === false ||
					$activation->instance === null ||
					($force === false && $activation->isDue() === false)
				) {
					continue;
				}

				if ($this->validate($activation) === false) {
					break;
				}
			}
		} catch (Throwable) {
			// the license check must never break the Panel
		}
	}

	/**
	 * Returns the license status on this site.
	 * Never sends a request and never throws.
	 */
	public function status(): Status
	{
		try {
			$activation = $this->activation();

			if ($activation?->valid === true) {
				return Status::Active;
			}

			if ($this->host()->isExempt() === true) {
				return Status::Exempt;
			}

			return $activation === null ? Status::Missing : Status::Invalid;
		} catch (Throwable) {
			return Status::Missing;
		}
	}

	/**
	 * Returns the storage of the license file
	 */
	public function storage(): Storage
	{
		return new Storage(
			$this->file ?? App::instance()->root('config') . '/' . Storage::FILENAME
		);
	}

	/**
	 * Returns the LemonSqueezy store ID
	 */
	public function store(): int|null
	{
		return $this->store ?? static::STORE;
	}

	/**
	 * Returns the shop link of the plugin
	 */
	public function url(): string|null
	{
		return $this->url;
	}

	/**
	 * Translates the reason why an activation is not valid
	 */
	public static function explain(string $reason): string
	{
		if (in_array($reason, static::REASONS, true) === true) {
			return Translation::get('reason.' . $reason);
		}

		// error message of the License API
		return $reason;
	}

	/**
	 * Checks an API response for the store, the product and the
	 * status of the key and returns its meta data
	 *
	 * @throws \Kirby\Exception\InvalidArgumentException
	 */
	protected function check(array $response): array
	{
		$reason = $this->reason($response);

		if ($reason !== null) {
			throw new InvalidArgumentException(static::explain($reason));
		}

		return $response['meta'];
	}

	/**
	 * Returns the API client
	 */
	protected function client(): Client
	{
		return $this->client ??= new Client();
	}

	/**
	 * Checks if the current request goes to the Panel or the API
	 */
	protected function isPanelRequest(): bool
	{
		try {
			$kirby = App::instance();
			$path  = trim($kirby->path(), '/') . '/';

			foreach ([$kirby->option('panel.slug', 'panel'), $kirby->option('api.slug', 'api')] as $slug) {
				if (str_starts_with($path, trim((string)$slug, '/') . '/') === true) {
					return true;
				}
			}
		} catch (Throwable) {
		}

		return false;
	}

	/**
	 * Checks if the activation belongs to this plugin and domain.
	 * Without a known domain (CLI) any domain matches.
	 */
	protected function matches(Activation $activation): bool
	{
		if ($this->accepts($activation->store, $activation->product) === false) {
			return false;
		}

		$domain = $this->host()->domain();

		return $domain === '' || Host::normalize($activation->domain) === $domain;
	}

	/**
	 * Returns why an API response doesn't unlock the plugin:
	 * a reason code (see `REASONS`), the API's error message,
	 * or `null` if the key is fine
	 *
	 * @param string|null $flag Response field that must be `true`,
	 *                          e.g. `valid`
	 */
	protected function reason(array $response, string|null $flag = null): string|null
	{
		$meta   = $response['meta'] ?? null;
		$status = $response['license_key']['status'] ?? null;

		// keys of other stores or other products are simply invalid
		if (
			is_array($meta) === false ||
			is_string($status) === false ||
			$this->accepts((int)($meta['store_id'] ?? 0), (int)($meta['product_id'] ?? 0)) === false
		) {
			return 'invalid';
		}

		if (in_array($status, ['disabled', 'expired'], true) === true) {
			return $status;
		}

		if ($flag !== null && ($response[$flag] ?? false) !== true) {
			$error = $response['error'] ?? null;

			return is_string($error) === true && $error !== '' ? $error : 'invalid';
		}

		return null;
	}

	/**
	 * Sends a request and turns connection problems
	 * into a translated error
	 *
	 * @throws \Kirby\Exception\Exception
	 */
	protected function request(Closure $callback): array
	{
		try {
			return $callback($this->client());
		} catch (ConnectionException) {
			throw new Exception(Translation::get('error.offline'));
		}
	}

	/**
	 * Validates a stored activation and saves the result.
	 * Returns `false` if the API could not be reached.
	 */
	protected function validate(Activation $activation): bool
	{
		$now = time();

		$activation->checkedAt = $now;

		try {
			$response = $this->client()->validate($activation->key, $activation->instance);
		} catch (ConnectionException) {
			$this->storage()->save($activation);
			$this->storage()->markOffline($now);
			return false;
		}

		$reason = $this->reason($response, 'valid');

		if ($reason === null) {
			$activation->valid       = true;
			$activation->error       = null;
			$activation->validatedAt = $now;
			$activation->productName = $response['meta']['product_name'] ?? $activation->productName;
			$activation->variantName = $response['meta']['variant_name'] ?? $activation->variantName;
		} else {
			$activation->valid = false;
			$activation->error = $reason;
		}

		$this->storage()->save($activation);

		return true;
	}
}
