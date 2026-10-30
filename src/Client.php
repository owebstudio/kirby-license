<?php

namespace Oweb\License;

use Closure;
use Kirby\Http\Remote;
use Throwable;

/**
 * Client for the LemonSqueezy License API
 * (form-encoded POST requests, no API key needed)
 *
 * @link https://docs.lemonsqueezy.com/api/license-api
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
class Client
{
	public const ENDPOINT = 'https://api.lemonsqueezy.com/v1/licenses/';

	/**
	 * @param \Closure|null $transport Sends the request instead of
	 *                                 Kirby's `Remote` class (for tests):
	 *                                 `fn (string $url, array $data): array`
	 *                                 returning `['code' => int, 'body' => string]`
	 */
	public function __construct(
		protected Closure|null $transport = null,
		protected int $timeout = 5
	) {
	}

	/**
	 * Activates the key for a new instance
	 *
	 * @throws \Oweb\License\ConnectionException
	 */
	public function activate(string $key, string $instanceName): array
	{
		return $this->post('activate', [
			'license_key'   => $key,
			'instance_name' => $instanceName
		]);
	}

	/**
	 * Deactivates the instance of the key
	 *
	 * @throws \Oweb\License\ConnectionException
	 */
	public function deactivate(string $key, string $instance): array
	{
		return $this->post('deactivate', [
			'license_key' => $key,
			'instance_id' => $instance
		]);
	}

	/**
	 * Options for Kirby's `Remote` class
	 */
	public function options(array $data): array
	{
		return [
			'method'  => 'POST',
			'data'    => $data,
			'headers' => ['Accept' => 'application/json'],
			'timeout' => $this->timeout
		];
	}

	/**
	 * Sends the request and returns the decoded response.
	 * Any response with a JSON body and a status code below 500
	 * (except 429) is an answer of the License API. Everything
	 * else counts as a connection problem.
	 *
	 * @throws \Oweb\License\ConnectionException
	 */
	public function post(string $action, array $data): array
	{
		$url = static::ENDPOINT . $action;

		try {
			$response = match ($this->transport) {
				null    => $this->remote($url, $data),
				default => ($this->transport)($url, $data)
			};
		} catch (Throwable $e) {
			throw new ConnectionException($e->getMessage(), 0, $e);
		}

		$code = (int)($response['code'] ?? 0);
		$body = json_decode((string)($response['body'] ?? ''), true);

		if ($code < 200 || $code === 429 || $code >= 500 || is_array($body) === false) {
			throw new ConnectionException('Unexpected response from the License API (HTTP ' . $code . ')', $code);
		}

		return $body;
	}

	/**
	 * Validates the key, or one instance of it
	 *
	 * @throws \Oweb\License\ConnectionException
	 */
	public function validate(string $key, string|null $instance = null): array
	{
		$data = ['license_key' => $key];

		if ($instance !== null) {
			$data['instance_id'] = $instance;
		}

		return $this->post('validate', $data);
	}

	/**
	 * Sends the request with Kirby's `Remote` class
	 */
	protected function remote(string $url, array $data): array
	{
		$remote = Remote::request($url, $this->options($data));

		return [
			'code' => $remote->code(),
			'body' => $remote->content()
		];
	}
}
