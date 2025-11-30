<?php
// Общие настройки проекта API для "Переход в Так".
// Файл размещается вне public_html и подключается через __DIR__.

// Включить режим отладки (true/false). В продакшне держать false.
define('APP_DEBUG', true);

// Корневая директория проекта (выше public_html).
define('APP_ROOT', realpath(__DIR__ . '/..'));

// Каталог логов.
define('LOG_DIR', APP_ROOT . '/logs');

// Таймзона проекта.
date_default_timezone_set('Europe/Moscow');

// Токен для внутренних API (пример). В бою заменить на настоящий.
define('API_INTERNAL_TOKEN', 'change_me_internal_token');

// Уровень подробности логирования платежей.
// Можно использовать: info, debug, error. Пока оставим debug для подробных трассировок.
define('PAYMENT_LOG_LEVEL', 'debug');

// Общий формат даты-времени для логов и ответов.
define('DATE_FORMAT_FULL', 'Y-m-d H:i:s');

// Настройка включения Атола. Если false, чека не отправляем, только логируем подготовку.
define('ATOL_ENABLED', false);

// Срок действия сертификатов (в днях).
define('CERT_VALID_DAYS', 365);

// Базовый сдвиг номера заказа сертификата для внешних систем (Prodamus).
// Внешний номер заказа сертификата = BASE_CERT_ORDER_NUM + id.
define('BASE_CERT_ORDER_NUM', 500000000);

// Кодировка БД (по умолчанию utf8mb4 для MySQL 5.7).
define('DB_CHARSET', 'utf8mb4');
