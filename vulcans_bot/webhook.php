<?php
require_once 'sql.php'; // Подключаем блок работы с MySQL
require_once 'telegram.php'; // Подключение к Telegramm

$jsonData = file_get_contents('php://input');
$inputMessage = json_decode($jsonData, TRUE);
//sendServiceMessage(var_export($inputMessage, true));
// **************************************************
// *************** ДАННЫЕ ПОЛЬЗОВАТЕЛЯ **************
// **************************************************
$messageType = detectMessageType($inputMessage); // Распознаем тип сообщения
$primaryKey = array_keys($inputMessage)[1];
$chat_id = (int)$inputMessage[$primaryKey]['chat']['id'];
$from_id = (int)$inputMessage[$primaryKey]['from']['id'];
$from_first_name = $inputMessage[$primaryKey]['from']['first_name'];
$from_username = $inputMessage[$primaryKey]['from']['username'];
$text = $inputMessage['message']['text'];
saveUser($inputMessage);
// +++++++++++++++++++++++++++++++++++++++++++++
// +++++++++++ ТЕКСТОВОЕ СООБЩЕНИЕ +++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++
/*if ($chat_id == "-1001753935655") { // !!! БЛОК ДЛЯ ТЕСТИРОВАНИЯ НА ГРУППЕ СЕМЬЯ!!!
    sendServiceMessage($chat_id . " " . $inputMessage[$primaryKey]['message_id']);
}*/
if ($messageType == 'text') {
    $incomingCommand = parseCommand($text);
    //sendServiceMessage(var_export($incomingCommand, true));
    //sendServiceMessage($incomingCommand[0]);
    switch ($incomingCommand[0]) { // Обработка входящих команд.
        case '/help':
            getHelp($chat_id);
            break;
        case '/rate':
            sendMessage($chat_id, getRate());
            break;
        case '/myinfo':
            getMyInfo($chat_id, $inputMessage);
            break;
        default:
            if (stripos("*" . $text, "всем привет")) {
                sendMessage($chat_id, getAnswer($text));
            } elseif ($inputMessage[$primaryKey]['reply_to_message']['from']['id'] == 5195893815) {
                sendMessage($chat_id, getAnswer($text));
            } elseif (parseCallByName($text)) {
                sendMessage($chat_id, getAnswer($text));
            }
            exit;
    }
} elseif ($messageType == 'photo') {
    $fileArrCount = count($inputMessage['message']['photo']); // Количество созданых разновидностей разрешений для картинки.
    $fileId = $inputMessage['message']['photo'][$fileArrCount - 1]['file_id']; // Берем последнюю запись - самый большой файл.
    $fileUniqueId = $inputMessage['message']['photo'][$fileArrCount - 1]['file_unique_id']; // Выясняем file_id & file_unique_id

    $url = TG_API . 'getFile?file_id=' . $fileId . '&file_unique_id=' . $fileUniqueId; // Создаем запрос на вывод файла и получения ссылки (действует 1 час.)
    $jsonFileData = file_get_contents($url); // Получаем ответ на запрос.
    $arrFileData = json_decode($jsonFileData, TRUE); // Преобразуем в JSON.
    $filePath = TG_FILE_API . $arrFileData['result']['file_path'];
    $saveFileName = "image/" . $chat_id . '_' . $from_username . '_FI_' . $fileId . '_FU_' . $fileUniqueId . ".jpg"; // Создаем уникальное имя файла.
    file_put_contents($saveFileName, fopen($filePath, 'r')); // Сохраняем файл на сервере.
    //sendServiceMessage("Image received.\n" . $getFileURL . "\n" . $filePath);
    // +++++++++++++++++++++++++++++++++++++++++++++
    // +++++++++++++ НОВЫЙ ПОДПИСЧИК +++++++++++++++
    // +++++++++++++++++++++++++++++++++++++++++++++
} elseif ($messageType == 'new_chat_member') {
    sendServiceMessage("\xF0\x9F\x86\x95 Новый пользователь \xF0\x9F\x86\x95\n" . var_export($inputMessage, true));
    sendSticker($chat_id, "CAACAgIAAxkBAAIHC2K8Vr5hrQz-c7LcEUQ7NdxnkL1vAAIyAgAC-p_xGNstS3aLy_YDKQQ");
    sendMessage($chat_id, "\nПривeт и дoбро пoжаловaть\xE2\x9D\x95\nTы случаем нe бoт\xE2\x9D\x94", (int)$inputMessage[$primaryKey]['message_id']);
} elseif ($messageType == 'left_chat_participant') {
    sendServiceMessage("\xE2\x9B\x94 Пользователь покинул чат \xE2\x9B\x94\n" . var_export($inputMessage, true));
} else exit;
// *********** КОНЕЦ ОСНОВНОГО КОДА ***********
$pdo = null;
// ********************************************
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
// xxxxxxxxxxxxxxxxx FUNCTIONS xxxxxxxxxxxxxxxxx
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
/**
 * Отправить текстовое Telegram сообщение на указанный $chat_id, содержащее справочную информацию.
 */
function getHelp($chat_id)
{
    sendMessage(
        $chat_id,
        "/rate - рейтинг пользователей по количеству сообщений\n" .
            "/myinfo - служебная информация\n" .
            "/help - справка\n" .
            "* Команды необходимо писать без обращения к боту\n"
    );
}

function getMyInfo($chat_id, $inputMessage)
{
    sendMessage(
        $chat_id,
        "ID: " . $chat_id .
            "\nFirst Name: " . $inputMessage['message']['from']['first_name'] .
            "\nUser Name: " . $inputMessage['message']['from']['username'] .
            "\nIncoming Message: " . $inputMessage['message']['text']
    );
}

