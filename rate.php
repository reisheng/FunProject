<?php
/**
 * 台灣銀行匯率查詢腳本
 * 此腳本使用cURL查詢台灣銀行的匯率資訊並解析HTML內容，然後以漂亮的前端介面呈現
 */

// 設定 HTTP 頭部防止快取
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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
    $error = '錯誤: ' . curl_error($ch);
    echo $error;
    exit;
}

// 關閉cURL
curl_close($ch);

// 建立一個空陣列來存儲匯率資訊
$exchangeRates = [];

// 使用DOMDocument和XPath進行更精確的解析
$dom = new DOMDocument();
@$dom->loadHTML($response);
$xpath = new DOMXPath($dom);

// 獲取更新時間
$updateTimeElement = $xpath->query('//span[@class="time"]')->item(0);
$updateTime = $updateTimeElement ? trim($updateTimeElement->textContent) : date('Y/m/d H:i');

// 獲取表格中的所有行（排除表頭）
$rows = $xpath->query('//table[contains(@class, "table")]/tbody/tr');

if ($rows->length > 0) {
    foreach ($rows as $row) {
        // 提取貨幣名稱
        $currencyElement = $xpath->query('.//div[contains(@class, "hidden-phone")]', $row)->item(0);
        if ($currencyElement) {
            $currencyName = trim($currencyElement->textContent);
            
            // 提取貨幣代碼 (如 USD, JPY 等)
            preg_match('/\(([A-Z]{3})\)/', $currencyName, $matches);
            $currencyCode = isset($matches[1]) ? $matches[1] : '';
            
            // 提取匯率
            $cashBuyElement = $xpath->query('.//td[@data-table="本行現金買入"]', $row)->item(0);
            $cashSellElement = $xpath->query('.//td[@data-table="本行現金賣出"]', $row)->item(0);
            $spotBuyElement = $xpath->query('.//td[@data-table="本行即期買入"]', $row)->item(0);
            $spotSellElement = $xpath->query('.//td[@data-table="本行即期賣出"]', $row)->item(0);
            
            $cashBuy = $cashBuyElement ? trim($cashBuyElement->textContent) : '-';
            $cashSell = $cashSellElement ? trim($cashSellElement->textContent) : '-';
            $spotBuy = $spotBuyElement ? trim($spotBuyElement->textContent) : '-';
            $spotSell = $spotSellElement ? trim($spotSellElement->textContent) : '-';
            
            $exchangeRates[] = [
                '貨幣' => $currencyName,
                '代碼' => $currencyCode,
                '現金買入' => $cashBuy,
                '現金賣出' => $cashSell,
                '即期買入' => $spotBuy,
                '即期賣出' => $spotSell
            ];
        }
    }
}

