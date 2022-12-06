<?php
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
 * Добавляем пользователя в базу, игнорируем если он есть.
 * @param array $inputMessage Массив телеграм сообщения.
 */
function saveUser(array $inputMessage)
{
    global $pdo;
    $primaryKey = array_keys($inputMessage)[1];
    $sql = "INSERT IGNORE INTO users (id, username, first_name, last_name) VALUES (" .
        (int)$inputMessage[$primaryKey]['from']['id'] . ", '" .
        $inputMessage[$primaryKey]['from']['username'] . "', '" .
        $inputMessage[$primaryKey]['from']['first_name'] . "', '" .
        $inputMessage[$primaryKey]['from']['last_name'] . "'" .
        ") ON DUPLICATE KEY UPDATE msg = msg + 1, " .
        "username = '" . $inputMessage[$primaryKey]['from']['username'] . "', " .
        "first_name = '" . $inputMessage[$primaryKey]['from']['first_name'] . "', " .
        "last_name = '" . $inputMessage[$primaryKey]['from']['last_name'] . "', " .
        "lastmsgtime = now()";
    // sendServiceMessage("SQL Query:\n" . $sql);
    try {
        $query = $pdo->prepare($sql); // Выполнение запроса INSERT & UPDATE
        $query->execute();
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 INSERT error (sql.php: saveUserAndMessage):\n"
            . $sql . "\n---\n" . $e->getMessage());
    }
}