/**
 * Проверяет сообщение на наличие имени бота в сообщении.
 * @param $text
 * @return false|int
 */
function parseCallByName($text): bool
{
    $text = mb_strtolower($text);
    $arrNames = array( // Поиск регистронезависимый stripos
        "вулканоид",
        "вулканойд",
        "вулканоит",
        "vulcanoid",
        "th_vulcans_bot",
    );
    foreach ($arrNames as $value) {
        if (strpos("*" . $text, $value)) return true; // Подставляем * чтобы первый символ вхождения не был равен 0
    }
    return false;
}

/**
 * Проверяет сообщение на наличие вопроса и возвращает соответствующий ответ.
 * @param $text
 * @return string
 */
function getAnswer($text): string
{
    $questionID = 0;
    $text = mb_strtolower($text);
    $arrNames = array(
        11 => "ты кто",
        12 => "кто ты",
        13 => "а ты",
        21 => "ты бот",
        31 => "привет",
        41 => "пока",
        42 => "до свидания",
        43 => "всего доброго",
    );
    foreach ($arrNames as $key => $value) {
        if (strpos("*" . $text, $value)) { // Подставляем * чтобы первый символ вхождения не был равен 0
            $questionID = $key;
            break;
        }
    }
    switch ($questionID) {
        case 11: // "ты кто"
        case 12: // "кто ты"
        case 13: // "а ты"
            $arrAnswer = array(
                "Я местный бот.",
                "Ну явно не человек...",
                "Я железяка.",
                "Я железный человек!",
                "Я младший брат андроида.",
            );
            return $arrAnswer[rand(0, count($arrAnswer))];
        case 21: // "ты бот"
            $arrAnswer = array(
                "Да.",
                "Ага.",
                "В точку.",
                "Эпсолютли точно!",
                "Верно.",
                "Так и есть.",
            );
            return $arrAnswer[rand(0, count($arrAnswer))];
        case 31: // "привет"
            $arrAnswer = array(
                "Привет!",
                "Здравствуйте.",
                "Привет! Честно говоря, я понял только привет из всего сообщения!",
                "Категорически приветствую!",
                "Дрям!",
                "Бонжур.",
                "Хелло!",
                "Здравствуй человек!",
                "Я пришел с миром...",
                "Хай!",
                "Рад вас видеть!",
                "Благословен будь твой день!",
                "Сердечно обнимаю!",
                "Я ослеплен встречей!",
                "Давно не виделись, потеряшка!",
                "Я ослеплен встречей с тобой!",
            );
            return $arrAnswer[rand(0, count($arrAnswer))];
        case 41: // "пока"
        case 42: // "до свидания"
        case 43: // "всего доброго"
            $arrAnswer = array(
                "Пока!",
                "Всех благ!",
                "Удачи!",
                "Всего доброго!",
            );
            return $arrAnswer[rand(0, count($arrAnswer))];

        default:
            $arrAnswer = array(
                "Непонятненько ...",
                "Ммммммм...",
                "Чегось ?",
                "Не понял :(",
                "Я не хорошо понимать по русски!",
                "Я твой дом труба шаталь!",
                "А зи рунга рунгеееее, рунга зиии рунга!\n\xC2\xA9 Рабыня Изаура",
                "Одна голова — хорошо, а с туловищем лучше",
                "Заройся в мох и плюйся клюквой…",
                "У него отличное чувство юмора!",
                "Говорите, говорите… Я всегда зеваю, когда мне интересно!",
                "Как бы так себя похвалить, чтобы самому тошно не стало?",
                "Думаю, мне хватит самого лучшего",
                "Хочется верить, что будет хотеться и дальше...",
                "Да покарает тебя стоматолог!",
                "Ах, ты меня лишил иммунитета!",
                "Напился — веди себя доступно!",
                "Я не тормоз — я просто плавно мыслю",
                "Кто много спрашивает — тому много врут",
                "Некоторые люди наглеют, если их ежедневно кормить.",
                "Бегу и волосы назад ...",
                "Сорян, мозгами шевелил — перемешались...",
                "Думаю, не ошибусь, если промолчу.",
                "Вот таково мое мнение, а если оно вам не нравится, то у меня есть другое!",
                "Вам ответить как: вежливо или честно?",
                "Скажите, вам помочь или не мешать?",
            );
            return $arrAnswer[rand(0, count($arrAnswer))];
    }
}

function getRate(): string
{
    try {
        global $pdo;
        $sql = "SELECT users.id, users.username, users.first_name, users.msg
                FROM users ORDER BY users.msg";
        $arr = $pdo->query($sql)->fetchAll();
        $str = "\xE2\x9A\xA1 Рейтинг по количеству сообщений \xE2\x9A\xA1\n";
        for ($i = 0; $i < count($arr); $i++) {
            $str .= $arr[$i]['cnt'] . " - ";
            $arr[$i]['first_name'] ? $str .= $arr[$i]['first_name'] : $str .= $arr[$i]['from_id'];
            $arr[$i]['username'] ? $str .= " (@" . $arr[$i]['username'] . ")\n" : $str .= " (@" . $arr[$i]['from_id'] . ")\n";
        }
        $str .= "* расчет ведется с 04.02.2022.\n";
    } catch (PDOException $e) {
        sendServiceMessage("\xE2\x9A\xA0 SELECT error (webhook.php):\n"
            . $sql . "\n---\n" . $e->getMessage());
        exit;
    }
    return $str;
}
