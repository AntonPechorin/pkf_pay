<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/logger.php';

/**
 * Начать транзакцию.
 */
function db_begin_transaction($connection)
{
    if (!$connection->begin_transaction()) {
        logger_error('Не удалось начать транзакцию: ' . $connection->error, 'db');
        return false;
    }
    return true;
}

/**
 * Зафиксировать транзакцию.
 */
function db_commit($connection)
{
    if (!$connection->commit()) {
        logger_error('Не удалось зафиксировать транзакцию: ' . $connection->error, 'db');
        return false;
    }
    return true;
}

/**
 * Откатить транзакцию.
 */
function db_rollback($connection)
{
    if (!$connection->rollback()) {
        logger_error('Не удалось откатить транзакцию: ' . $connection->error, 'db');
        return false;
    }
    return true;
}

/**
 * Безопасный select с подготовкой запроса.
 */
function db_query_select($connection, $sql, $params)
{
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        logger_error('Ошибка подготовки запроса: ' . $connection->error . ' SQL: ' . $sql, 'db');
        return false;
    }
    if (!empty($params)) {
        $types = '';
        $values = array();
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param) || is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        $stmt->bind_param($types, ...$values);
    }
    if (!$stmt->execute()) {
        logger_error('Ошибка выполнения запроса: ' . $stmt->error . ' SQL: ' . $sql, 'db');
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $data = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $stmt->close();
    return $data;
}

/**
 * Безопасный insert/update/delete.
 */
function db_query_execute($connection, $sql, $params)
{
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        logger_error('Ошибка подготовки запроса: ' . $connection->error . ' SQL: ' . $sql, 'db');
        return false;
    }
    if (!empty($params)) {
        $types = '';
        $values = array();
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param) || is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }
        $stmt->bind_param($types, ...$values);
    }
    if (!$stmt->execute()) {
        logger_error('Ошибка выполнения запроса: ' . $stmt->error . ' SQL: ' . $sql, 'db');
        $stmt->close();
        return false;
    }
    $insertId = $stmt->insert_id;
    $affected = $stmt->affected_rows;
    $stmt->close();
    return array('insert_id' => $insertId, 'affected_rows' => $affected);
}

/**
 * Экранирование строк.
 */
function db_escape($connection, $value)
{
    return $connection->real_escape_string($value);
}
