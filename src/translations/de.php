<?php

return [
	'name' => 'oweb studio Lizenz',

	'status.active'  => 'Aktiv',
	'status.exempt'  => 'Testumgebung',
	'status.invalid' => 'Ungültige Lizenz',
	'status.missing' => 'Bitte aktivieren',

	'button.activate'   => 'Aktivieren',
	'button.deactivate' => 'Deaktivieren',
	'button.license'    => 'Lizenz',

	'dialog.active'    => '<strong>{plugin}</strong> ist für <strong>{domain}</strong> aktiviert.',
	'dialog.admin'     => 'Nur Administratoren können Lizenzen aktivieren.',
	'dialog.domain'    => 'Die Lizenz wird für <strong>{domain}</strong> aktiviert.',
	'dialog.exempt'    => '<strong>{plugin}</strong> braucht auf <strong>{host}</strong> keinen Lizenzschlüssel, weil es eine lokale oder Staging-Seite ist. Aktiviere deinen Schlüssel auf der Live-Seite.',
	'dialog.invalid'   => 'Die Lizenz von <strong>{plugin}</strong> ist nicht mehr gültig: {reason}',
	'dialog.key'       => 'Lizenzschlüssel: {key}',
	'dialog.other'     => 'Diese Lizenz ist für <strong>{domain}</strong> aktiviert. Eine Aktivierung hier verbraucht eine weitere Aktivierung des Schlüssels.',
	'dialog.product'   => 'Produkt: {product}',
	'dialog.validated' => 'Zuletzt geprüft: {date}',

	'field.key'             => 'Lizenzschlüssel',
	'field.key.buy'         => '<a href="{url}" target="_blank">Lizenz kaufen &rarr;</a>',
	'field.key.help'        => 'Du findest den Schlüssel in der E-Mail zu deinem Kauf.',
	'field.deactivate'      => 'Deaktivieren',
	'field.deactivate.help' => 'Andere oweb studio Plugins mit demselben Schlüssel werden auf dieser Seite ebenfalls deaktiviert.',
	'field.deactivate.text' => 'Aktivierung von {domain} entfernen',

	'notice.admin'   => 'Bitte einen Administrator, sie zu aktivieren.',
	'notice.invalid' => 'Die Lizenz von {plugin} ist auf {domain} nicht gültig.',
	'notice.missing' => '{plugin} ist auf {domain} nicht aktiviert.',

	'reason.disabled' => 'Der Lizenzschlüssel wurde deaktiviert.',
	'reason.expired'  => 'Der Lizenzschlüssel ist abgelaufen.',
	'reason.invalid'  => 'Der Lizenzschlüssel ist ungültig.',

	'error.activate'   => 'Die Lizenz konnte nicht aktiviert werden: {error}',
	'error.confirm'    => 'Bitte bestätige die Deaktivierung.',
	'error.domain'     => 'Die Domain der Seite konnte nicht erkannt werden.',
	'error.exempt'     => 'Auf diesem Host wird kein Lizenzschlüssel benötigt.',
	'error.key'        => 'Bitte gib deinen Lizenzschlüssel ein.',
	'error.missing'    => 'Es gibt keine Lizenz zum Deaktivieren.',
	'error.offline'    => 'Der Lizenzserver ist nicht erreichbar. Bitte versuche es später noch einmal.',
	'error.permission' => 'Nur Administratoren können Lizenzen aktivieren und deaktivieren.',
	'error.storage'    => 'Die Lizenz konnte nicht in {file} gespeichert werden. Bitte prüfe die Dateirechte.',

	'success.activate'   => 'Die Lizenz wurde aktiviert',
	'success.deactivate' => 'Die Lizenz wurde deaktiviert',
];
