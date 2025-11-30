<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/helpers.php';

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
