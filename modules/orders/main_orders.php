<?php
require_once __DIR__ . '/../../lib/db_helpers.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/logger.php';

function main_orders_get_by_id($connection, $orderId)
{
    $sql = 'SELECT * FROM hksxq_orders WHERE id = ? LIMIT 1';
    $rows = db_query_select($connection, $sql, array($orderId));
    if ($rows === false || count($rows) === 0) {
        return null;
    }
    return $rows[0];
}

function main_orders_append_payment_history($order, $fieldName, $event)
{
    $history = array();
    if (!empty($order[$fieldName]) && is_valid_json($order[$fieldName])) {
        $history = json_decode($order[$fieldName], true);
        if (!is_array($history)) {
            $history = array();
        }
    }
    $history[] = $event;
    return json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function main_orders_update_fields($connection, $orderId, $fields)
{
    $sqlParts = array();
    $params = array();
    foreach ($fields as $field => $value) {
        $sqlParts[] = $field . ' = ?';
        $params[] = $value;
    }
    $params[] = $orderId;
    $sql = 'UPDATE hksxq_orders SET ' . implode(', ', $sqlParts) . ', date_last_update = NOW() WHERE id = ?';
    $result = db_query_execute($connection, $sql, $params);
    return $result !== false;
}
