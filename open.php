<?php
// 初始化默认设置
$protocol = isset($_POST['protocol']) ? $_POST['protocol'] : 'auto';
$domain_type = isset($_POST['domain_type']) ? $_POST['domain_type'] : 'original';
$batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 5;
$batch_delay = isset($_POST['batch_delay']) ? (int)$_POST['batch_delay'] : 3;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urls'])) {
    $rawUrls = $_POST['urls'];
    $processedUrls = [];
    
    // 分割输入的URL（按行分割）
    $urlList = preg_split('/\r\n|\r|\n/', $rawUrls);
    
    foreach ($urlList as $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }
        
        // 处理域名类型（www或根域名）
        if ($domain_type == 'root' || $domain_type == 'www') {
            // 先去除现有协议以便处理域名
            $noProtocol = preg_replace('~^(?:f|ht)tps?://~i', '', $url);
            
            // 根据选择添加或移除www
            if ($domain_type == 'root' && strpos($noProtocol, 'www.') === 0) {
                $noProtocol = substr($noProtocol, 4);
            } else if ($domain_type == 'www' && strpos($noProtocol, 'www.') !== 0) {
                $noProtocol = 'www.' . $noProtocol;
            }
            
            // 根据协议选择重建URL
            if ($protocol == 'http') {
                $url = 'http://' . $noProtocol;
            } else if ($protocol == 'https') {
                $url = 'https://' . $noProtocol;
            } else {
                // 自动模式下保留原始协议
                if (preg_match('~^(?:f|ht)tps?://~i', $url)) {
                    // 已有协议，重建URL
                    $originalProtocol = parse_url($url, PHP_URL_SCHEME);
                    $url = $originalProtocol . '://' . $noProtocol;
                } else {
                    // 无协议，根据默认协议添加
                    $url = 'http://' . $noProtocol;
                }
            }
        } else {
            // 保留原始域名结构，仅处理协议
            if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
                // 无协议，添加协议
                if ($protocol == 'https') {
                    $url = 'https://' . $url;
                } else {
                    // 默认使用http
                    $url = 'http://' . $url;
                }
            } else if ($protocol != 'auto') {
                // 已有协议但需要强制使用特定协议
                $noProtocol = preg_replace('~^(?:f|ht)tps?://~i', '', $url);
                $url = ($protocol == 'https' ? 'https://' : 'http://') . $noProtocol;
            }
        }
        
        // 验证URL格式
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $processedUrls[] = $url;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量打开网址工具</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        textarea {
            width: 100%;
            height: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .options-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .option-group {
            margin-bottom: 10px;
            min-width: 200px;
        }
        .option-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .option-group select, .option-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        #openButton, #batchOpenButton {
            background-color: #2196F3;
        }
        #openButton:hover, #batchOpenButton:hover {
            background-color: #0b7dda;
        }
        .copy-button {
            background-color: #9c27b0;
        }
        .copy-button:hover {
            background-color: #7b1fa2;
        }
        .url-count {
            margin-top: 10px;
            font-weight: bold;
        }
        .url-list {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            max-height: 300px;
            overflow-y: auto;
            background-color: #f9f9f9;
            position: relative;
        }
        .url-item {
            padding: 5px;
            border-bottom: 1px solid #eee;
            word-break: break-all;
        }
        .url-item:last-child {
            border-bottom: none;
        }
        .copy-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 100;
            display: none;
        }
        .batch-info {
            display: none;
            margin-top: 15px;
            padding: 10px;
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>批量打开网址工具</h1>
    
    <form method="post" action="">
        <div class="options-container">
            <div class="option-group">
                <div class="option-title">协议选择：</div>
                <select name="protocol" id="protocol">
                    <option value="auto" <?php echo $protocol == 'auto' ? 'selected' : ''; ?>>自动（保留原协议）</option>
                    <option value="http" <?php echo $protocol == 'http' ? 'selected' : ''; ?>>强制 HTTP</option>
                    <option value="https" <?php echo $protocol == 'https' ? 'selected' : ''; ?>>强制 HTTPS</option>
                </select>
            </div>
            
            <div class="option-group">
                <div class="option-title">域名类型：</div>
                <select name="domain_type" id="domain_type">
                    <option value="original" <?php echo $domain_type == 'original' ? 'selected' : ''; ?>>保持原样</option>
                    <option value="root" <?php echo $domain_type == 'root' ? 'selected' : ''; ?>>使用根域名（去掉www）</option>
                    <option value="www" <?php echo $domain_type == 'www' ? 'selected' : ''; ?>>使用www子域名</option>
                </select>
            </div>
            
            <div class="option-group">
                <div class="option-title">批量打开数量：</div>
                <input type="number" name="batch_size" id="batch_size" min="1" max="50" value="<?php echo $batch_size; ?>">
            </div>
            
            <div class="option-group">
                <div class="option-title">批次间隔（秒）：</div>
                <input type="number" name="batch_delay" id="batch_delay" min="1" max="60" value="<?php echo $batch_delay; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="urls">请输入需要打开的网址（每行一个）：</label>
            <textarea id="urls" name="urls" placeholder="例如：
www.example1.com
https://www.example2.com
example3.com"><?php echo isset($rawUrls) ? htmlspecialchars($rawUrls) : ''; ?></textarea>
        </div>
        
        <div class="buttons">
            <button type="submit">处理网址</button>
            <button type="button" id="copyInputButton" class="copy-button">复制输入内容</button>
            
            <?php if (isset($processedUrls) && count($processedUrls) > 0): ?>
                <button type="button" id="openButton">一次性打开全部（<?php echo count($processedUrls); ?>）</button>
                <button type="button" id="batchOpenButton">分批打开（每批<?php echo $batch_size; ?>个）</button>
                <button type="button" id="copyProcessedButton" class="copy-button">复制处理后的URL</button>
            <?php endif; ?>
        </div>
    </form>
    
    <div id="copyInputTooltip" class="copy-tooltip">已复制!</div>
    
    <?php if (isset($processedUrls) && count($processedUrls) > 0): ?>
        <div class="url-count">
            共处理 <?php echo count($processedUrls); ?> 个有效网址
        </div>
        
        <div class="batch-info" id="batchInfo">
            <div id="batchStatus">准备分批打开网址...</div>
            <div id="batchProgress"></div>
        </div>
        
        <div class="url-list">
            <?php foreach ($processedUrls as $index => $url): ?>
                <div class="url-item" data-index="<?php echo $index; ?>">
                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank"><?php echo htmlspecialchars($url); ?></a>
                </div>
            <?php endforeach; ?>
            <div id="copyProcessedTooltip" class="copy-tooltip">已复制!</div>
        </div>
        
        <!-- 隐藏的textarea用于存储处理后的URL，方便复制 -->
        <textarea id="processedUrlsText" style="position: absolute; left: -9999px;"><?php echo implode("\n", $processedUrls); ?></textarea>
        
        <script>
            // 一次性批量打开URL的功能
            document.getElementById('openButton').addEventListener('click', function() {
                const urls = <?php echo json_encode($processedUrls); ?>;
                
                // 确认是否要打开多个网址
                if (urls.length > 10) {
                    if (!confirm('您确定要同时打开 ' + urls.length + ' 个网址吗？这可能会导致浏览器卡顿。')) {
                        return;
                    }
                }
                
                // 批量打开网址
                urls.forEach(function(url) {
                    window.open(url, '_blank');
                });
            });
            
            // 分批打开URL的功能
            document.getElementById('batchOpenButton').addEventListener('click', function() {
                const urls = <?php echo json_encode($processedUrls); ?>;
                const batchSize = <?php echo $batch_size; ?>;
                const batchDelay = <?php echo $batch_delay; ?> * 1000; // 转换为毫秒
                const totalBatches = Math.ceil(urls.length / batchSize);
                const batchInfo = document.getElementById('batchInfo');
                const batchStatus = document.getElementById('batchStatus');
                const batchProgress = document.getElementById('batchProgress');
                
                // 显示批处理信息区域
                batchInfo.style.display = 'block';
                
                // 确认是否开始分批打开
                if (!confirm('将分' + totalBatches + '批打开共' + urls.length + '个网址，每批' + batchSize + '个，间隔' + (batchDelay/1000) + '秒。确定开始？')) {
                    batchInfo.style.display = 'none';
                    return;
                }
                
                // 禁用按钮，防止重复点击
                this.disabled = true;
                document.getElementById('openButton').disabled = true;
                
                // 高亮显示当前处理的URL
                function highlightUrl(index, highlight) {
                    const urlItem = document.querySelector(`.url-item[data-index="${index}"]`);
                    if (urlItem) {
                        urlItem.style.backgroundColor = highlight ? '#e3f2fd' : '';
                    }
                }
                
                // 分批打开函数
                let currentBatch = 0;
                
                function openNextBatch() {
                    if (currentBatch >= totalBatches) {
                        // 所有批次处理完毕
                        batchStatus.textContent = '所有URL已打开完成！';
                        document.getElementById('batchOpenButton').disabled = false;
                        document.getElementById('openButton').disabled = false;
                        return;
                    }
                    
                    // 计算当前批次的起始和结束索引
                    const startIndex = currentBatch * batchSize;
                    const endIndex = Math.min(startIndex + batchSize, urls.length);
                    
                    // 更新状态信息
                    batchStatus.textContent = `正在打开第 ${currentBatch + 1}/${totalBatches} 批（${startIndex + 1} 至 ${endIndex} 个网址）`;
                    batchProgress.textContent = `总进度: ${Math.round((currentBatch * batchSize / urls.length) * 100)}%`;
                    
                    // 打开当前批次的URL
                    for (let i = startIndex; i < endIndex; i++) {
                        highlightUrl(i, true);
                        window.open(urls[i], '_blank');
                    }
                    
                    // 准备打开下一批
                    currentBatch++;
                    
                    // 如果还有下一批，延时后再处理
                    if (currentBatch < totalBatches) {
                        setTimeout(function() {
                            // 取消之前批次的高亮显示
                            for (let i = startIndex; i < endIndex; i++) {
                                highlightUrl(i, false);
                            }
                            openNextBatch();
                        }, batchDelay);
                    } else {
                        // 最后一批处理完成
                        setTimeout(function() {
                            batchStatus.textContent = '所有URL已打开完成！';
                            batchProgress.textContent = '总进度: 100%';
                            document.getElementById('batchOpenButton').disabled = false;
                            document.getElementById('openButton').disabled = false;
                            
                            // 取消最后一批的高亮
                            for (let i = startIndex; i < endIndex; i++) {
                                highlightUrl(i, false);
                            }
                        }, 1000);
                    }
                }
                
                // 开始第一批处理
                openNextBatch();
            });
            
            // 复制输入框内容的功能
            document.getElementById('copyInputButton').addEventListener('click', function() {
                const inputTextarea = document.getElementById('urls');
                inputTextarea.select();
                document.execCommand('copy');
                
                // 显示复制成功提示
                const tooltip = document.getElementById('copyInputTooltip');
                tooltip.style.display = 'block';
                tooltip.style.top = (this.offsetTop - 30) + 'px';
                tooltip.style.left = (this.offsetLeft + this.offsetWidth / 2 - 30) + 'px';
                
                setTimeout(function() {
                    tooltip.style.display = 'none';
                }, 2000);
            });
            
            // 复制处理后URL的功能
            document.getElementById('copyProcessedButton').addEventListener('click', function() {
                const processedTextarea = document.getElementById('processedUrlsText');
                processedTextarea.select();
                document.execCommand('copy');
                
                // 显示复制成功提示
                const tooltip = document.getElementById('copyProcessedTooltip');
                tooltip.style.display = 'block';
                tooltip.style.top = (this.offsetTop - 30) + 'px';
                tooltip.style.left = (this.offsetLeft + this.offsetWidth / 2 - 30) + 'px';
                
                setTimeout(function() {
                    tooltip.style.display = 'none';
                }, 2000);
            });
            
            // 为现代浏览器添加Clipboard API支持
            if (navigator.clipboard) {
                document.getElementById('copyInputButton').addEventListener('click', function() {
                    navigator.clipboard.writeText(document.getElementById('urls').value)
                        .catch(err => console.error('复制失败:', err));
                });
                
                document.getElementById('copyProcessedButton').addEventListener('click', function() {
                    navigator.clipboard.writeText(document.getElementById('processedUrlsText').value)
                        .catch(err => console.error('复制失败:', err));
                });
            }
        </script>
    <?php endif; ?>
</body>
</html>