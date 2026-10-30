<?php

return [
	'name' => 'oweb studio lisansı',

	'status.active'  => 'Etkin',
	'status.exempt'  => 'Test ortamı',
	'status.invalid' => 'Geçersiz lisans',
	'status.missing' => 'Lütfen etkinleştirin',

	'button.activate'   => 'Etkinleştir',
	'button.deactivate' => 'Devre dışı bırak',
	'button.license'    => 'Lisans',

	'dialog.active'    => '<strong>{plugin}</strong>, <strong>{domain}</strong> için etkin.',
	'dialog.admin'     => 'Lisansları sadece yöneticiler etkinleştirebilir.',
	'dialog.domain'    => 'Lisans <strong>{domain}</strong> için etkinleştirilecek.',
	'dialog.exempt'    => '<strong>{plugin}</strong>, <strong>{host}</strong> üzerinde lisans anahtarı gerektirmez, çünkü bu yerel veya staging bir site. Anahtarınızı canlı sitede etkinleştirin.',
	'dialog.invalid'   => '<strong>{plugin}</strong> lisansı artık geçerli değil: {reason}',
	'dialog.key'       => 'Lisans anahtarı: {key}',
	'dialog.other'     => 'Bu lisans <strong>{domain}</strong> için etkin. Burada etkinleştirmek anahtarın bir etkinleştirme hakkını daha kullanır.',
	'dialog.product'   => 'Ürün: {product}',
	'dialog.validated' => 'Son doğrulama: {date}',

	'field.key'             => 'Lisans anahtarı',
	'field.key.buy'         => '<a href="{url}" target="_blank">Lisans satın al &rarr;</a>',
	'field.key.help'        => 'Anahtarı satın alma e-postanızda bulabilirsiniz.',
	'field.deactivate'      => 'Devre dışı bırak',
	'field.deactivate.help' => 'Aynı anahtarı kullanan diğer oweb studio eklentileri de bu sitede devre dışı kalır.',
	'field.deactivate.text' => 'Etkinleştirmeyi {domain} üzerinden kaldır',

	'notice.admin'   => 'Etkinleştirmesi için bir yöneticiye başvurun.',
	'notice.invalid' => '{plugin} lisansı {domain} üzerinde geçerli değil.',
	'notice.missing' => '{plugin}, {domain} üzerinde etkinleştirilmemiş.',

	'reason.disabled' => 'Lisans anahtarı devre dışı bırakılmış.',
	'reason.expired'  => 'Lisans anahtarının süresi dolmuş.',
	'reason.invalid'  => 'Lisans anahtarı geçerli değil.',

	'error.activate'   => 'Lisans etkinleştirilemedi: {error}',
	'error.confirm'    => 'Lütfen devre dışı bırakmayı onaylayın.',
	'error.domain'     => 'Sitenin alan adı tespit edilemedi.',
	'error.exempt'     => 'Bu sunucuda lisans anahtarı gerekmiyor.',
	'error.key'        => 'Lütfen lisans anahtarınızı girin.',
	'error.missing'    => 'Devre dışı bırakılacak bir lisans yok.',
	'error.offline'    => 'Lisans sunucusuna ulaşılamadı. Lütfen daha sonra tekrar deneyin.',
	'error.permission' => 'Lisansları sadece yöneticiler etkinleştirebilir ve devre dışı bırakabilir.',
	'error.storage'    => 'Lisans {file} dosyasına kaydedilemedi. Lütfen dosya izinlerini kontrol edin.',

	'success.activate'   => 'Lisans etkinleştirildi',
	'success.deactivate' => 'Lisans devre dışı bırakıldı',
];
