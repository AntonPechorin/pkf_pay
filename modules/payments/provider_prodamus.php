<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/prodamus_hmac.php';
require_once __DIR__ . '/../../lib/db_connection.php';
require_once __DIR__ . '/../orders/cert_orders.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Обработчик Prodamus PayForm.
 * Принимает POST multipart/form-data (основной кейс по докам) или JSON, проверяет подпись Sign и
 * возвращает унифицированное событие для dispatcher.
 */
function provider_prodamus_handle($headers, $rawBody)
{
    $config = require __DIR__ . '/../../config/payments.php';
    $channel = 'payments/prodamus';

    $data = array();
    if (!empty($_POST)) {
        $data = $_POST;
    } else {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $payloadForLog = !empty($_POST) ? json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $rawBody;
    logger_info('Prodamus webhook сырой: ' . $payloadForLog, $channel);

    if (!is_array($data) || count($data) === 0) {
        logger_error('Prodamus: пустое тело запроса', $channel);
        response_error('Некорректные данные', 400);
    }

    $signHeader = provider_prodamus_extract_sign($headers);
    if ($signHeader === '') {
        logger_error('Prodamus: отсутствует заголовок Sign', $channel);
        response_error('Отсутствует подпись', 400);
    }

    if (!isset($config['prodamus']['secret_keys']) || !is_array($config['prodamus']['secret_keys'])) {
        logger_error('Prodamus: не настроены secret_keys', $channel);
        response_error('Конфигурация Prodamus отсутствует', 500);
    }

    $signatureValid = false;
    foreach ($config['prodamus']['secret_keys'] as $secretKey) {
        if (Hmac::verify($data, $secretKey, $signHeader)) {
            $signatureValid = true;
            break;
        }
    }

    if (!$signatureValid) {
        logger_error('Prodamus: подпись не совпала', $channel);
        response_error('Неверная подпись', 400);
    }

    if (!isset($data['order_num'])) {
        logger_error('Prodamus: отсутствует order_num', $channel);
        response_error('Отсутствует номер заказа', 400);
    }

    $orderNumRaw = $data['order_num'];

    $primaryPart = $orderNumRaw;
    if (strpos($orderNumRaw, '-') !== false) {
        $parts = explode('-', $orderNumRaw);
        $primaryPart = $parts[0];
    }
    $primaryNumeric = intval($primaryPart);

    $orderType = 'course';
    $internalOrderId = $primaryNumeric;

    $connection = db_get_connection();

    $certOrder = cert_orders_get_by_external_order_num($connection, $orderNumRaw);
    if ($certOrder === null && $primaryPart !== $orderNumRaw) {
        $certOrder = cert_orders_get_by_external_order_num($connection, $primaryPart);
    }

    if ($certOrder !== null) {
        $orderType = 'certificate';
        $internalOrderId = intval($certOrder['id']);

        logger_info(
            'Prodamus: найден заказ сертификата по external_order_num=' . $orderNumRaw . ' (id=' . $internalOrderId . ')',
            $channel
        );
    } else {
        $orderType = 'course';
        $internalOrderId = $primaryNumeric;

        logger_info(
            'Prodamus: сертификат по external_order_num не найден, обрабатываем как курс id=' . $internalOrderId,
            $channel
        );
    }

    $statusRaw = isset($data['payment_status']) ? $data['payment_status'] : '';
    $statusNormalized = provider_prodamus_normalize_status($statusRaw);
    if ($statusNormalized === 'unknown') {
        logger_error('Prodamus: неизвестный статус ' . $statusRaw, $channel);
    }

    $amount = isset($data['sum']) ? floatval($data['sum']) : 0;
    $currency = isset($data['currency']) ? $data['currency'] : $config['prodamus']['default_currency'];
    $amountRub = $amount;
    if (strtolower($currency) !== 'rub') {
        $amountRub = $amount; // TODO: добавить конвертацию валют при наличии курса.
    }

    $metadata = $data;
    $isPartial = false;
    if (isset($metadata['products']) && is_array($metadata['products'])) {
        foreach ($metadata['products'] as $product) {
            if (isset($product['name']) && strpos($product['name'], 'Частичная оплата') !== false) {
                $isPartial = true;
            }
        }
    }
    if (isset($metadata['_param_partial']) && intval($metadata['_param_partial']) === 1) {
        $isPartial = true;
    }

    $event = array(
        'provider' => 'prodamus',
        'provider_type' => 'payform',
        'order_id' => $internalOrderId,
        'order_type' => $orderType,
        'order_raw' => $orderNumRaw,
        'status_raw' => $statusRaw,
        'status' => $statusNormalized,
        'amount' => $amount,
        'amount_rub' => $amountRub,
        'currency' => $currency,
        'external_payment_id' => isset($data['order_id']) ? $data['order_id'] : '',
        'is_partial' => $isPartial,
        'is_refund' => false,
        'provider_commission' => isset($data['commission_sum']) ? floatval($data['commission_sum']) : 0,
        'internal_diff_amount' => 0,
        'created_at_provider' => isset($data['date']) ? $data['date'] : '',
        'metadata' => $metadata,
        'payload_raw' => !empty($payloadForLog) ? $payloadForLog : $rawBody
    );

    logger_info('Prodamus: событие подготовлено ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $channel);
    return $event;
}

function provider_prodamus_extract_sign($headers)
{
    $signHeader = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'sign') {
            $signHeader = $value;
        }
    }
    return $signHeader;
}

function provider_prodamus_normalize_status($statusRaw)
{
    $status = 'unknown';
    if ($statusRaw === 'success') {
        $status = 'success';
    } elseif ($statusRaw === 'order_canceled') {
        $status = 'cancelled';
    } elseif ($statusRaw === 'order_denied') {
        $status = 'failed';
    }
    return $status;
}
