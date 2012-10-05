<?php

class ShopLogger
{
    const LOG_FILE_PATH = './files/shop_log.txt';
	const XE_CORE_DEBUG_MESSAGE_PATH = './files/_debug_message.php';
	const XE_CORE_DEBUG_DB_QUERY_PATH = './files/_debug_db_query.php';

    public static function log($message)
    {
        $timestamp = date("y.m.d H:i:s");
        $log_message = $timestamp . "\t" . $message . PHP_EOL;
        FileHandler::writeFile(self::LOG_FILE_PATH, $log_message, "a");
    }
}
