<?php

namespace Oweb\License;

use Kirby\Cms\App;

/**
 * Detects the domain of the site and whether
 * it runs on a local or staging host
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://owebstudio.com
 * @license   MIT
 */
class Host
{
	/**
	 * Subdomains that Kirby's own license treats as the same
	 * site, see `Kirby\Cms\License::normalizeDomain()`
	 */
	public const PREFIXES = ['www.', 'dev.', 'test.', 'staging.'];

	/**
	 * Subdomains of staging hosts
	 */
	public const STAGING = ['dev.', 'test.', 'staging.'];

	public function __construct(
		protected App $kirby
	) {
	}

	/**
	 * Returns the normalized domain the license is activated for,
	 * e.g. `example.com` or `example.com/shop` for subfolders.
	 * Returns an empty string if the domain is unknown (CLI).
	 */
	public function domain(): string
	{
		return static::normalize($this->kirby->system()->indexUrl());
	}

	/**
	 * Returns the host name of the site
	 */
	public function host(): string
	{
		$host = parse_url($this->kirby->url('index'), PHP_URL_HOST);

		return is_string($host) === true ? strtolower($host) : '';
	}

	/**
	 * Checks if no license key is needed on this host
	 */
	public function isExempt(): bool
	{
		if ($this->kirby->option('owebstudio.license.exempt', true) === false) {
			return false;
		}

		return $this->isLocal() === true || $this->isStaging() === true;
	}

	/**
	 * Checks for local hosts like `localhost`, `*.test`, `*.local`
	 * and `*.ddev.site`, the same way Kirby core does
	 */
	public function isLocal(): bool
	{
		$host = $this->host();

		if ($host === 'localhost' || str_ends_with($host, '.localhost') === true) {
			return true;
		}

		if (in_array(trim($host, '[]'), ['127.0.0.1', '::1'], true) === true) {
			return true;
		}

		return $this->kirby->environment()->isLocal();
	}

	/**
	 * Checks for `staging.`, `dev.` and `test.` subdomains
	 * and for the hosts in the `owebstudio.license.staging` option,
	 * which may contain `*` wildcards, e.g. `*.example.dev`
	 */
	public function isStaging(): bool
	{
		$host = $this->host();

		if ($host === '') {
			return false;
		}

		foreach (static::STAGING as $prefix) {
			if (str_starts_with($host, $prefix) === true) {
				return true;
			}
		}

		foreach ((array)$this->kirby->option('owebstudio.license.staging', []) as $pattern) {
			if (static::matches($host, (string)$pattern) === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Matches a host against a pattern with `*` wildcards
	 */
	public static function matches(string $host, string $pattern): bool
	{
		$pattern = strtolower(trim($pattern));

		if ($pattern === '') {
			return false;
		}

		$regex = '/^' . str_replace('\*', '.+', preg_quote($pattern, '/')) . '$/';

		return preg_match($regex, $host) === 1;
	}

	/**
	 * Normalizes a URL or domain like Kirby core does
	 * for its own license, so that `www.`, `dev.`, `test.`
	 * and `staging.` subdomains belong to the same site
	 */
	public static function normalize(string $url): string
	{
		$domain = strtolower(trim($url));
		$domain = preg_replace('!^(https?:)?//!', '', $domain);
		$domain = rtrim($domain, '/');

		if ($domain === '' || str_contains($domain, '/') === true) {
			return $domain;
		}

		foreach (static::PREFIXES as $prefix) {
			if (str_starts_with($domain, $prefix) === true) {
				return substr($domain, strlen($prefix));
			}
		}

		return $domain;
	}
}
