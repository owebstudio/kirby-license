<?php

namespace Oweb\License;

use Kirby\Exception\Exception;
use Throwable;

/**
 * Reads and writes the license file in `site/config`.
 * The file is shared by all plugins and all package versions:
 * the format only gets new fields, unknown fields are kept.
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
class Storage
{
	public const FILENAME = '.owebstudio-licenses';
	public const VERSION  = 1;

	public function __construct(
		protected string $file
	) {
	}

	/**
	 * Returns all stored activations
	 *
	 * @return array<\Oweb\License\Activation>
	 */
	public function activations(): array
	{
		$activations = [];

		foreach ($this->read()['activations'] as $data) {
			if (is_array($data) === true && $activation = Activation::fromArray($data)) {
				$activations[] = $activation;
			}
		}

		return $activations;
	}

	/**
	 * Returns the path of the license file
	 */
	public function file(): string
	{
		return $this->file;
	}

	/**
	 * Returns when the License API could not be reached the last time
	 * during a validation, shared by all plugins on the site
	 */
	public function offline(): int|null
	{
		$time = $this->read()['offlineAt'] ?? null;

		return is_string($time) === true && ($time = strtotime($time)) !== false ? $time : null;
	}

	/**
	 * Remembers that the License API could not be reached
	 */
	public function markOffline(int|null $time = null): void
	{
		$this->update(fn (array $activations) => $activations, [
			'offlineAt' => date(DATE_ATOM, $time ?? time())
		]);
	}

	/**
	 * Reads the license file; a missing or broken
	 * file counts as a file without activations
	 */
	public function read(): array
	{
		try {
			$json = is_file($this->file) === true ? file_get_contents($this->file) : false;
			$data = is_string($json) === true ? json_decode($json, true) : null;
		} catch (Throwable) {
			$data = null;
		}

		if (is_array($data) === false) {
			$data = [];
		}

		if (is_array($data['activations'] ?? null) === false) {
			$data['activations'] = [];
		}

		return $data;
	}

	/**
	 * Removes the activation of the same key and domain
	 */
	public function remove(Activation $activation): void
	{
		$this->update(function (array $activations) use ($activation) {
			return array_filter(
				$activations,
				fn (Activation $item) => $item->is($activation) === false
			);
		});
	}

	/**
	 * Adds the activation or replaces the one
	 * of the same key and domain
	 */
	public function save(Activation $activation): void
	{
		$this->update(function (array $activations) use ($activation) {
			$result = [];
			$found  = false;

			foreach ($activations as $item) {
				if ($item->is($activation) === true) {
					$item  = $activation;
					$found = true;
				}

				$result[] = $item;
			}

			if ($found === false) {
				$result[] = $activation;
			}

			return $result;
		});
	}

	/**
	 * Reads the current file, changes the activations
	 * and other top-level fields and writes the file back
	 */
	protected function update(callable $callback, array $fields = []): void
	{
		$data        = [...$this->read(), ...$fields];
		$activations = [];
		$broken      = [];

		foreach ($data['activations'] as $item) {
			if (is_array($item) === true && $activation = Activation::fromArray($item)) {
				$activations[] = $activation;
			} else {
				$broken[] = $item;
			}
		}

		$activations = $callback($activations);

		$data['version']     = max(static::VERSION, (int)($data['version'] ?? 0));
		$data['activations'] = [
			...array_map(fn (Activation $item) => $item->toArray(), array_values($activations)),
			...$broken
		];

		$this->write($data);
	}

	/**
	 * Writes the file atomically
	 *
	 * @throws \Kirby\Exception\Exception
	 */
	protected function write(array $data): void
	{
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$dir  = dirname($this->file);
		$temp = $dir . '/' . basename($this->file) . '.' . bin2hex(random_bytes(4)) . '.tmp';

		// warnings are silenced, the result is checked
		// and a translated error is thrown instead
		try {
			if (is_dir($dir) === false) {
				@mkdir($dir, 0755, true);
			}

			if (
				@file_put_contents($temp, $json . "\n") === false ||
				@rename($temp, $this->file) === false
			) {
				throw new Exception('Cannot write ' . $this->file);
			}
		} catch (Throwable) {
			if (is_file($temp) === true) {
				@unlink($temp);
			}

			throw new Exception(Translation::get('error.storage', [
				'file' => 'site/config/' . basename($this->file)
			]));
		}
	}
}
