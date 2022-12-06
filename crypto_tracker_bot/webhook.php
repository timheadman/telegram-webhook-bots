<?php
require_once 'global_functions.php'; // Глобальные функции.
require_once 'sql.php'; // Подключение к MySQL, функции.
require_once 'telegram.php'; // Подключение к Telegramm, функции.
require_once 'binance.php'; // Функции Binance API.

$jsonData = file_get_contents('php://input');
$inputMessage = json_decode($jsonData, TRUE);
$messageType = detectMessageType($inputMessage); // Распознаем тип сообщения

// sendServiceMessage(gettype($inputMessage));
// sendServiceMessage(var_export($inputMessage, true) . "\n\n" . $messageType);
// **************************************************
// *************** ДАННЫЕ ПОЛЬЗОВАТЕЛЯ **************
// **************************************************
$messageType == 'callback_query' ? $jdColumnName = $messageType : $jdColumnName = 'message';
$chat_id = (int)$inputMessage[$jdColumnName]['chat']['id'];
$chat_title = $inputMessage[$jdColumnName]['chat']['title'];
$from_id = (int)$inputMessage[$jdColumnName]['from']['id'];
$from_is_bot = (int)$inputMessage[$jdColumnName]['from']['is_bot'];
$from_first_name = $inputMessage[$jdColumnName]['from']['first_name'];
$from_username = $inputMessage[$jdColumnName]['from']['username'];
$from_language_code = $inputMessage[$jdColumnName]['from']['language_code'];
$text = $inputMessage['message']['text'];

saveUserAndMessage($inputMessage, $messageType);
// *********************************************
// ******* ОБРАБОТКА ПО ТИПУ СООБЩЕНИЙ *********
// *********************************************
if ($messageType == 'text') {
    // ---------- TEXT ----------
    $incomingCommand = parseCommand($text);
    //sendServiceMessage(var_export($incomingCommand, true));
    switch ($incomingCommand[0]) { // Обработка входящих команд.
        case '/help':
            getHelp($chat_id);
            break;
        case '/getmyinfo':
            getMyInfo($chat_id, $inputMessage);
            break;
        case '/get':
            getAlertsTable($chat_id, $incomingCommand);
            break;
        case '/add':
            addAlert($chat_id, $incomingCommand);
            break;
        case '/upd':
            updateAlert($chat_id, $incomingCommand);
            break;
        case '/del':
            delAlerts($chat_id, $incomingCommand);
            break;
        default:
            sendMessage($chat_id, "Unrecognized command:\n" . $text);
            exit;
    }
} elseif ($messageType == 'photo') {
    // ---------- PHOTO ----------
    $fileArrCount = count($inputMessage['message']['photo']); // Количество созданых разновидностей разрешений для картинки.
    $fileId = $inputMessage['message']['photo'][$fileArrCount - 1]['file_id']; // Берем последнюю запись - самый большой файл.
    $fileUniqueId = $inputMessage['message']['photo'][$fileArrCount - 1]['file_unique_id']; // Выясняем file_id & file_unique_id

    $getFileURL = TG_API . 'getFile?file_id=' . $fileId . '&file_unique_id=' . $fileUniqueId; // Создаем запрос на вывод файла и получения ссылки (действует 1 час.)
    $getFileData = file_get_contents($getFileURL); // Получаем ответ на запрос.
    $getFileJson = json_decode($getFileData, TRUE); // Преобразуем в JSON.
    $filePath = TG_FILE_API . $getFileJson['result']['file_path'];
    $saveFileName = "image/" . $chat_id . '_' . $inputMessage[$jdColumnName]['from']['username'] . '_FI_' . $fileId . '_FU_' . $fileUniqueId . ".jpg"; // Создаем уникальное имя файла.
    file_put_contents($saveFileName, fopen($filePath, 'r')); // Сохраняем файл на сервере.
    sendMessage($chat_id, "File uploaded.\n" . $saveFileName);
    sendServiceMessage("Image received.\n" . $getFileURL . "\n" . $filePath);
} elseif ($messageType == 'animation') {
    // ---------- ANIMATION ----------
    sendMessage($chat_id, "Сообщения типа " . $messageType . " еще не обрабатываются.");
} elseif ($messageType == 'document') {
    sendMessage($chat_id, "Сообщения типа " . $messageType . " еще не обрабатываются.");
    // ---------- DOCUMENT ----------
} else exit;
// **************************************************
$pdo = null;
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
// xxxxxxxxxxxxxxxxx FUNCTIONS xxxxxxxxxxxxxxxxx
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
/**
 * Отправить текстовое Telegram сообщение на указанный $chat_id, содержащее справочную информацию.
 * @param $chat_id
 */
