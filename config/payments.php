<?php
// Конфигурация платёжных систем.

return array(
    'prodamus' => array(
        // Два секрета для разных аккаунтов (например, два ИП). Ключ выбирается по shop_id, если нужно.
        'secret_main' => 'prodamus_secret_main',
        'secret_alt' => 'prodamus_secret_alt',
        // Валюта по умолчанию (если провайдер не передал валюту или нужно пересчитать в RUB).
        'default_currency' => 'RUB'
    ),
    'tbank_acq' => array(
        'password' => 'tbank_acquiring_password',
        'shop_id' => 'tbank_shop_id'
    ),
    'tbank_installment' => array(
        'base_url' => 'https://forma.tinkoff.ru/api/partners/v2',
        'partner_id' => 'partner_id',
        'api_key' => 'partner_api_key',
        'auto_commit' => true,
        'auth_login' => 'basic_login',
        'auth_password' => 'basic_password'
    )
);
