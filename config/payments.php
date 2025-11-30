<?php
// Конфигурация платёжных систем.

return array(
    'prodamus' => array(
        // Массив секретных ключей из личного кабинета Prodamus (URL и секретный ключ платежной страницы).
        // Проверка подписи выполняется по каждому ключу последовательно до совпадения.
        'secret_keys' => array(
            'prodamus_secret_main',
            'prodamus_secret_alt'
        ),
        // Код системы (sys) из настроек платежной страницы.
        'sys' => 'prostovtak',
        // URL уведомлений (webhook), который указывается при формировании ссылки.
        'notification_url' => 'https://api.perexodvtak.ru/pay/prodamus',
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
