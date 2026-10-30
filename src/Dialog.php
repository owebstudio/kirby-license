<?php

namespace Oweb\License;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\LogicException;
use Kirby\Exception\PermissionException;
use Kirby\Toolkit\HtmlString;

/**
 * Panel dialog to activate and deactivate a license.
 * Built from core components only, so the same definition
 * works with the Vue 2 Panel (Kirby 4, 5) and the Vue 3 Panel (Kirby 6).
 *
 * @package   oweb studio license
 * @author    Ahmet Bora
 * @link      https://owebstudio.com
 * @license   MIT
 */
class Dialog
{
	public function __construct(
		protected License $license
	) {
	}

	/**
	 * Returns the dialog definition
	 */
	public function load(): array
	{
		$license = $this->license;
		$license->refresh();

		$fields = match ($license->status()) {
			Status::Active  => $this->activeFields(),
			Status::Exempt  => $this->exemptFields(),
			default         => $this->activateFields()
		};

		$submit = $license->canManage() === true ? match ($license->status()) {
			Status::Active => [
				'icon'  => 'trash',
				'text'  => Translation::get('button.deactivate'),
				'theme' => 'negative'
			],
			Status::Exempt => false,
			default => [
				'icon'  => 'key',
				'text'  => Translation::get('button.activate'),
				'theme' => 'positive'
			]
		} : false;

		return [
			'component' => 'k-form-dialog',
			'props'     => [
				'fields'       => $fields,
				'size'         => 'medium',
				'submitButton' => $submit,
				'value'        => [
					'deactivate' => false,
					// only admins see the key field
					'key'        => $license->canManage() === true ? $this->otherDomain()?->key : null
				]
			]
		];
	}

	/**
	 * Returns the dialog routes for the system area
	 */
	public function routes(): array
	{
		// Kirby binds route closures to the route,
		// so `$this` must not be used inside them
		$dialog = $this;

		return [
			$this->license->dialog() => [
				'load'   => fn () => $dialog->load(),
				'submit' => fn () => $dialog->submit()
			]
		];
	}

	/**
	 * Activates or deactivates the license
	 *
	 * @throws \Kirby\Exception\Exception
	 */
	public function submit(): array
	{
		$license = $this->license;
		$request = App::instance()->request();

		if ($license->canManage() === false) {
			throw new PermissionException(Translation::get('error.permission'));
		}

		switch ($license->status()) {
			case Status::Active:
				if (in_array($request->get('deactivate'), [true, 'true', 1, '1'], true) === false) {
					throw new InvalidArgumentException(Translation::get('error.confirm'));
				}

				$license->deactivate();

				return [
					'message' => Translation::get('success.deactivate')
				];

			case Status::Exempt:
				throw new LogicException(Translation::get('error.exempt'));

			default:
				$license->activate((string)$request->get('key', ''));

				return [
					'message' => Translation::get('success.activate')
				];
		}
	}

	/**
	 * Fields to enter a license key
	 */
	protected function activateFields(): array
	{
		$license    = $this->license;
		$activation = $license->activation();
		$other      = $this->otherDomain();
		$fields     = [];

		if ($activation?->valid === false) {
			$fields['invalid'] = $this->info('negative', Translation::html('dialog.invalid', [
				'plugin' => $license->name(),
				'reason' => License::explain($activation->error ?? 'invalid')
			]));
		}

		if ($license->canManage() === false) {
			$fields['admin'] = $this->info('notice', Translation::html('dialog.admin'));
			return $fields;
		}

		$fields['domain'] = $this->info('info', Translation::html('dialog.domain', [
			'domain' => $license->host()->domain()
		]));

		if ($other !== null) {
			$fields['other'] = $this->info('notice', Translation::html('dialog.other', [
				'domain' => $other->domain
			]));
		}

		$fields['key'] = [
			'type'        => 'text',
			'label'       => Translation::get('field.key'),
			'icon'        => 'key',
			'required'    => true,
			'counter'     => false,
			'autofocus'   => true,
			'placeholder' => 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
			'help'        => $this->help()
		];

		return $fields;
	}

	/**
	 * Fields of an active license
	 */
	protected function activeFields(): array
	{
		$license    = $this->license;
		$activation = $license->activation();

		$lines = [
			Translation::html('dialog.active', [
				'domain' => $activation->domain,
				'plugin' => $license->name()
			]),
			Translation::html('dialog.product', [
				'product' => trim(($activation->productName ?? '?') . ' ' . ($activation->variantName ? '(' . $activation->variantName . ')' : ''))
			]),
			Translation::html('dialog.key', [
				'key' => $activation->maskedKey()
			])
		];

		if ($activation->validatedAt !== null) {
			$lines[] = Translation::html('dialog.validated', [
				'date' => date('Y-m-d', $activation->validatedAt)
			]);
		}

		$fields = [
			'license' => $this->info('positive', implode('<br>', $lines))
		];

		if ($license->canManage() === true) {
			$fields['deactivate'] = [
				'type'     => 'toggle',
				'label'    => Translation::get('field.deactivate'),
				'text'     => Translation::get('field.deactivate.text', [
					'domain' => $activation->domain
				]),
				'help'     => Translation::get('field.deactivate.help'),
				'required' => true
			];
		}

		return $fields;
	}

	/**
	 * Fields for local and staging hosts
	 */
	protected function exemptFields(): array
	{
		return [
			'exempt' => $this->info('info', Translation::html('dialog.exempt', [
				'host'   => $this->license->host()->host(),
				'plugin' => $this->license->name()
			]))
		];
	}

	/**
	 * Help text of the key field with the shop link
	 */
	protected function help(): HtmlString|string
	{
		$help = Translation::html('field.key.help');
		$url  = $this->license->url();

		if ($url !== null) {
			$help .= ' ' . Translation::html('field.key.buy', ['url' => $url]);
		}

		return static::trusted($help);
	}

	/**
	 * Info field with trusted HTML
	 */
	protected function info(string $theme, string $html): array
	{
		return [
			'type'  => 'info',
			'theme' => $theme,
			'text'  => static::trusted($html)
		];
	}

	/**
	 * Returns a valid activation of the key on another domain,
	 * e.g. after the site moved to a new domain
	 */
	protected function otherDomain(): Activation|null
	{
		$license = $this->license;

		if ($license->status() === Status::Active) {
			return null;
		}

		$domain = $license->host()->domain();

		foreach ($license->storage()->activations() as $activation) {
			if (
				$activation->valid === true &&
				$activation->domain !== $domain &&
				$license->accepts($activation->store, $activation->product) === true
			) {
				return $activation;
			}
		}

		return null;
	}

	/**
	 * Marks HTML as trusted for the Kirby 6 Panel,
	 * which escapes all other strings
	 */
	public static function trusted(string $html): HtmlString|string
	{
		if (class_exists(HtmlString::class) === true) {
			return new HtmlString($html);
		}

		return $html;
	}
}