// 開始輸出HTML
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>台灣銀行匯率查詢結果</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976D2;
            --secondary-color: #2196F3;
            --accent-color: #FFC107;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --medium-gray: #e0e0e0;
            --dark-gray: #757575;
            --white: #ffffff;
            --red: #f44336;
            --green: #4CAF50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans TC', sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 20px 30px;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .header-left {
            flex: 1;
        }
        
        .update-time {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .refresh-btn {
            background-color: var(--accent-color);
            color: var(--text-color);
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .refresh-btn:hover {
            background-color: #FFD54F;
        }
        
        .refresh-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-radius: 50%;
            border-top-color: transparent;
            margin-right: 4px;
        }
        
        .refresh-btn.loading .refresh-icon {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .content {
            padding: 30px;
        }
        
        .filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .filter-btn {
            background-color: var(--medium-gray);
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .filter-btn.active {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            padding: 8px 12px;
            border: 1px solid var(--medium-gray);
            border-radius: 4px;
            font-size: 14px;
            width: 200px;
        }
        
        .rate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rate-table th, .rate-table td {
            padding: 12px 15px;
            text-align: left;
        }
        
        .rate-table th {
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: 500;
            position: sticky;
            top: 0;
        }
        
        .rate-table tr:nth-child(even) {
            background-color: var(--light-gray);
        }
        
        .rate-table tr:hover {
            background-color: rgba(33, 150, 243, 0.1);
        }
        
        .currency-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .currency-code {
            color: var(--dark-gray);
            font-size: 14px;
        }
        
        .flag {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--white);
            background-color: var(--primary-color);
        }
        
        .rate-value {
            font-family: 'Roboto Mono', monospace;
            text-align: right;
        }
        
        .cash-rate {
            color: var(--red);
        }
        
        .spot-rate {
            color: var(--green);
        }
        
        @media (max-width: 768px) {
            .container {
                border-radius: 0;
                box-shadow: none;
            }
            
            .content {
                padding: 15px;
            }
            
            .rate-table {
                font-size: 14px;
            }
            
            .rate-table th, .rate-table td {
                padding: 10px;
            }
            
            .filters {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-box {
                width: 100%;
                margin-top: 10px;
            }
            
            .search-input {
                width: 100%;
            }
        }
        
        .footer {
            background-color: var(--light-gray);
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: var(--dark-gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-left">
                <h1>台灣銀行匯率查詢結果</h1>
                <div class="update-time">最後更新時間：<?php echo $updateTime; ?></div>
            </div>
            <button id="refreshBtn" class="refresh-btn">
                <span class="refresh-icon"></span>刷新匯率
            </button>
        </header>
        
        <div class="content">
            <div class="filters">
                <div class="filter-group">
                    <button class="filter-btn active" data-filter="all">全部顯示</button>
                    <button class="filter-btn" data-filter="cash">現金匯率</button>
                    <button class="filter-btn" data-filter="spot">即期匯率</button>
                </div>
                
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="搜尋貨幣..." id="searchInput">
                </div>
            </div>
            
            <?php if (!empty($exchangeRates)): ?>
            <div class="table-container">
                <table class="rate-table" id="rateTable">
                    <thead>
                        <tr>
                            <th>貨幣</th>
                            <th class="cash-rate">現金買入</th>
                            <th class="cash-rate">現金賣出</th>
                            <th class="spot-rate">即期買入</th>
                            <th class="spot-rate">即期賣出</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exchangeRates as $rate): ?>
                        <tr>
                            <td>
                                <div class="currency-name">
                                    <span class="flag"><?php echo substr($rate['代碼'], 0, 1); ?></span>
                                    <?php echo $rate['貨幣']; ?>
                                </div>
                            </td>
                            <td class="rate-value cash-rate"><?php echo $rate['現金買入']; ?></td>
                            <td class="rate-value cash-rate"><?php echo $rate['現金賣出']; ?></td>
                            <td class="rate-value spot-rate"><?php echo $rate['即期買入']; ?></td>
                            <td class="rate-value spot-rate"><?php echo $rate['即期賣出']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="error-message">
                <p>無法解析匯率資訊，可能是台灣銀行網站結構已更改。</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>資料來源：<a href="https://rate.bot.com.tw/xrt" target="_blank">台灣銀行</a> | 本網站僅供參考，實際匯率請以台灣銀行官方公告為準。</p>
        </div>
    </div>
    
    <script>
        // 搜尋功能
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#rateTable tbody tr');
            
            rows.forEach(row => {
                const currencyText = row.querySelector('.currency-name').textContent.toLowerCase();
                if (currencyText.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // 匯率類型過濾功能
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // 更新按鈕狀態
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                const filterType = this.getAttribute('data-filter');
                const cashColumns = document.querySelectorAll('.cash-rate');
                const spotColumns = document.querySelectorAll('.spot-rate');
                
                // 根據過濾類型顯示/隱藏對應列
                if (filterType === 'cash') {
                    cashColumns.forEach(col => col.style.display = '');
                    spotColumns.forEach(col => col.style.display = 'none');
                } else if (filterType === 'spot') {
                    cashColumns.forEach(col => col.style.display = 'none');
                    spotColumns.forEach(col => col.style.display = '');
                } else {
                    cashColumns.forEach(col => col.style.display = '');
                    spotColumns.forEach(col => col.style.display = '');
                }
            });
        });
        
        // 刷新按鈕功能
        document.getElementById('refreshBtn').addEventListener('click', function() {
            // 添加加載動畫
            this.classList.add('loading');
            // 禁用按鈕
            this.disabled = true;
            
            // 重新載入頁面，強制從伺服器重新請求頁面而不使用快取
            location.reload(true);
        });
    </script>
</body>
</html>