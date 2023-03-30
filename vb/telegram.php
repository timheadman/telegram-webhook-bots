<?php
require_once 'config.php';
// +++++++++++++++++++++++++++++++++++++++++++++++++++++
// ++++++++++++++ configTG.php ver.3.5 +++++++++++++++++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++
const TG_API = 'https://api.telegram.org/bot' . TG_TOKEN . '/';
const TG_FILE_API = 'https://api.telegram.org/file/bot' . TG_TOKEN . '/';

/**
 * Отправка текстового Telegram сообщения, пользователю с идентификатором $chat_id.
 * @param int $chat_id
 * @param String $message
 * @param int $reply_to_message_id
 * @return false|mixed
 */
function sendMessage(int $chat_id, string $message, int $reply_to_message_id = 0)
{
    if ($message == "") return false;
    if ($reply_to_message_id)
        $url = TG_API . 'sendMessage?chat_id=' . $chat_id . '&reply_to_message_id=' . $reply_to_message_id . '&text=' . urlencode($message);
    else $url = TG_API . 'sendMessage?chat_id=' . $chat_id . '&text=' . urlencode($message);

    $jsonReturnData = file_get_contents($url); // Получаем JSON ответ на запрос.
    $arrReturnData = json_decode($jsonReturnData, TRUE); // Ответ преобразуем в массив.
    return $arrReturnData['ok'];
}
/**
 * Отправка стикера, пользователю с идентификатором $chat_id.
 * @param int $chat_id
 * @param String $file_id
 * @param int $reply_to_message_id
 * @return false|mixed
 */
function sendSticker(int $chat_id, string $file_id, int $reply_to_message_id = 0)
{
    if ($file_id == "") return false;
    if ($reply_to_message_id)
        $url = TG_API . 'sendSticker?chat_id=' . $chat_id . '&reply_to_message_id=' . $reply_to_message_id . '&sticker=' . urlencode($file_id);
    else $url = TG_API . 'sendSticker?chat_id=' . $chat_id . '&sticker=' . urlencode($file_id);

    $jsonReturnData = file_get_contents($url); // Получаем JSON ответ на запрос.
    $arrReturnData = json_decode($jsonReturnData, TRUE); // Ответ преобразуем в массив.
    return $arrReturnData['ok'];
}
/**
 * Отправка служебного текстового Telegram сообщения, пользователю с идентификатором TG_ADMIN_ID.
 * @param $message
 * @return false|mixed
 */
function sendServiceMessage($message)
{
    if ($message == "") return false;
    $tmp_message = "\xE2\x84\xB9 #service \xE2\x84\xB9\n" . mb_strimwidth($message, 0, 4000, "...");
    $url = TG_API . 'sendMessage?chat_id=' . TG_ADMIN_ID . '&text=' . urlencode($tmp_message);
    $jsonReturnData = file_get_contents($url); // Получаем JSON ответ на запрос.
    $arrReturnData = json_decode($jsonReturnData, TRUE); // Ответ преобразуем в массив.
    return $arrReturnData['ok'];
}

/**
 * Отправка текстового Telegram сообщения, пользователю с идентификатором $chat_id.
 * @param int $chat_id Уникальный идентификатор целевого чата или имя пользователя целевого канала (в формате @channelusername)
 * @param int $message_id Идентификатор сообщения в чате, указанный в from_chat_id
 * @param int $from_chat_id Уникальный идентификатор чата, в который было отправлено исходное сообщение (или имя пользователя канала в формате @channelusername)
 */
//function forwardMessage(int $chat_id, int $message_id, int $from_chat_id = 0)
//{
//    if ($from_chat_id)
//        file_get_contents(TG_API . 'forwardMessage?chat_id=' . $chat_id . '&from_chat_id=' . $from_chat_id . '&message_id=' . $message_id);
//    else
//        file_get_contents(TG_API . 'forwardMessage?chat_id=' . $chat_id . '&from_chat_id=' . $chat_id . '&message_id=' . $message_id);
//}

/**
 * @param $chat_id
 * @param $message
 * @param $keyboard
 * @return mixed
 */
function sendInlineKeyboard($chat_id, $message, $keyboard)
{
    $url = TG_API . "sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message) .
        "&disable_web_page_preview=0&" . http_build_query(array(
            'reply_markup' => json_encode(array('inline_keyboard' => $keyboard))
        ));
    sendServiceMessage($url);
    return json_decode(file_get_contents($url), JSON_OBJECT_AS_ARRAY);
}

/**
 * Определить тип Telegram сообщения.
 * @param $inputMessage
 * @return string
 */
function detectMessageType($inputMessage): string
{
    $primaryKey = array_keys($inputMessage)[1];
    if ($primaryKey == 'message') {
        if (array_key_exists('text', $inputMessage['message'])) {
            return 'text';
        } elseif (array_key_exists('photo', $inputMessage['message'])) {
            return 'photo';
        } elseif (array_key_exists('animation', $inputMessage['message'])) {
            return 'animation';
        } elseif (array_key_exists('audio', $inputMessage['message'])) {
            return 'audio';
        } elseif (array_key_exists('voice', $inputMessage['message'])) {
            return 'voice';
        } elseif (array_key_exists('video', $inputMessage['message'])) {
            return 'video';
        } elseif (array_key_exists('document', $inputMessage['message'])) {
            return 'document';
        } elseif (array_key_exists('sticker', $inputMessage['message'])) {
            return 'sticker';
        } elseif (array_key_exists('left_chat_participant', $inputMessage['message'])) {
            return 'left_chat_participant';
        } elseif (array_key_exists('new_chat_member', $inputMessage['message'])) {
            return 'new_chat_member';
        }
    } elseif ($primaryKey == 'inline_query') {
        sendServiceMessage("\xF0\x9F\x93\x97 inline_query \xF0\x9F\x93\x97\n" . var_export($inputMessage, true));
        return 'inline_query';
    } elseif ($primaryKey == 'callback_query') {
        sendServiceMessage("\xF0\x9F\x93\x98 callback_query \xF0\x9F\x93\x98\n" . var_export($inputMessage, true));
        return 'callback_query';
    } elseif ($primaryKey == 'edited_message') {
        return 'edited_message';
    }
    sendServiceMessage("\xE2\x9D\x93 Тип cообщения не распознан \xE2\x9D\x93\n" . var_export($inputMessage, true));
    return "";
}

/**
 * Ищем команду и аргументы в тексте.
 * @param $text
 * @return array
 */
function parseCommand($text): array
{
    $_text = trim(strtolower($text)); // Обрезаем пробелы в начале и конце/
    if (strpos("*" . $_text, "/") == 1) { // Добавляем любой символ в начале, чтобы поиск позиции не возвращал 0
        return explode(" ", $_text);
    } else {
        return array();
    }
}
