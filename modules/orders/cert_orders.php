<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../config/config.php';

function cert_orders_get_by_id($connection, $orderId)
{
    $sql = 'SELECT * FROM hksxq_cert_orders WHERE id = ? LIMIT 1';
    $rows = db_query_select($connection, $sql, array($orderId));
    if ($rows === false || count($rows) === 0) {
        return null;
    }
    return $rows[0];
}

function cert_orders_update_payment_fields($connection, $orderId, $provider, $externalId, $status, $payload)
{
    $sql = 'UPDATE hksxq_cert_orders SET payment_provider = ?, payment_external_id = ?, payment_status = ?, payment_payload = ?, updated_at = NOW() WHERE id = ?';
    $result = db_query_execute($connection, $sql, array($provider, $externalId, $status, $payload, $orderId));
    return $result !== false;
}

function cert_orders_update_status_and_payment($connection, $orderId, $provider, $externalId, $paymentStatus, $orderStatus, $payload)
{
    $sql = 'UPDATE hksxq_cert_orders SET payment_provider = ?, payment_external_id = ?, payment_status = ?, status = ?, payment_payload = ?, updated_at = NOW() WHERE id = ?';
    $result = db_query_execute($connection, $sql, array($provider, $externalId, $paymentStatus, $orderStatus, $payload, $orderId));
    return $result !== false;
}

function cert_orders_set_external_order_num($connection, $orderId, $externalOrderNum)
{
    $sql = 'UPDATE hksxq_cert_orders SET external_order_num = ?, updated_at = NOW() WHERE id = ?';
    $result = db_query_execute($connection, $sql, array($externalOrderNum, $orderId));
    return $result !== false;
}

function cert_orders_calculate_external_order_num($orderId)
{
    return BASE_CERT_ORDER_NUM + intval($orderId);
}

function cert_orders_create($connection, $data)
{
    $sql = 'INSERT INTO hksxq_cert_orders (user_id, email, name, phone, product_id, amount, currency, status, payment_provider, payment_external_id, payment_status, payment_payload, utm_source, utm_medium, utm_campaign, utm_content, utm_term, utm_referrer, ya_cid, google_cid, order_source, created_at, updated_at, payment_method, external_order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)';
    $params = array(
        isset($data['user_id']) ? intval($data['user_id']) : 0,
        isset($data['email']) ? $data['email'] : '',
        isset($data['name']) ? $data['name'] : '',
        isset($data['phone']) ? $data['phone'] : '',
        isset($data['product_id']) ? $data['product_id'] : '',
        isset($data['amount']) ? intval($data['amount']) : 0,
        isset($data['currency']) ? $data['currency'] : 'RUB',
        isset($data['status']) ? $data['status'] : 'pending_payment',
        isset($data['payment_provider']) ? $data['payment_provider'] : '',
        isset($data['payment_external_id']) ? $data['payment_external_id'] : '',
        isset($data['payment_status']) ? $data['payment_status'] : '',
        isset($data['payment_payload']) ? $data['payment_payload'] : json_encode(array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        isset($data['utm_source']) ? $data['utm_source'] : '',
        isset($data['utm_medium']) ? $data['utm_medium'] : '',
        isset($data['utm_campaign']) ? $data['utm_campaign'] : '',
        isset($data['utm_content']) ? $data['utm_content'] : '',
        isset($data['utm_term']) ? $data['utm_term'] : '',
        isset($data['utm_referrer']) ? $data['utm_referrer'] : '',
        isset($data['ya_cid']) ? $data['ya_cid'] : '',
        isset($data['google_cid']) ? $data['google_cid'] : '',
        isset($data['order_source']) ? $data['order_source'] : '',
        isset($data['payment_method']) ? $data['payment_method'] : '',
        isset($data['external_order_num']) ? $data['external_order_num'] : 0
    );
    $result = db_query_execute($connection, $sql, $params);
    return $result !== false ? $result['insert_id'] : false;
}

function cert_orders_append_payload($order, $newEventPayload)
{
    $history = array();
    if (!empty($order['payment_payload']) && is_valid_json($order['payment_payload'])) {
        $history = json_decode($order['payment_payload'], true);
        if (!is_array($history)) {
            $history = array();
        }
    }
    $history[] = $newEventPayload;
    return json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
