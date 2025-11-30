<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';

/**
 * Обработчик вебхуков Т-Банк эквайринг.
 */
function provider_tbank_acquiring_handle($headers, $rawBody)
{
    $config = require __DIR__ . '/../../config/payments.php';
    $body = json_decode($rawBody, true);
    if ($body === null) {
        logger_error('T-Bank acquiring: некорректный JSON', 'payments');
        return false;
    }

    if (!provider_tbank_acquiring_check_token($body, $config['tbank_acq'])) {
        logger_error('T-Bank acquiring: неверный токен', 'payments');
        return false;
    }

    $statusRaw = isset($body['Status']) ? $body['Status'] : '';
    if ($statusRaw === 'AUTHORIZED') {
        logger_info('T-Bank acquiring: статус AUTHORIZED, подтверждение не требуется', 'payments');
        return false;
    }

    if ($statusRaw !== 'CONFIRMED') {
        logger_error('T-Bank acquiring: неподдерживаемый статус ' . $statusRaw, 'payments');
        return false;
    }

    $orderIdRaw = isset($body['OrderId']) ? $body['OrderId'] : '';
    $orderId = 0;
    $orderType = 'course';
    if ($orderIdRaw !== '') {
        $parts = explode('-', $orderIdRaw);
        $orderId = intval($parts[0]);
        if (strlen($parts[0]) > 0 && substr($parts[0], 0, 1) === '0') {
            $orderType = 'jshopping';
        }
    }

    $amount = isset($body['Amount']) ? floatval($body['Amount']) / 100 : 0;

    $event = array(
        'provider' => 'tbank_acq',
        'provider_type' => 'acquiring',
        'order_id' => $orderId,
        'order_type' => $orderType,
        'order_raw' => $orderIdRaw,
        'status_raw' => $statusRaw,
        'status' => 'success',
        'amount' => $amount,
        'amount_rub' => $amount,
        'currency' => 'RUB',
        'external_payment_id' => isset($body['PaymentId']) ? $body['PaymentId'] : '',
        'is_partial' => false,
        'is_refund' => false,
        'provider_commission' => 0,
        'internal_diff_amount' => 0,
        'created_at_provider' => isset($body['OrderCreateDate']) ? $body['OrderCreateDate'] : '',
        'metadata' => $body,
        'payload_raw' => $rawBody
    );

    return $event;
}

function provider_tbank_acquiring_check_token($body, $config)
{
    if (!isset($body['Token'])) {
        return false;
    }
    $tokenIncoming = $body['Token'];
    unset($body['Token']);
    $body['Password'] = $config['password'];
    ksort($body);
    $str = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tokenExpected = hash('sha256', $str);
    return $tokenIncoming === $tokenExpected;
}
