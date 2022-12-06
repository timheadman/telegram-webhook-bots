<?php
$start_time = microtime(true);
require_once 'sql.php'; // Подключение к MySQL, функции.
// ***************************************************************
// ********** Блок запроса новых транзакциях в блокчейн **********
// ***************************************************************
$timeOutWF = 300 + (3300 * getConfigData("ctbWFError")); // Время между срабатыванием скрипта. Если ошибки нет, то 300 секунд, если есть 3600 секунд.
if (time() - getConfigData("ctbWFLastRunTS") > $timeOutWF) { // Не ранее чем через 5 минут после последнего запуска.
    sendMessage(TG_CHAT_ID, getNewWhaleTrasaction());
    setConfigData("ctbWFLastRunTS", time()); // Обновить время последнего запуска в timesatmp );
} else {
    return "";
}

/**
 * @return string
 */
function getNewWhaleTrasaction(): string
{
    $currentMaxBlock = 0;
    $tmpString = "";
    $tmpOperationStr = "";
    $connectionError = true;
    // Проверка на ошибку в предыдущем запросе.
    $postData = file_get_contents('https://blockchain.info/rawaddr/1P5ZEDWTKTFGxQjZphgWPQUpe554WKDfHQ?limit=20');
    if ($postData) {
        setConfigData("ctbWFError", 0);
        $connectionError = false;
        $data = json_decode($postData, true);
        $maxBlock = getConfigData("ctbWFmaxBlock");
        foreach ($data["txs"] as $value) { // Перебираем массив
            if ($value["block_index"] > $maxBlock) { // Если блок больше последнего обработанного
                if ($value["block_index"] > $currentMaxBlock) $currentMaxBlock = $value["block_index"]; // Выбираем самый старший блок и запоминаем
                $result = $value["result"] / 100000000; // Переводим из сатоши в биткоин, округляем до 2 знаков после запятой
                if ($result > 1 || $result < -1) {  // Отбрасываем мелкие транзакции
                    $tmpOperationStr .= "" . $value["block_index"] . " - " .
                        date('d M Y H:i', $value["time"]) . ": ";
                    $result >= 0 ? $tmpOperationStr .= "\xE2\x9C\x85 " : $tmpOperationStr .= "\xE2\x9D\x8C ";
                    $tmpOperationStr .= ($result > 0 ? "+" : "") . number_format($result, 0, ',', ' ') .
                        " BTC (" . number_format(getHistoryPrice('BTCUSDT', $value["time"]), 0, ',', ' ') . "$)\n";
                }
            }
        }
        if ($currentMaxBlock > $maxBlock) {
            setConfigData("ctbWFmaxBlock", $currentMaxBlock);
            if ($tmpOperationStr != "") { // Если транзакция больше 1BTC выводим информацию.
                $tmpString = "\xF0\x9F\x90\xB3 #whale \xF0\x9F\x90\xB3\n"
                    . "A new transactions was found:\n"
                    . $tmpOperationStr
                    . "-----------------------------\n"
                    . "https://bitinfocharts.com/ru/bitcoin/address/1P5ZEDWTKTFGxQjZphgWPQUpe554WKDfHQ\n";
            }
        }
    }
    if ($connectionError) {
        setError("ctbWhaleFollow (Whale Follow error): " . error_get_last()["message"]);
        setConfigData("ctbWFError", 1);
    }
    return $tmpString;
}
$end_time = microtime(true);
echo 'ctbWhaleFollow: ' . number_format(($end_time - $start_time),8,'.','') . "sec.\n";