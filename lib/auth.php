<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/helpers.php';

/**
 * Простая авторизация по токену в заголовке Authorization: Bearer.
 */
function require_internal_auth()
{
    $token = get_bearer_token();
    if ($token === '' || $token !== API_INTERNAL_TOKEN) {
        response_error('Недостаточно прав или неверный токен', 401);
    }
}
