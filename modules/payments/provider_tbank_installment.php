<?php
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/db_connection.php';

/**
 * Обработчик рассрочки Т-Банк (forma.tinkoff.ru).
 * В данной заготовке описана основная последовательность: получение вебхука, запрос /info, опциональный /commit.
 */
function provider_tbank_installment_handle($headers, $rawBody)
{
    $config = require __DIR__ . '/../../config/payments.php';
    $body = json_decode($rawBody, true);
    if ($body === null || !isset($body['id'])) {
        logger_error('T-Bank installment: некорректный JSON', 'payments');
        return false;
    }

    $applicationId = $body['id'];
    $info = provider_tbank_installment_get_info($applicationId, $config['tbank_installment']);
    if ($info === false) {
        logger_error('T-Bank installment: не удалось получить info', 'payments');
        return false;
    }

    $statusRaw = isset($info['status']) ? $info['status'] : '';
    $cooldown = isset($info['commit_cooldown']) ? $info['commit_cooldown'] : '';
    if ($cooldown !== '') {
        logger_info('T-Bank installment: охлаждение до ' . $cooldown, 'payments');
        return false;
    }

    $isCommitted = isset($info['committed']) ? (bool)$info['committed'] : false;
    if (!$isCommitted && isset($config['tbank_installment']['auto_commit']) && $config['tbank_installment']['auto_commit']) {
        $commitResult = provider_tbank_installment_commit($applicationId, $config['tbank_installment']);
        if ($commitResult === false) {
            logger_error('T-Bank installment: commit не выполнен', 'payments');
            return false;
        }
        $isCommitted = true;
    }

    if (!$isCommitted) {
        logger_info('T-Bank installment: не зафиксировано, ждем', 'payments');
        return false;
    }

    $orderIdRaw = isset($info['order_id']) ? $info['order_id'] : $applicationId;
    $parts = explode('-', $orderIdRaw);
    $orderId = intval($parts[0]);
    $orderAmount = isset($info['order_amount']) ? floatval($info['order_amount']) : 0;
    $transferAmount = isset($info['transfer_amount']) ? floatval($info['transfer_amount']) : $orderAmount;
    $diff = $orderAmount - $transferAmount;

    $event = array(
        'provider' => 'tbank_installment',
        'provider_type' => 'installment',
        'order_id' => $orderId,
        'order_type' => 'course',
        'order_raw' => $orderIdRaw,
        'status_raw' => $statusRaw,
        'status' => 'success',
        'amount' => $orderAmount,
        'amount_rub' => $orderAmount,
        'currency' => 'RUB',
        'external_payment_id' => $applicationId,
        'is_partial' => false,
        'is_refund' => false,
        'provider_commission' => 0,
        'internal_diff_amount' => $diff,
        'created_at_provider' => isset($info['created_at']) ? $info['created_at'] : '',
        'metadata' => $info,
        'payload_raw' => $rawBody
    );

    // Сохраняем info в кредитной базе (переписка legacy функционала).
    provider_tbank_installment_save_credit($applicationId, $info);

    return $event;
}

function provider_tbank_installment_get_info($applicationId, $config)
{
    $url = rtrim($config['base_url'], '/') . '/orders/' . urlencode($applicationId) . '/info';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $config['auth_login'] . ':' . $config['auth_password']);
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return false;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) {
        logger_error('T-Bank installment: info http code ' . $code, 'payments');
        return false;
    }
    $data = json_decode($response, true);
    if ($data === null) {
        return false;
    }
    return $data;
}

function provider_tbank_installment_commit($applicationId, $config)
{
    $url = rtrim($config['base_url'], '/') . '/orders/' . urlencode($applicationId) . '/commit';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $config['auth_login'] . ':' . $config['auth_password']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return false;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 && $code !== 409) {
        logger_error('T-Bank installment: commit http code ' . $code, 'payments');
        return false;
    }
    return true;
}

function provider_tbank_installment_save_credit($applicationId, $info)
{
    $connection = db_get_credit_connection();
    $sql = 'INSERT INTO credit (application_id, payload, created_at) VALUES (?, ?, NOW())';
    $payload = json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $result = db_query_execute($connection, $sql, array($applicationId, $payload));
    if ($result === false) {
        logger_error('T-Bank installment: не удалось сохранить кредитные данные', 'payments');
    }
}
