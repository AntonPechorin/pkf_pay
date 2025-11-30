<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/main_orders.php';

/**
 * GET /api/orders/{id}
 */
function api_get_order($id)
{
    require_internal_auth();
    $connection = db_get_connection();
    $order = main_orders_get_by_id($connection, intval($id));
    if ($order === null) {
        response_error('Заказ не найден', 404);
    }
    response_success($order);
}

/**
 * POST /api/orders
 * Пример создания черновика заказа.
 */
function api_post_order($body)
{
    require_internal_auth();
    if (!is_array($body)) {
        response_error('Некорректный JSON', 400);
    }
    $name = isset($body['name']) ? sanitize_string($body['name']) : '';
    $email = isset($body['email']) ? sanitize_string($body['email']) : '';
    $idu = isset($body['idu']) ? sanitize_string($body['idu']) : '';
    $price = isset($body['price']) ? intval($body['price']) : 0;

    if ($name === '' || $email === '' || $idu === '' || $price <= 0) {
        response_error('Не заполнены обязательные поля', 400);
    }

    $connection = db_get_connection();
    $sql = 'INSERT INTO hksxq_orders (name, email, idu, poten, fakt, comment, date_last_update, date_last_cron) VALUES (?, ?, ?, ?, 0, ?, NOW(), NOW())';
    $result = db_query_execute($connection, $sql, array($name, $email, $idu, $price, 'Создан через API'));
    if ($result === false) {
        response_error('Не удалось создать заказ', 500);
    }

    $newId = $result['insert_id'];
    $order = main_orders_get_by_id($connection, $newId);
    response_success($order);
}
