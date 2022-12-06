<?php
$start_time = microtime(true);
require_once 'config.php';
require_once 'telegram.php';

// +++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++++++++ sql.php ver.2.1 +++++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++
// dsn - data source name
// charset=utf8mb4 - не стандартная кодировка, которая должна быть установлена в DB, для записи кириллицы и эмодзи.
$dsn = 'mysql:host=' . DBCONFIG['host'] . ';port=' . DBCONFIG['port'] . ';dbname=' . DBCONFIG['db'] . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, DBCONFIG['user'], DBCONFIG['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sendServiceMessage("SQL connection error (sql.php): " . $e->getMessage());
    exit;
}

/**
 * Получаем массив конфигурационных данных.
 * @param string $key Ключ - должен совпадать с колонкой в таблице config.
 * @return int Возвращает значение по заданному ключу.
 */
function getConfigData(string $key): int
{
    global $pdo;
    $sql = "SELECT " . $key . " FROM config WHERE id = 0";
    try {
        $arr = $pdo->query($sql)->fetchAll(); // Выполнение запроса SELECT
        //sendServiceMessage("SQL Query:\n" . $sql);
        //sendServiceMessage(var_export($arr, true));
        //sendServiceMessage($key . " - " . $arr[0][$key]);
        return intval($arr[0][$key]);
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 SELECT error (sql.php: getConfigData):\n"
            . $sql . "\n---\n" . $e->getMessage());
        exit;
    }
}

/**
 * Сохранить параметр в баду данных.
 * @param string $key Ключ - должен совпадать с колонкой в таблице config.
 * @param int $value Значение.
 */
function setConfigData(string $key, int $value): void
{
    global $pdo;
    $sql = "UPDATE config SET " . $key . " = " . $value . " WHERE id = 0";
    //sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $query = $pdo->prepare($sql);
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 INSERT error (sql.php: setConfigData):\n" . $sql . "\n-- - \n" . $e->getMessage());
        exit;
    }
}

function saveUserAndMessage($inputMessage, $messageType): void
{
    global $pdo;
    $primaryKey = array_keys($inputMessage)[1];
    // Save user
    $sql = "INSERT IGNORE INTO users (id, username, first_name, is_bot) VALUES (" .
        (int)$inputMessage[$primaryKey]['from']['id'] . ", '" .
        $inputMessage[$primaryKey]['from']['username'] . "', '" .
        $inputMessage[$primaryKey]['from']['first_name'] . "', " .
        (int)$inputMessage[$primaryKey]['from']['is_bot'] .
        ") ON DUPLICATE KEY UPDATE msg = msg + 1";
    //sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $query = $pdo->prepare($sql); // Выполнение запроса INSERT & UPDATE
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 INSERT error (sql.php: saveUserAndMessage):\n"
            . $sql . "\n---\n" . $e->getMessage());
    }

    // Save message
    // Конкатенация всех ввозможных мест нахождения текста, если таких элементов нет, они будут пустыми.
    $text = $inputMessage['message']['text'] . $inputMessage['message']['caption'] . $inputMessage['callback_query']['data'];
    $_text = mb_strimwidth($text, 0, 255, "...");
    $_text = str_ireplace("'", "\'", $_text);

    $sql = "INSERT INTO messages(from_id, username, first_name, message, messageType) VALUES(" .
        (int)$inputMessage[$primaryKey]['from']['id'] . ", '" .
        $inputMessage[$primaryKey]['from']['username'] . "', '" .
        $inputMessage[$primaryKey]['from']['first_name'] . "', '" .
        $_text . "', '" . $messageType . "')";
    //sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $query = $pdo->prepare($sql); // Выполнение запроса INSERT
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 INSERT error (sql.php: saveUserAndMessage):\n" . $sql . "\n-- - \n" . $e->getMessage());
    }
}

/**
 * Сохранить ошибку в баду данных.
 * @param string $error Текстовое сообщение об ошибке.
 */
function setError(string $error): void
{
    global $pdo;
    $sql = "INSERT INTO error (err_message) VALUES ('" . $error . "')";
    //sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $query = $pdo->prepare($sql);
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 INSERT error (sql.php: setConfigData):\n" . $sql . "\n-- - \n" . $e->getMessage());
        exit;
    }
}

/**
 * Получаем количество ошибок за день.
 * @return string Возвращает число ошибок, если ошибок нет возвращает пустую строку.
 */
function getError(): string
{
    global $pdo;
    $sql = "SELECT * FROM error WHERE time >= CURDATE() - INTERVAL 1 DAY and time < CURDATE()";
    //sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $arr = $pdo->query($sql)->fetchAll(); // Выполнение запроса SELECT
        //sendServiceMessage(var_export($arr, true));
        if ($arr) return count($arr); else return "";
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 SELECT error (sql.php: getError()):\n" . $sql . "\n-- - \n" . $e->getMessage());
        exit;
    }
}
$end_time = microtime(true);
echo 'sql: ' . number_format(($end_time - $start_time),8,'.','') . "sec.\n";