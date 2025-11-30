<?php
// Конфигурация интеграции с Атол (заглушка). В бою заменить на реальные данные.

return array(
    'enabled' => ATOL_ENABLED,
    'base_url' => 'https://online.atol.ru/possystem/v4',
    'login' => 'atol_login',
    'password' => 'atol_password',
    'group_code' => 'group_code',
    'inn' => '0000000000',
    'sno' => 'osn',
    'vat' => 'vat20',
    'payment_address' => 'https://perexodvtak.ru',
    // Настройки таймаутов и повторов.
    'timeout' => 15,
    'retries' => 2
);
