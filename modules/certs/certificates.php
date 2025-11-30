<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../orders/cert_orders.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Обработка платежа для заказа сертификата.
 */
function certs_handle_payment_result($orderId, $paymentProvider, $paymentStatus, $paidAmount, $paidCurrency, $rawPayloadJson, $connection)
{
    $order = cert_orders_get_by_id($connection, $orderId);
    if ($order === null) {
        logger_error('Сертификат: заказ не найден id=' . $orderId, 'payments');
        return array('success' => false, 'error' => 'Заказ не найден');
    }

    $historyPayload = cert_orders_append_payload($order, array(
        'status' => $paymentStatus,
        'provider' => $paymentProvider,
        'amount' => $paidAmount,
        'currency' => $paidCurrency,
        'payload' => $rawPayloadJson,
        'time' => date(DATE_FORMAT_FULL)
    ));

    $statusToSet = $order['status'];
    if ($paymentStatus === 'success') {
        $statusToSet = 'paid';
    }

    if (!cert_orders_update_payment_fields($connection, $orderId, $paymentProvider, '', $paymentStatus, $historyPayload)) {
        logger_error('Сертификат: не удалось обновить заказ', 'payments');
        return array('success' => false, 'error' => 'Не удалось обновить заказ');
    }

    $cert = certs_get_certificate_by_order($connection, $orderId);
    if ($cert !== null) {
        return array('success' => true, 'certificate' => $cert);
    }

    if ($paymentStatus !== 'success') {
        return array('success' => true, 'certificate' => null);
    }

    $certCreate = certs_create_certificate($connection, $order, $paymentProvider, $paidAmount, $rawPayloadJson);
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

function certs_create_certificate($connection, $order, $paymentProvider, $paidAmount, $rawPayloadJson)
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
        'payload' => $rawPayloadJson,
        'paid_amount' => $paidAmount
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
        $paymentProvider,
        '',
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

function certs_generate_password()
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pass = '';
    for ($i = 0; $i < 8; $i++) {
        $pass .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $pass;
}
