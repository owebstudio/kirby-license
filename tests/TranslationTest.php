<?php

namespace Oweb\License\Tests;

use Kirby\Cms\App;
use Oweb\License\Translation;

class TranslationTest extends TestCase
{
	public function testGet(): void
	{
		$this->assertSame('Active', Translation::get('status.active'));
		$this->assertSame('Aktiv', Translation::get('status.active', locale: 'de'));
		$this->assertSame('Etkin', Translation::get('status.active', locale: 'tr'));
	}

	public function testGetWithData(): void
	{
		$this->assertSame(
			'<b>x</b> is not activated on example.com.',
			Translation::get('notice.missing', ['plugin' => '<b>x</b>', 'domain' => 'example.com'])
		);
	}

	public function testHtml(): void
	{
		$this->assertSame(
			'<strong>&lt;b&gt;x&lt;/b&gt;</strong> is activated for <strong>a.com</strong>.',
			Translation::html('dialog.active', ['plugin' => '<b>x</b>', 'domain' => 'a.com'])
		);
	}

	public function testFallback(): void
	{
		$this->assertSame('Active', Translation::get('status.active', locale: 'fr'));
		$this->assertSame('Aktiv', Translation::get('status.active', locale: 'de_CH'));
		$this->assertSame('Active', Translation::get('status.active', locale: '../en'));
		$this->assertSame('unknown.key', Translation::get('unknown.key'));
	}

	public function testLocaleOfUser(): void
	{
		Translation::$locale = null;

		$this->app('https://example.com', [
			'users' => [
				['email' => 'tr@example.com', 'role' => 'admin', 'language' => 'tr']
			]
		]);

		$this->assertSame('en', Translation::locale());

		App::instance()->impersonate('tr@example.com');
		$this->assertSame('tr', Translation::locale());
		$this->assertSame('Etkin', Translation::get('status.active'));
	}

	public function testAllLocalesHaveTheSameKeys(): void
	{
		$keys = array_keys(Translation::strings('en'));
		sort($keys);

		$this->assertContains('tr', Translation::locales());
		$this->assertContains('de', Translation::locales());

		foreach (Translation::locales() as $locale) {
			$strings = Translation::strings($locale);
			$other   = array_keys($strings);
			sort($other);

			$this->assertSame($keys, $other, 'Keys of ' . $locale);

			// the placeholders are the same
			foreach (Translation::strings('en') as $key => $string) {
				preg_match_all('/\{\w+\}/', $string, $expected);
				preg_match_all('/\{\w+\}/', $strings[$key], $actual);
				sort($expected[0]);
				sort($actual[0]);
				$this->assertSame($expected[0], $actual[0], $locale . ': ' . $key);
			}
		}
	}
}
