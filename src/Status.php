<?php

namespace Oweb\License;

/**
 * License status of a plugin on the current site
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://github.com/owebstudio/kirby-license
 * @license   MIT
 */
enum Status: string
{
	/**
	 * A valid key is activated for this domain
	 */
	case Active = 'active';

	/**
	 * Local or staging host, no key needed
	 */
	case Exempt = 'exempt';

	/**
	 * The activation for this domain is no longer valid
	 */
	case Invalid = 'invalid';

	/**
	 * No key is activated for this domain
	 */
	case Missing = 'missing';

	/**
	 * Icon for the Panel
	 */
	public function icon(): string
	{
		return match ($this) {
			static::Active  => 'check',
			static::Exempt  => 'preview',
			static::Invalid => 'alert',
			static::Missing => 'key'
		};
	}

	/**
	 * Whether plugins should lock their main features
	 */
	public function isLocked(): bool
	{
		return match ($this) {
			static::Active, static::Exempt   => false,
			static::Invalid, static::Missing => true
		};
	}

	/**
	 * Translated label for the Panel
	 */
	public function label(): string
	{
		return Translation::get('status.' . $this->value);
	}

	/**
	 * Theme for buttons and boxes in the Panel
	 */
	public function theme(): string
	{
		return match ($this) {
			static::Active  => 'positive',
			static::Exempt  => 'info',
			static::Invalid => 'negative',
			static::Missing => 'love'
		};
	}
}
