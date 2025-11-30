<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Создает подключение к MySQL с использованием mysqli.
 * Возвращает объект mysqli или завершается с ошибкой.
 */
function db_get_connection()
{
    static $connection = null;
    if ($connection !== null) {
        return $connection;
    }

    $config = require __DIR__ . '/../config/db.php';
    $connection = new mysqli($config['host'], $config['user'], $config['password'], $config['dbname']);
    if ($connection->connect_error) {
        die('Ошибка подключения к БД: ' . $connection->connect_error);
    }
    $connection->set_charset($config['charset']);
    return $connection;
}

/**
 * Подключение ко второй базе данных (credit).
 */
function db_get_credit_connection()
{
    static $connection = null;
    if ($connection !== null) {
        return $connection;
    }

    $config = require __DIR__ . '/../config/db_credit.php';
    $connection = new mysqli($config['host'], $config['user'], $config['password'], $config['dbname']);
    if ($connection->connect_error) {
        die('Ошибка подключения к кредитной БД: ' . $connection->connect_error);
    }
    $connection->set_charset($config['charset']);
    return $connection;
}
