<?php
/**
 * 台灣銀行匯率查詢腳本
 * 此腳本使用cURL查詢台灣銀行的匯率資訊並解析HTML內容
 * 專注於網頁解析方式獲取匯率
 */

// 設定目標URL（台灣銀行匯率查詢頁面）
$url = 'https://rate.bot.com.tw/xrt?Lang=zh-TW';

// 初始化cURL
$ch = curl_init();

// 設定cURL選項
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// 執行cURL請求
$response = curl_exec($ch);

// 檢查是否有錯誤
if (curl_errno($ch)) {
    echo '錯誤: ' . curl_error($ch);
    exit;
}

// 檢查HTTP狀態碼
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($httpCode != 200) {
    echo "HTTP錯誤: $httpCode\n";
    exit;
}

// 關閉cURL
curl_close($ch);

// 使用DOMDocument來解析HTML，這比正則表達式更可靠
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $response);
$xpath = new DOMXPath($dom);

// 初始化匯率陣列
$exchangeRates = [];

// 獲取表格
$tables = $xpath->query('//table[contains(@class, "table")]/tbody');

// 調試輸出
echo "找到 " . $tables->length . " 個表格\n\n";

if ($tables && $tables->length > 0) {
    $tbody = $tables->item(0);
    $rows = $xpath->query('.//tr', $tbody);
    
    echo "找到 " . $rows->length . " 行數據\n\n";
    
    foreach ($rows as $row) {
        // 使用多種方式嘗試提取貨幣名稱
        $currencyText = '';
        
        // 方法1：尋找幣別名稱
        $currencyNameNodes = $xpath->query('.//div[contains(@class, "hidden-phone")]/text()', $row);
        if ($currencyNameNodes->length > 0) {
            $currencyText = trim($currencyNameNodes->item(0)->nodeValue);
        }
        
        // 如果方法1失敗，嘗試方法2
        if (empty($currencyText)) {
            $currencyNameNodes = $xpath->query('.//td[1]//text()', $row);
            foreach ($currencyNameNodes as $node) {
                $text = trim($node->nodeValue);
                if (!empty($text)) {
                    $currencyText = $text;
                    break;
                }
            }
        }
        
        // 獲取匯率值
        $cols = $xpath->query('.//td', $row);
        
        // 調試輸出
        if ($cols->length > 0) {
            echo "幣別: $currencyText, 找到 " . $cols->length . " 個欄位\n";
            for ($i = 0; $i < $cols->length; $i++) {
                echo "  欄位 $i: " . trim($cols->item($i)->nodeValue) . "\n";
            }
            echo "\n";
        }
        
        // 確保有足夠的欄位
        if ($cols->length >= 5 && !empty($currencyText)) {
            // 在某些情況下，現金匯率欄位可能是第2和第3列
            $cashBuy = ($cols->length > 1) ? trim($cols->item(1)->nodeValue) : 'N/A';
            $cashSell = ($cols->length > 2) ? trim($cols->item(2)->nodeValue) : 'N/A';
            $spotBuy = ($cols->length > 3) ? trim($cols->item(3)->nodeValue) : 'N/A';
            $spotSell = ($cols->length > 4) ? trim($cols->item(4)->nodeValue) : 'N/A';
            
            $exchangeRates[] = [
                '貨幣' => $currencyText,
                '現金買入' => $cashBuy,
                '現金賣出' => $cashSell,
                '即期買入' => $spotBuy,
                '即期賣出' => $spotSell
            ];
        }
    }
}

// 嘗試直接獲取CSV格式匯率
if (empty($exchangeRates)) {
    // 台灣銀行提供的匯率CSV下載連結
    $csvUrl = 'https://rate.bot.com.tw/xrt/flcsv/0/day';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $csvUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $csvData = curl_exec($ch);
    curl_close($ch);
    
    if ($csvData) {
        // 解析CSV
        $lines = explode("\n", $csvData);
        $header = str_getcsv(array_shift($lines));
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            if (count($data) >= 5) {
                $exchangeRates[] = [
                    '貨幣' => $data[0],
                    '現金買入' => $data[1],
                    '現金賣出' => $data[2],
                    '即期買入' => $data[3],
                    '即期賣出' => $data[4]
                ];
            }
        }
    }
}

// 輸出結果
echo "\n台灣銀行匯率查詢結果：\n";
echo "======================\n";

if (!empty($exchangeRates)) {
    echo sprintf("%-15s %-10s %-10s %-10s %-10s\n", '貨幣', '現金買入', '現金賣出', '即期買入', '即期賣出');
    echo str_repeat('-', 60) . "\n";
    
    foreach ($exchangeRates as $rate) {
        echo sprintf("%-15s %-10s %-10s %-10s %-10s\n", 
            $rate['貨幣'], 
            $rate['現金買入'], 
            $rate['現金賣出'], 
            $rate['即期買入'], 
            $rate['即期賣出']
        );
    }
} else {
    echo "無法解析匯率資訊，可能是台灣銀行網站結構已更改。\n";
    echo "請考慮直接訪問台灣銀行網站: https://rate.bot.com.tw/xrt?Lang=zh-TW\n";
}

/**
 * 注意事項：
 * 1. 此腳本通過分析台灣銀行網頁結構來獲取匯率信息
 * 2. 如果網站結構發生變化，此腳本可能需要更新
 * 3. 調試輸出可幫助識別網頁結構的變化
 * 4. 網站爬蟲應遵守目標網站的使用條款和robots.txt規定
 */
?>