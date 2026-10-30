<?php

namespace Oweb\License;

use Kirby\Cms\App;
use Kirby\Toolkit\Escape;
use Throwable;

/**
 * Translations of the package. They are kept out of
 * Kirby's translations, so that plugins bundling different
 * versions of the package never overwrite each other's texts.
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
class Translation
{
	public const FALLBACK = 'en';

	public static string|null $locale = null;

	protected static array $strings = [];

	/**
	 * Returns the translation for the key with
	 * `{placeholders}` replaced by the data
	 */
	public static function get(
		string $key,
		array $data = [],
		string|null $locale = null
	): string {
		$locale ??= static::locale();
		$string   = static::strings($locale)[$key] ?? static::strings(static::FALLBACK)[$key] ?? $key;

		$replace = [];

		foreach ($data as $name => $value) {
			$replace['{' . $name . '}'] = (string)$value;
		}

		return strtr($string, $replace);
	}

	/**
	 * Returns the translation as HTML: the translation's own markup
	 * is kept, every placeholder value is escaped
	 */
	public static function html(
		string $key,
		array $data = [],
		string|null $locale = null
	): string {
		$data = array_map(
			fn ($value) => Escape::html((string)$value),
			$data
		);

		return static::get($key, $data, $locale);
	}

	/**
	 * Detects the language of the current Panel user
	 */
	public static function locale(): string
	{
		if (static::$locale !== null) {
			return static::$locale;
		}

		try {
			$kirby  = App::instance();
			$locale = $kirby->user()?->language() ?? $kirby->option('panel.language');
		} catch (Throwable) {
			$locale = null;
		}

		return is_string($locale) === true ? $locale : static::FALLBACK;
	}

	/**
	 * Returns all locales the package has translations for
	 */
	public static function locales(): array
	{
		$locales = [];

		foreach (glob(__DIR__ . '/translations/*.php') as $file) {
			$locales[] = basename($file, '.php');
		}

		return $locales;
	}

	/**
	 * Loads the strings of a locale, e.g. `pt_BR`
	 * falls back to `pt` if there is no `pt_BR` file
	 */
	public static function strings(string $locale): array
	{
		if (isset(static::$strings[$locale]) === true) {
			return static::$strings[$locale];
		}

		$strings = [];

		foreach ([$locale, (string)strtok($locale, '_-')] as $name) {
			if (preg_match('/^[a-zA-Z_-]+$/', $name) !== 1) {
				continue;
			}

			$file = __DIR__ . '/translations/' . $name . '.php';

			if (is_file($file) === true) {
				$strings = require $file;
				break;
			}
		}

		return static::$strings[$locale] = $strings;
	}
}
