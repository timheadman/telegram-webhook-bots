<?php
$start_time = microtime(true);
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
// xxxxxxxxxxx BINANCE API FUNCTIONS xxxxxxxxxxx
// xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

$priceArray = getAllPrice(); // Запрашиваем в массив все текущие цены с Binance

/**
 * Возвращает все пары биржи Binance в массив.
 * @return array
 */
function getAllPrice(): array
{
    $urlBinanceAPI = 'https://api.binance.com/api/v3/ticker/price';
    $postData = file_get_contents($urlBinanceAPI);
    if ($postData) {
        return json_decode($postData, true);
    }
    setError("getAllPrice(): " . error_get_last()["message"]);
    exit;
}

/**
 * Возвращает текущую цену запрашиваемой пары.
 * Принимает как существующие пары Binance, так и не существующие пары (например ADA/DOT).
 * https://stackoverflow.com/questions/65864645/how-to-use-binance-api-simple-get-price-by-ticker
 * @param string $symbol Любой символ из существующих, функция пробует составить пару из нескольких запросов.
 * @return float Если пара не найдена, возвращает false.
 */
function getPrice(string $symbol): float
{
    $symbol = strtoupper($symbol);
    $symbolPieces = explode("/", $symbol);
    switch (count($symbolPieces)) {
        case 1: // Если пара из списка существующих торговых пар Binance
            return getPriceFromArray($symbol);
        case 2: // Если пара составная (не из существующих пар Binance) запрашиваем отдельно две пары к USDT и создаем виртуальную пару
            $price0 = getPriceFromArray($symbolPieces[0] . "USDT");
            $price1 = getPriceFromArray($symbolPieces[1] . "USDT");
            return $price0 / $price1;
        default:
            return false;
    }
}

/**
 * Вспомогательная функция.
 * Возвращает текущую цену пары из массива $priceArray.
 * @param string $symbol Существующая на Binance торговая пара (например "BTCUSDT")
 * @return float Если пара не найдена, возвращает false.
 */
function getPriceFromArray(string $symbol): float
{
    global $priceArray;
    foreach ($priceArray as $item) {
        if (array_search($symbol, $item)) {
            return (float)$item['price'];
        }
    }
    return false;
}

/**
 * Возвращает историческое значение конкретной пары по заданным условиям.
 * https://developers.binance.com/docs/binance-api/spot-detail/rest-api#klinecandlestick-data
 * @param string $symbol Trade symbol (ex. "BTCUSDT")
 * @param int $startTime UNIX time (0 - default)
 * @param int $level 1 - Open, 2 - High, 3 - Low, 4 - Close, 5 - Average (default)
 * @return float Если пара не найдена, возвращает false.
 */
function getHistoryPrice(string $symbol, int $startTime = 0, int $level = 5): float
{
    /* EXAMPLE 
    https://api.binance.com/api/v3/klines?symbol=ADAUSDT&interval=1m&limit=1&startTime=1637826840000&endTime=1637826899999
    [
        1499040000000,      // Open time [0]
        "0.01634790",       // Open [1]
        "0.80000000",       // High [2]
        "0.01575800",       // Low [3]
        "0.01577100",       // Close [4]
        "148976.11427815",  // Volume
        1499644799999,      // Close time
        "2434.19055334",    // Quote asset volume
        308                // Number of trades
      ] 
*/
    $symbol = strtoupper($symbol);
    $interval = '1m';
    $limit = 1;
    $price = 0;
    if ($startTime == 0)
        $urlBinanceAPI = 'https://api.binance.com/api/v3/klines?symbol=' . $symbol . '&interval=' . $interval .
            '&limit=' . $limit;
    else {
        $startTime_ = intdiv($startTime, 60) * 60000;
        $endTime_ = $startTime_ + 59999;
        $urlBinanceAPI = 'https://api.binance.com/api/v3/klines?symbol=' . $symbol . '&interval=' . $interval .
            '&limit=' . $limit . '&startTime=' . $startTime_ . '&endTime=' . $endTime_;
    }
    //sendServiceMessage($urlBinanceAPI);
    $postData = file_get_contents($urlBinanceAPI);
    if ($postData) {
        $jsonPostData = json_decode($postData, true);
        if ($level <= 4 && $level > 0)
            $price = (float)$jsonPostData[0][$level];
        else $price = ((float)$jsonPostData[0][2] + (float)$jsonPostData[0][3]) / 2;
        return $price;
    }
    setError("getHistoryPrice(): " . error_get_last()["message"]);
    return false;
}
$end_time = microtime(true);
echo 'binance: ' . number_format(($end_time - $start_time),8,'.','') . "sec.\n";