function getHelp($chat_id): void
{
    sendMessage(
        $chat_id,
        "Уведомления об изменении курса криптовалютных пар:\n" .
        "/get - список уведомлений\n\n" .
        "/add - добавить уведомление\n" .
        "Пример: /add BTCUSDT 50000\n" .
        "/upd - изменить уведомление\n" .
        "Пример: /add 11 2.53 или /add 11 2.53 UP \n" .
        "11 - первый аргумент, порядковый номер уведомления в списке\n" .
        "2.53 - второй аргумент, новая цена срабатывания\n" .
        "UP - третий аргумент, опционально, новое напрвление пересечения цены\n\n" .
        "/del - удалить уведомления\n" .
        "Пример: /del 0 1 2\n" .
        "* 0 1 2 - Порядковые номера уведомлений в списке /get,\n" .
        "/del all - удаление всех уведомлений.\n\n" .
        "/help - расширенная справка"
    );
}

function getMyInfo($chat_id, $inputMessage): void
{
    sendMessage(
        $chat_id,
        "ID: " . $chat_id .
        "\nFirst Name: " . $inputMessage['message']['from']['first_name'] .
        "\nUser Name: " . $inputMessage['message']['from']['username'] .
        "\nLanguage: " . $inputMessage['message']['from']['language_code'] .
        "\nBot: " . (int)$inputMessage['message']['from']['is_bot'] .
        "\nIncoming Message: " . $inputMessage['message']['text']
    );
}

function getAlertsTable(int $chat_id, $incomingCommand): void
{
    $getTicket = $incomingCommand[1];
    //sendServiceMessage("getTicket:" . var_export($getTicket, True));
    if (count($incomingCommand) > 2) {
        sendMessage($chat_id, "\xE2\x81\x89 Неверное количество аргументов (" . count($incomingCommand)
            . ").\n/get XXXX - список уведомлений по отношению к XXXX\n/help - расширенная справка");
        exit;
    }
    $alertsData = getAlerts($chat_id);
    //sendServiceMessage(var_export($alertsData, true));
    if (!$alertsData)
        sendMessage($chat_id, "Список уведомлений пуст.");
    else {
        $alertsTable = $getTicket ? strtoupper($getTicket) . "\n" : "";

        for ($i = 0; $i < count($alertsData); $i++) {
            if ($getTicket &&
                $getTicket != strtolower(substr($alertsData[$i]['symbol'], strlen($getTicket) * -1)))
                continue;
            $currentPrice = getPrice($alertsData[$i]['symbol']);
            $percentDiff = round(($currentPrice - $alertsData[$i]['value']) / ($alertsData[$i]['value'] * 0.01), 1);
            $tmpDirection = $alertsData[$i]['direction'] ? "UP" : "DOWN";
            // ДОБАВИТЬ !!! Считывать количество символов после запятой в текущей цене
            // и добивать нулями цену уведомления для удобства считывания.
            $alertsTable .= $i . ". " . $alertsData[$i]['symbol'] . " " . $alertsData[$i]['value']  . " "
                . $tmpDirection . " (" . $percentDiff . "% " . printPrice($currentPrice) . ") " . "\n";
        }
        sendMessage($chat_id, $alertsTable);
    }
}

