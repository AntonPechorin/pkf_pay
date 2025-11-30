<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../orders/cert_orders.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Обработка платежа для заказа сертификата по унифицированному событию.
 */
function certs_handle_payment_result($event, $connection)
{
    $orderId = intval($event['order_id']);
    $order = cert_orders_get_by_id($connection, $orderId);
    if ($order === null) {
        logger_error('Сертификат: заказ не найден id=' . $orderId, 'payments');
        return array('success' => false, 'error' => 'Заказ не найден');
    }

    $historyPayload = cert_orders_append_payload($order, array(
        'provider' => $event['provider'],
        'status_raw' => $event['status_raw'],
        'status' => $event['status'],
        'amount' => $event['amount'],
        'currency' => $event['currency'],
        'external_payment_id' => $event['external_payment_id'],
        'metadata' => $event['metadata'],
        'created_at_provider' => isset($event['created_at_provider']) ? $event['created_at_provider'] : '',
        'payload' => $event['payload_raw'],
        'time' => date(DATE_FORMAT_FULL)
    ));

    $statusToSet = isset($order['status']) ? $order['status'] : 'pending_payment';
    if ($event['status'] === 'success') {
        $statusToSet = 'paid';
    } elseif ($event['status'] === 'cancelled' || $event['status'] === 'failed') {
        $statusToSet = 'payment_failed';
    }

    $paymentStatus = isset($event['status_raw']) ? $event['status_raw'] : $event['status'];
    $externalPaymentId = isset($event['external_payment_id']) ? $event['external_payment_id'] : '';

    if (!cert_orders_update_status_and_payment($connection, $orderId, $event['provider'], $externalPaymentId, $paymentStatus, $statusToSet, $historyPayload)) {
        logger_error('Сертификат: не удалось обновить заказ', 'payments');
        return array('success' => false, 'error' => 'Не удалось обновить заказ');
    }

    $cert = certs_get_certificate_by_order($connection, $orderId);
    if ($cert !== null) {
        return array('success' => true, 'certificate' => $cert);
    }

    if ($event['status'] !== 'success') {
        return array('success' => true, 'certificate' => null);
    }

    $certCreate = certs_create_certificate($connection, $order, $event);
    if ($certCreate === false) {
        return array('success' => false, 'error' => 'Не удалось создать сертификат');
    }

    return array('success' => true, 'certificate' => $certCreate);
}

function certs_get_certificate_by_order($connection, $orderId)
{
    $sql = 'SELECT * FROM hksxq_certificates WHERE order_id = ? LIMIT 1';
    $rows = db_query_select($connection, $sql, array($orderId));
    if ($rows === false || count($rows) === 0) {
        return null;
    }
    return $rows[0];
}

function certs_create_certificate($connection, $order, $event)
{
    $code = 1000000 + intval($order['id']);
    $password = certs_generate_password();
    $now = date(DATE_FORMAT_FULL);
    $validUntil = date(DATE_FORMAT_FULL, strtotime('+' . CERT_VALID_DAYS . ' days'));
    $comment = array(
        'utm_source' => $order['utm_source'],
        'utm_medium' => $order['utm_medium'],
        'utm_campaign' => $order['utm_campaign'],
        'utm_content' => $order['utm_content'],
        'utm_term' => $order['utm_term'],
        'utm_referrer' => $order['utm_referrer'],
        'ya_cid' => $order['ya_cid'],
        'google_cid' => $order['google_cid'],
        'payment_provider' => $event['provider'],
        'payment_status' => $event['status'],
        'payment_status_raw' => isset($event['status_raw']) ? $event['status_raw'] : '',
        'external_payment_id' => isset($event['external_payment_id']) ? $event['external_payment_id'] : '',
        'payment_init' => isset($event['metadata']['payment_init']) ? $event['metadata']['payment_init'] : '',
        'commission_percent' => isset($event['metadata']['commission']) ? floatval($event['metadata']['commission']) : 0,
        'commission_sum' => isset($event['metadata']['commission_sum']) ? floatval($event['metadata']['commission_sum']) : 0,
        'currency' => $event['currency'],
        'sum' => $event['amount'],
        'currency_sum' => isset($event['metadata']['currency_sum']) ? $event['metadata']['currency_sum'] : '',
        'currency_commission_sum' => isset($event['metadata']['currency_commission_sum']) ? $event['metadata']['currency_commission_sum'] : '',
        'params' => certs_extract_params($event['metadata']),
        'payload' => $event['payload_raw']
    );

    $sql = 'INSERT INTO hksxq_certificates (order_id, idu, code, amount_initial, amount_remaining, currency, status, valid_from, valid_until, created, origin, external_ref, email_snapshot, name_snapshot, pass_cert, md5_pass_cert, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $result = db_query_execute($connection, $sql, array(
        intval($order['id']),
        isset($order['product_id']) ? $order['product_id'] : '',
        $code,
        intval($order['amount']),
        intval($order['amount']),
        'RUB',
        'active',
        $now,
        $validUntil,
        $now,
        $event['provider'],
        isset($event['external_payment_id']) ? $event['external_payment_id'] : '',
        $order['email'],
        $order['name'],
        $password,
        md5($password),
        json_encode($comment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ));

    if ($result === false) {
        logger_error('Сертификат: ошибка вставки', 'payments');
        return false;
    }

    return certs_get_certificate_by_order($connection, $order['id']);
}

function certs_extract_params($metadata)
{
    $params = array();
    if (!is_array($metadata)) {
        return $params;
    }
    foreach ($metadata as $key => $value) {
        if (strpos($key, '_param_') === 0 || strpos($key, 'utm_') === 0 || $key === 'ref' || $key === 'payment_type') {
            $params[$key] = $value;
        }
    }
    return $params;
}

function certs_generate_password()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pass = '';
    for ($i = 0; $i < 8; $i++) {
        $pass .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $pass;
}
