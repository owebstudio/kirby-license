<?php

namespace Oweb\License;

/**
 * One activated license key on one domain,
 * as stored in the license file
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
class Activation
{
	/**
	 * Re-validate after a week
	 */
	public const LIFETIME = 604800;

	/**
	 * Wait a day after a failed attempt
	 */
	public const RETRY = 86400;

	public function __construct(
		public string $key,
		public string $domain,
		public string|null $instance = null,
		public int|null $store = null,
		public int|null $product = null,
		public string|null $productName = null,
		public string|null $variantName = null,
		public bool $valid = true,
		public string|null $error = null,
		public int|null $activatedAt = null,
		public int|null $validatedAt = null,
		public int|null $checkedAt = null,
		protected array $extra = []
	) {
	}

	/**
	 * Creates an activation from the stored data
	 */
	public static function fromArray(array $data): static|null
	{
		if (
			is_string($data['key'] ?? null) === false ||
			is_string($data['domain'] ?? null) === false
		) {
			return null;
		}

		$known = [
			'key', 'domain', 'instance', 'store', 'product',
			'productName', 'variantName', 'valid', 'error',
			'activatedAt', 'validatedAt', 'checkedAt'
		];

		return new static(
			key:         $data['key'],
			domain:      $data['domain'],
			instance:    static::string($data['instance'] ?? null),
			store:       static::int($data['store'] ?? null),
			product:     static::int($data['product'] ?? null),
			productName: static::string($data['productName'] ?? null),
			variantName: static::string($data['variantName'] ?? null),
			valid:       ($data['valid'] ?? false) === true,
			error:       static::string($data['error'] ?? null),
			activatedAt: static::time($data['activatedAt'] ?? null),
			validatedAt: static::time($data['validatedAt'] ?? null),
			checkedAt:   static::time($data['checkedAt'] ?? null),
			extra:       array_diff_key($data, array_flip($known))
		);
	}

	/**
	 * Checks if the activation should be validated again
	 */
	public function isDue(int|null $now = null): bool
	{
		$now ??= time();

		if (
			$this->validatedAt !== null &&
			$this->validatedAt > $now - static::LIFETIME
		) {
			return false;
		}

		if (
			$this->checkedAt !== null &&
			$this->checkedAt > $now - static::RETRY
		) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if the activation belongs to the same key and domain
	 */
	public function is(Activation $activation): bool
	{
		return
			$this->key === $activation->key &&
			$this->domain === $activation->domain;
	}

	/**
	 * Returns the key with only the first and last
	 * four characters visible
	 */
	public function maskedKey(): string
	{
		if (strlen($this->key) <= 12) {
			return str_repeat('•', strlen($this->key));
		}

		return substr($this->key, 0, 4) . '…' . substr($this->key, -4);
	}

	/**
	 * Returns the data for the license file;
	 * unknown fields from newer package versions are kept
	 */
	public function toArray(): array
	{
		return [
			...$this->extra,
			'key'         => $this->key,
			'domain'      => $this->domain,
			'instance'    => $this->instance,
			'store'       => $this->store,
			'product'     => $this->product,
			'productName' => $this->productName,
			'variantName' => $this->variantName,
			'valid'       => $this->valid,
			'error'       => $this->error,
			'activatedAt' => static::date($this->activatedAt),
			'validatedAt' => static::date($this->validatedAt),
			'checkedAt'   => static::date($this->checkedAt),
		];
	}

	protected static function date(int|null $time): string|null
	{
		return $time !== null ? date(DATE_ATOM, $time) : null;
	}

	protected static function int(mixed $value): int|null
	{
		return is_numeric($value) === true ? (int)$value : null;
	}

	protected static function string(mixed $value): string|null
	{
		return is_string($value) === true && $value !== '' ? $value : null;
	}

	protected static function time(mixed $value): int|null
	{
		if (is_int($value) === true) {
			return $value;
		}

		if (is_string($value) === true && ($time = strtotime($value)) !== false) {
			return $time;
		}

		return null;
	}
}
