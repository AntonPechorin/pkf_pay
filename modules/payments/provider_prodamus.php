<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/response.php';

/**
 * Обработчик Prodamus PayForm.
 */
function provider_prodamus_handle($headers, $rawBody)
{
    $config = require __DIR__ . '/../../config/payments.php';
    $bodyData = json_decode($rawBody, true);
    if ($bodyData === null) {
        logger_error('Prodamus: некорректный JSON', 'payments');
        return false;
    }

    $signHeader = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'sign') {
            $signHeader = $value;
        }
    }

    $expectedSign = provider_prodamus_signature($rawBody, $config['prodamus']);
    if ($signHeader === '' || $signHeader !== $expectedSign) {
        logger_error('Prodamus: подпись не совпала', 'payments');
        return false;
    }

    if (!isset($bodyData['payment_status']) || $bodyData['payment_status'] !== 'success') {
        logger_info('Prodamus: статус не success, пропускаем', 'payments');
        return false;
    }

    if (!isset($bodyData['order_num'])) {
        logger_error('Prodamus: отсутствует order_num', 'payments');
        return false;
    }

    $orderIdParts = explode('-', $bodyData['order_num']);
    $orderId = intval($orderIdParts[0]);
    $amount = isset($bodyData['amount']) ? floatval($bodyData['amount']) : 0;
    $currency = isset($bodyData['currency']) ? $bodyData['currency'] : $config['prodamus']['default_currency'];

    $event = array(
        'provider' => 'prodamus',
        'provider_type' => 'payform',
        'order_id' => $orderId,
        'order_type' => 'course',
        'order_raw' => $bodyData['order_num'],
        'status_raw' => 'success',
        'status' => 'success',
        'amount' => $amount,
        'amount_rub' => $amount,
        'currency' => $currency,
        'external_payment_id' => isset($bodyData['payment_id']) ? $bodyData['payment_id'] : '',
        'is_partial' => false,
        'is_refund' => false,
        'provider_commission' => isset($bodyData['fee']) ? floatval($bodyData['fee']) : 0,
        'internal_diff_amount' => 0,
        'created_at_provider' => isset($bodyData['created_at']) ? $bodyData['created_at'] : '',
        'metadata' => $bodyData,
        'payload_raw' => $rawBody
    );

    // Определение частичной оплаты и разниц можно расширить при наличии данных о курсе.
    if (isset($bodyData['is_partial']) && $bodyData['is_partial']) {
        $event['is_partial'] = true;
    }

    return $event;
}

function provider_prodamus_signature($rawBody, $config)
{
    $secret = $config['secret_main'];
    return hash_hmac('sha256', $rawBody, $secret);
}
