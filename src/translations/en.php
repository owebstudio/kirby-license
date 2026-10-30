<?php

return [
	'name' => 'oweb studio license',

	'status.active'  => 'Active',
	'status.exempt'  => 'Test environment',
	'status.invalid' => 'Invalid license',
	'status.missing' => 'Please activate',

	'button.activate'   => 'Activate',
	'button.deactivate' => 'Deactivate',
	'button.license'    => 'License',

	'dialog.active'    => '<strong>{plugin}</strong> is activated for <strong>{domain}</strong>.',
	'dialog.admin'     => 'Only administrators can activate licenses.',
	'dialog.domain'    => 'The license will be activated for <strong>{domain}</strong>.',
	'dialog.exempt'    => '<strong>{plugin}</strong> needs no license key on <strong>{host}</strong>, because it is a local or staging site. Activate your key on the live site.',
	'dialog.invalid'   => 'The license of <strong>{plugin}</strong> is no longer valid: {reason}',
	'dialog.key'       => 'License key: {key}',
	'dialog.other'     => 'This license is activated for <strong>{domain}</strong>. Activating it here uses another activation of the key.',
	'dialog.product'   => 'Product: {product}',
	'dialog.validated' => 'Last validated: {date}',

	'field.key'             => 'License key',
	'field.key.buy'         => '<a href="{url}" target="_blank">Buy a license &rarr;</a>',
	'field.key.help'        => 'You can find the key in the email of your purchase.',
	'field.deactivate'      => 'Deactivate',
	'field.deactivate.help' => 'Other oweb studio plugins that use the same key are deactivated on this site as well.',
	'field.deactivate.text' => 'Remove the activation from {domain}',

	'notice.admin'   => 'Please ask an administrator to activate it.',
	'notice.invalid' => 'The license of {plugin} is not valid on {domain}.',
	'notice.missing' => '{plugin} is not activated on {domain}.',

	'reason.disabled' => 'The license key has been disabled.',
	'reason.expired'  => 'The license key has expired.',
	'reason.invalid'  => 'The license key is not valid.',

	'error.activate'   => 'The license could not be activated: {error}',
	'error.confirm'    => 'Please confirm the deactivation.',
	'error.domain'     => 'The domain of the site could not be detected.',
	'error.exempt'     => 'No license key is needed on this host.',
	'error.key'        => 'Please enter your license key.',
	'error.missing'    => 'There is no license to deactivate.',
	'error.offline'    => 'The license server could not be reached. Please try again later.',
	'error.permission' => 'Only administrators can activate and deactivate licenses.',
	'error.storage'    => 'The license could not be saved in {file}. Please check the file permissions.',

	'success.activate'   => 'The license has been activated',
	'success.deactivate' => 'The license has been deactivated',
];