/**
 * Добавить уведомление в базу данных.
 * @param int $chat_id
 * @param $incomingCommand
 */
function addAlert(int $chat_id, $incomingCommand): void
{
    global $pdo;
    //sendServiceMessage(implode('|', $incomingCommand));
    if (count($incomingCommand) != 3) {
        sendMessage($chat_id, "\xE2\x81\x89 Неверное количество аргументов (" . count($incomingCommand)
            . ").\n/get - список уведомлений\n/help - расширенная справка");
        exit;
    }
    // Проверка ПАРЫ
    $symbol = strtoupper($incomingCommand[1]); // Приводим символы пары к верхнему регистру
    $price = getPrice($symbol);
    if ($price < 0) {
        sendMessage($chat_id, "\xE2\x81\x89 Пара '" . $symbol . "' не найдена или ошибка получения данных.");
        exit;
    }

    // Проверка значения стоимости
    $value = $incomingCommand[2];
    if ($value <= 0) {
        sendMessage($chat_id, "\xE2\x81\x89 Стоимость не может быть меньше или равна 0.");
        exit;
    }
    // Устанавливаем направление по умолчанию
    $direction = ($price < $value) ? 1 : 0;
    try {
        $sql = "INSERT INTO alerts (user_id, symbol, value, direction) " .
            "VALUES (" . $chat_id . ", '" . $symbol . "', " . $value . ", " . $direction . ")";

        // $sql = "INSERT INTO alerts (user_id, symbol, value, direction) " .
        //     "SELECT " . $chat_id . ", '" . $symbol . "', " . $value . ", " . $direction . " FROM alerts " .
        //     "WHERE NOT EXISTS (SELECT * FROM alerts WHERE user_id=" . $chat_id .
        //     " AND symbol='" . $symbol . "' AND value=" . $value . " AND direction=" . $direction . ") LIMIT 1";
        //sendServiceMessage("SQL Query:\n" . $sql);
        $query = $pdo->prepare($sql); // Выполнение запроса INSERT
        $query->execute();
        $rowCount = $query->rowCount();
        if ($rowCount) {
            sendMessage($chat_id, "\xE2\x9C\xB3 Уведомление добавлено. " . $symbol . " " . $value . " (" . $direction . ")");
            getAlertsTable($chat_id, "");
        } else
            sendMessage($chat_id, "\xE2\x9D\x8C Запись существует. " . $symbol . " " . $value . " (" . $direction . ")\nВывести список уведомлений: /get");
    } catch (PDOException $e) {
        sendMessage($chat_id, "\xE2\x9A\xA0 INSERT error (webhook.php):\n" . $sql
            . "\n---\n" . $e->getMessage());
        exit;
    }
}

/**
 * Обновить определенную запись в базе данных уведомлений.
 * @param int $chat_id
 * @param $incomingCommand
 */
function updateAlert(int $chat_id, $incomingCommand): void //$serial, $value, $direction = "DEF"
{
    global $pdo;
    global $priceArray;
    //sendServiceMessage(implode('|', $incomingCommand));
    if (count($incomingCommand) < 3 || count($incomingCommand) >= 5) {
        sendMessage($chat_id, "\xE2\x81\x89 Неверное количество аргументов (" . count($incomingCommand)
            . ").\n/get - список уведомлений\n/help - расширенная справка");
        exit;
    }
    $serial = (int)$incomingCommand[1];
    $value = (float)$incomingCommand[2];
    $direction = (string)$incomingCommand[3];
    if (!is_numeric($value)) { // Проверка цены
        sendMessage($chat_id, "\xE2\x81\x89 Неверный аргумент " . $value . ", цена не содержит цифрового значения.\n/get - список уведомлений\n/help - расширенная справка");
        exit;
    }

    $alertDB = getAlerts($chat_id);
    if ($serial < 0 || $serial > count($alertDB)) {
        sendMessage($chat_id, "\xE2\x81\x89 Данного номера (" . $serial .
            ") нет в списке уведомлений.\n* Порядковый номер должен быть целым числом которое больше или равно нулю.\n/get - список уведомлений\n/help - расширенная справка");
        exit;
    }

    $id = $alertDB[$serial]["id"];
    $symbol = $alertDB[$serial]["symbol"];

    switch (strtoupper($direction)) {
        case 'UP':
            $direction = ", direction=1";
            break;
        case 'DOWN':
            $direction = ", direction=0";
            break;
        default:
            $key = array_search($symbol, array_column($priceArray, 'symbol'));
            $priceNow = floatval($priceArray[$key]['price']);
            $direction = $value > $priceNow ? ", direction=1" : ", direction=0";
            break;
    }

    // Подставляем вместо порядкового номера уведомления его ID из базы данных
    try {
        $sql = "UPDATE alerts SET value=" . $value . $direction . ", reloadmultiplier=0, reloadtime=0 WHERE id=" . $id;
        //sendServiceMessage("SQL Query:\n" . $sql);
        $query = $pdo->prepare($sql); // Выполнение запроса INSERT
        $query->execute();
        sendMessage($chat_id, "\xE2\x9C\xB3 Уведомление №" . $serial . " изменено.\n" . $symbol . " " . $value);
        getAlertsTable($chat_id, "");
    } catch (PDOException $e) {
        sendMessage($chat_id, "\xE2\x9A\xA0 UPDATE error (webhook.php):\n" . $sql
            . "\n---\n" . $e->getMessage());
        exit;
    }
}

/**
 * Удалить все или определенный список уведомлений из базы данных.
 * @param int $chat_id
 * @param $incomingCommand
 */
function delAlerts(int $chat_id, $incomingCommand): void
{
    global $pdo;
    sendServiceMessage(implode('|', $incomingCommand) . "\n" . count($incomingCommand));
    if (count($incomingCommand) == 1) {

        sendMessage($chat_id, "\xE2\x81\x89 Для удаления уведомлений укажите порядковые номера через пробел.\n" .
            "/get - список уведомлений\n/help - расширенная справка");
        exit;
    }
    $delRowID = "";
    // Проверяем аргумент на соответствие ALL all и удаляем все записи!
    if (count($incomingCommand) > 1 && ($incomingCommand[1] == "all" || $incomingCommand[1] == "ALL")) {
        $sql = "DELETE FROM alerts WHERE user_id=" . $chat_id;
    } else {
        $alertDB = getAlerts($chat_id);
        $countIncomingCommand = count($incomingCommand);
        for ($i = 1; $i < $countIncomingCommand; $i++) { // аргументы хрянятся в incommingCommand начиная с 1 элемента]
            if (intval($incomingCommand[$i]) >= 0) { // проверяем на валидность каждый индекс с 0 до +...
                // Подставляем вместо порядкового номера уведомления его ID из базы данных
                $delRowID == "" ? $delRowID .= $alertDB[$incomingCommand[$i]]["id"] : $delRowID .= "," . $alertDB[$incomingCommand[$i]]["id"];
            } else sendMessage($chat_id, "\xE2\x9A\xA0 Аргумент №" . ($i + 1) . " (" . $incomingCommand[$i] . ") содержит ошибку.");
        }
        $sql = "DELETE FROM alerts WHERE user_id=" . $chat_id . " AND id in (" . $delRowID . ")";
    }
    try {
        //sendServiceMessage("SQL Query:\n" . $sql);
        $query = $pdo->prepare($sql);
        $query->execute();
        $rowCount = $query->rowCount();
        sendMessage($chat_id, "\xE2\x9D\x8C Удалено " . $rowCount . " строк.");
        getAlertsTable($chat_id, "");
    } catch (PDOException $e) {
        sendMessage($chat_id, "\xE2\x9A\xA0 DELETE error (webhook.php):\n"
            . $sql . "\n---\n" . $e->getMessage());
        exit;
    }
}
