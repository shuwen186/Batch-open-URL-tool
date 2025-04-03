<?php
// --- Configuration ---
define('DEFAULT_PROTOCOL_IF_NONE', 'https'); // Default if 'auto' and none detected

// --- Initialization ---
$protocol = isset($_POST['protocol_choice']) ? $_POST['protocol_choice'] : 'auto'; // Default to 'auto'
$domainActions = isset($_POST['domain_action']) && is_array($_POST['domain_action']) ? $_POST['domain_action'] : [];
$batch_size = isset($_POST['batch_size']) ? max(1, (int)$_POST['batch_size']) : 10;
$batch_delay = isset($_POST['batch_delay']) ? max(0, (float)$_POST['batch_delay']) : 2; // Allow float for delay
$rawUrlsInput = isset($_POST['urls']) ? $_POST['urls'] : '';
$processedUrls = [];
$errorMessages = []; // Store ALL error messages

// --- Processing Logic (Only if form submitted) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urls'])) {
    // Validate selected protocol choice
    if (!in_array($protocol, ['auto', 'http', 'https'])) {
        $protocol = 'auto'; // Fallback to default if invalid value submitted
        $errorMessages[] = "无效的协议选项提交，已重置为'自动'。";
    }

    $urlList = preg_split('/\r\n|\r|\n/', $rawUrlsInput);

    foreach ($urlList as $originalLine => $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }

        $originalUrlHasScheme = preg_match('~^(?:f|ht)tps?://~i', $url);
        $urlToParse = $url;

        if (!$originalUrlHasScheme) {
            $urlToParse = 'http://' . $url; // Temporarily add scheme for parse_url
        }

        $parts = parse_url($urlToParse);

        if ($parts === false || !isset($parts['host'])) {
            $errorMessages[] = "无法解析第 " . ($originalLine + 1) . " 行: '" . htmlspecialchars(substr($url, 0, 50)) . (strlen($url) > 50 ? '...' : '') . "'";
            continue;
        }

        $host = $parts['host'];
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
        $fragment = $parts['fragment'] ?? '';
        $originalScheme = $originalUrlHasScheme ? ($parts['scheme'] ?? null) : null;

        // Domain Type Processing
        $processedHost = $host;
        if (in_array('ensure_root', $domainActions)) {
            if (strpos($processedHost, 'www.') === 0) {
                $processedHost = substr($processedHost, 4);
            }
        } elseif (in_array('ensure_www', $domainActions)) {
            $isIp = filter_var($processedHost, FILTER_VALIDATE_IP);
            if (!$isIp && strpos($processedHost, 'www.') !== 0) {
                 $processedHost = 'www.' . $processedHost;
            }
        }

        // Protocol Processing
        $finalScheme = '';
        if ($protocol === 'http' || $protocol === 'https') {
            $finalScheme = $protocol;
        } else { // 'auto'
            $finalScheme = $originalScheme ?: DEFAULT_PROTOCOL_IF_NONE;
        }

        // Reconstruct URL
        $finalUrl = $finalScheme . '://' . $processedHost;
        $finalUrl .= (!empty($path) ? $path : '');
        $finalUrl .= (!empty($query) ? '?' . $query : '');
        $finalUrl .= (!empty($fragment) ? '#' . $fragment : '');

        // Final Validation
        if (filter_var($finalUrl, FILTER_VALIDATE_URL)) {
            $processedUrls[] = $finalUrl;
        } else {
             $errorMessages[] = "处理后URL无效 (来自第 " . ($originalLine + 1) . " 行): '" . htmlspecialchars(substr($finalUrl, 0, 50)) . (strlen($finalUrl) > 50 ? '...' : '') . "'";
        }
    }
    $processedUrls = array_values(array_unique($processedUrls));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量网址处理与打开工具</title>
    <style>
        /* Modern UI Styles */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --input-bg: #fff;
            --body-bg: #f1f3f5;
            --container-bg: #ffffff;
            --font-family-sans-serif: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            --border-radius: 0.3rem;
            --box-shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: var(--font-family-sans-serif);
            line-height: 1.6;
            background-color: var(--body-bg);
            color: var(--dark-color);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 30px 40px;
            background-color: var(--container-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
        }

        h1 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        fieldset.options-container {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            background-color: var(--light-color);
        }
        fieldset.options-container legend {
            font-weight: 600; padding: 0 10px; color: var(--dark-color); font-size: 1.1em;
        }
        .options-grid {
             display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px;
        }
        .option-group .option-title {
            font-weight: 600; margin-bottom: 10px; font-size: 0.95em; color: #495057;
        }

        .checkbox-group label, .radio-group label {
            display: flex; align-items: center; margin-bottom: 8px; font-weight: normal; cursor: pointer; font-size: 0.95rem; transition: color 0.2s ease;
        }
        .checkbox-group label:hover, .radio-group label:hover { color: var(--primary-color); }
        input[type="checkbox"], input[type="radio"] {
            margin-right: 8px; cursor: pointer; transform: scale(1.15); accent-color: var(--primary-color);
        }
        input[type="checkbox"]:focus, input[type="radio"]:focus {
             outline: 2px solid rgba(0, 123, 255, 0.5); outline-offset: 2px;
         }

        .form-group { margin-bottom: 20px; }
        label:not(.checkbox-label):not(.radio-label) {
            display: block; margin-bottom: 8px; font-weight: 600; font-size: 1rem;
        }
        textarea, select, input[type="number"] {
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: var(--border-radius); box-sizing: border-box; font-size: 1rem; background-color: var(--input-bg); transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        textarea { min-height: 200px; resize: vertical; }
        textarea:focus, select:focus, input:focus {
            border-color: var(--primary-color); outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .buttons { display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap; }
        button {
            padding: 12px 22px; border: none; border-radius: var(--border-radius); cursor: pointer; font-size: 1rem; font-weight: 500; transition: all 0.2s ease; white-space: nowrap; box-shadow: var(--box-shadow-sm);
        }
        button:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1); }
        button:active:not(:disabled) { transform: translateY(0px); box-shadow: var(--box-shadow-sm); }
        button:disabled { background-color: #adb5bd !important; cursor: not-allowed; color: #fff; opacity: 0.7; }

        .submit-button { background-color: var(--primary-color); color: white; }
        .action-button { background-color: var(--info-color); color: white; }
        .copy-button { background-color: #6f42c1; color: white; }
        .clear-button { background-color: var(--danger-color); color: white; }

        .url-count, .status-message, .error-list {
            margin-top: 20px; padding: 15px 20px; border-radius: var(--border-radius); font-size: 0.95rem; border: 1px solid transparent;
        }
        .url-count { background-color: #e9ecef; border-color: var(--border-color); color: var(--dark-color); font-weight: 600;}
        .status-message { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; display: none; }
        .status-message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .status-message.warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .status-message.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }

        /* Collapsible Error List Style */
        .error-list {
            background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 18px; border-radius: var(--border-radius); margin-top: 20px;
        }
        .error-summary {
            display: flex; justify-content: space-between; align-items: center; cursor: pointer; /* Make summary clickable */
        }
         .error-summary strong { font-weight: 600; }
         .error-count-badge {
            font-weight: normal; font-size: 0.9em; background-color: #721c24; color: white; padding: 3px 8px; border-radius: var(--border-radius); white-space: nowrap; margin-left: 10px; /* Space from heading */
         }
        .error-toggle-button { /* Style the button/link look */
             background: none; border: none; color: #721c24; cursor: pointer; font-size: 0.9em; padding: 0 5px; margin-left: auto; /* Push to the right */
             display: inline-flex; align-items: center;
         }
         .error-toggle-button .arrow { /* Style for the arrow */
             display: inline-block;
             margin-left: 4px;
             transition: transform 0.2s ease-in-out;
         }
         .error-toggle-button.expanded .arrow {
             transform: rotate(90deg);
         }
        #errorDetailsList {
            display: none; /* Initially hidden */
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(114, 28, 36, 0.3); /* Separator line */
            max-height: 250px; /* Limit height and make scrollable */
            overflow-y: auto;
        }
        #errorDetailsList ul {
            list-style: disc;
            margin: 0 0 0 20px;
            padding: 0;
        }
        #errorDetailsList li {
             margin-bottom: 5px;
             font-size: 0.9em;
             line-height: 1.4;
             word-break: break-word;
        }


        .url-list {
            margin-top: 25px; border: 1px solid var(--border-color); border-radius: var(--border-radius); max-height: 400px; overflow-y: auto; background-color: #fff; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .url-item { padding: 10px 15px; border-bottom: 1px solid #f1f3f5; font-size: 0.95em; transition: background-color 0.2s; word-break: break-all; }
        .url-item:last-child { border-bottom: none; }
        .url-item a { color: var(--primary-color); text-decoration: none; }
        .url-item a:hover { text-decoration: underline; color: #0056b3; }
        .url-item.processing { background-color: rgba(0, 123, 255, 0.1) !important; font-weight: 600; }

        .copy-tooltip { position: fixed; background-color: rgba(0, 0, 0, 0.8); color: white; padding: 8px 14px; border-radius: var(--border-radius); font-size: 0.85em; z-index: 1000; display: none; pointer-events: none; }

         @media (max-width: 768px) {
             .container { padding: 20px; }
             h1 { font-size: 1.8em; }
             .buttons { flex-direction: column; align-items: stretch; }
             button { width: 100%; }
             .options-grid { grid-template-columns: 1fr; }
             .error-summary { flex-wrap: wrap; } /* Allow wrap on small screens */
             .error-toggle-button { margin-left: 10px; }
         }

    </style>
</head>
<body>
    <div class="container">
        <h1>批量网址处理与打开工具</h1>

        <form method="post" action="">
            <fieldset class="options-container">
                 <legend>处理选项</legend>
                <div class="options-grid">
                    <!-- Protocol Radios -->
                    <div class="option-group">
                        <div class="option-title">协议处理：</div>
                        <div class="radio-group">
                            <label class="radio-label" for="proto_auto"> <input type="radio" name="protocol_choice" id="proto_auto" value="auto" <?php echo $protocol == 'auto' ? 'checked' : ''; ?>> 自动 (无协议默认 <?php echo strtoupper(DEFAULT_PROTOCOL_IF_NONE); ?>) </label>
                             <label class="radio-label" for="proto_http"> <input type="radio" name="protocol_choice" id="proto_http" value="http" <?php echo $protocol == 'http' ? 'checked' : ''; ?>> 强制 HTTP </label>
                            <label class="radio-label" for="proto_https"> <input type="radio" name="protocol_choice" id="proto_https" value="https" <?php echo $protocol == 'https' ? 'checked' : ''; ?>> 强制 HTTPS </label>
                        </div>
                    </div>
                    <!-- Domain Type Checkboxes -->
                    <div class="option-group">
                        <div class="option-title">域名处理：</div>
                        <div class="checkbox-group">
                            <label class="checkbox-label" for="ensure_root"> <input type="checkbox" name="domain_action[]" id="ensure_root" value="ensure_root" <?php echo in_array('ensure_root', $domainActions) ? 'checked' : ''; ?>> 根域名 (去 www) </label>
                            <label class="checkbox-label" for="ensure_www"> <input type="checkbox" name="domain_action[]" id="ensure_www" value="ensure_www" <?php echo in_array('ensure_www', $domainActions) ? 'checked' : ''; ?>> www 子域名 (加 www) </label>
                        </div>
                    </div>
                    <!-- Batch Options -->
                    <div class="option-group">
                        <div class="option-title">每批打开数量：</div>
                        <input type="number" name="batch_size" id="batch_size" min="1" max="100" value="<?php echo htmlspecialchars($batch_size); ?>">
                    </div>
                    <div class="option-group">
                        <div class="option-title">批次间隔（秒）：</div>
                        <input type="number" name="batch_delay" id="batch_delay" min="0" max="60" step="0.1" value="<?php echo htmlspecialchars($batch_delay); ?>">
                    </div>
                </div><!-- /options-grid -->
            </fieldset>

            <div class="form-group">
                <label for="urls">输入网址（每行一个）：</label>
                <textarea id="urls" name="urls" placeholder="例如：
example1.com
https://www.example2.com/path?q=1
192.168.1.1
sub.domain.net"><?php echo htmlspecialchars($rawUrlsInput); ?></textarea>
            </div>

            <div class="buttons">
                 <button type="submit" class="submit-button">处理网址</button>
                <button type="button" id="copyInputButton" class="copy-button">复制输入</button>

                <?php if (!empty($processedUrls)): ?>
                    <button type="button" id="openButton" class="action-button">打开全部 (<?php echo count($processedUrls); ?>)</button>
                    <button type="button" id="batchOpenButton" class="action-button">分批打开</button>
                    <button type="button" id="copyProcessedButton" class="copy-button">复制结果</button>
                <?php endif; ?>
                 <button type="button" id="clearInputButton" class="clear-button">清空输入</button>
            </div>
        </form>

         <div id="statusMessage" class="status-message"></div>
        <div id="copyTooltip" class="copy-tooltip">已复制!</div>

        <!-- Collapsible Error Display Block -->
        <?php if (!empty($errorMessages)):
            $errorCount = count($errorMessages);
        ?>
            <div class="error-list">
                <div class="error-summary" id="errorSummaryHeader" role="button" tabindex="0" aria-expanded="false" aria-controls="errorDetailsList">
                    <strong>处理时遇到问题：</strong>
                    <div> <!-- Wrapper for badge and button -->
                         <span class="error-count-badge"><?php echo $errorCount; ?> 个错误</span>
                         <button type="button" id="toggleErrorsBtn" class="error-toggle-button" aria-expanded="false" aria-controls="errorDetailsList">
                             详情 <span class="arrow">▶</span>
                         </button>
                    </div>
                </div>
                <div id="errorDetailsList"> <!-- Initially hidden -->
                    <ul>
                        <?php foreach ($errorMessages as $msg): ?>
                            <li><?php echo $msg; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        <!-- End Collapsible Error Display Block -->


        <?php if (!empty($processedUrls)): ?>
            <div class="url-count">
                成功处理 <?php echo count($processedUrls); ?> 个有效网址
            </div>

            <div class="url-list" id="processedUrlList">
                <?php foreach ($processedUrls as $index => $url): ?>
                    <div class="url-item" data-index="<?php echo $index; ?>">
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($url); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>

            <textarea id="processedUrlsText" style="position: absolute; left: -9999px;"><?php echo implode("\n", $processedUrls); ?></textarea>
        <?php endif; ?>

    </div><!-- /container -->

    <script>
        // --- Elements ---
        const openButton = document.getElementById('openButton');
        const batchOpenButton = document.getElementById('batchOpenButton');
        const copyInputButton = document.getElementById('copyInputButton');
        const copyProcessedButton = document.getElementById('copyProcessedButton');
        const clearInputButton = document.getElementById('clearInputButton');
        const urlsTextarea = document.getElementById('urls');
        const processedUrlsTextarea = document.getElementById('processedUrlsText');
        const processedUrlListDiv = document.getElementById('processedUrlList');
        const statusMessageDiv = document.getElementById('statusMessage');
        const copyTooltip = document.getElementById('copyTooltip');
        // Error toggle elements
        const errorSummaryHeader = document.getElementById('errorSummaryHeader'); // Clickable summary
        const toggleErrorsBtn = document.getElementById('toggleErrorsBtn'); // Optional: direct button if needed
        const errorDetailsList = document.getElementById('errorDetailsList');

        // --- Data ---
        const processedUrls = <?php echo isset($processedUrls) && !empty($processedUrls) ? json_encode($processedUrls) : '[]'; ?>;
        const batchSize = <?php echo isset($batch_size) ? $batch_size : 10; ?>;
        const batchDelay = <?php echo isset($batch_delay) ? ($batch_delay * 1000) : 2000; ?>;

        // --- Helper Functions (showStatus, hideStatus, showTooltip, copyTextToClipboard, highlightUrlItem) ---
        function showStatus(message, type = 'info') { /* ... same ... */ if (!statusMessageDiv) return; statusMessageDiv.textContent = message; statusMessageDiv.className = 'status-message'; if (type === 'error') statusMessageDiv.classList.add('error'); if (type === 'warning') statusMessageDiv.classList.add('warning'); if (type === 'success') statusMessageDiv.classList.add('success'); statusMessageDiv.style.display = 'block'; }
        function hideStatus() { /* ... same ... */ if (statusMessageDiv) statusMessageDiv.style.display = 'none'; }
        function showTooltip(button) { /* ... same ... */ if (!copyTooltip || !button) return; const rect = button.getBoundingClientRect(); copyTooltip.style.left = `${rect.left + window.scrollX + (rect.width / 2) - (copyTooltip.offsetWidth / 2)}px`; copyTooltip.style.top = `${rect.top + window.scrollY - copyTooltip.offsetHeight - 5}px`; copyTooltip.style.display = 'block'; setTimeout(() => { copyTooltip.style.display = 'none'; }, 1500); }
        async function copyTextToClipboard(text, button) { /* ... same ... */ if (!text) { showStatus('没有内容可复制。', 'warning'); return; } try { if (navigator.clipboard) { await navigator.clipboard.writeText(text); showTooltip(button); } else { const textArea = document.createElement("textarea"); textArea.value = text; textArea.style.position = "fixed"; textArea.style.left = "-9999px"; document.body.appendChild(textArea); textArea.focus(); textArea.select(); try { document.execCommand('copy'); showTooltip(button); } catch (err) { throw new Error('回退复制方法失败'); } finally { document.body.removeChild(textArea); } } } catch (err) { console.error('复制失败:', err); showStatus(`复制失败: ${err.message}. 请尝试手动复制。`, 'error'); } }
        function highlightUrlItem(index, highlight = true) { /* ... same ... */ if (!processedUrlListDiv) return; const item = processedUrlListDiv.querySelector(`.url-item[data-index="${index}"]`); if (item) { if (highlight) { item.classList.add('processing'); item.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } else { item.classList.remove('processing'); } } }


        // --- Event Listeners ---

        // Error List Toggle
        if (errorSummaryHeader && errorDetailsList && toggleErrorsBtn) {
             // Function to toggle state
            const toggleErrorDetails = () => {
                const isExpanded = errorDetailsList.style.display === 'block';
                errorDetailsList.style.display = isExpanded ? 'none' : 'block';
                errorSummaryHeader.setAttribute('aria-expanded', !isExpanded);
                toggleErrorsBtn.setAttribute('aria-expanded', !isExpanded);
                toggleErrorsBtn.classList.toggle('expanded', !isExpanded);
                // Update button text/icon (optional)
                 const arrowSpan = toggleErrorsBtn.querySelector('.arrow');
                 if(arrowSpan) arrowSpan.textContent = isExpanded ? '▶' : '▼';
                 // toggleErrorsBtn.firstChild.textContent = isExpanded ? '详情 ' : '收起 '; // If text changes needed
            };

            // Add listener to the summary header (more clickable area)
            errorSummaryHeader.addEventListener('click', toggleErrorDetails);
             // Make it keyboard accessible
             errorSummaryHeader.addEventListener('keydown', (event) => {
                 if (event.key === 'Enter' || event.key === ' ') {
                     toggleErrorDetails();
                     event.preventDefault(); // Prevent spacebar scrolling
                 }
             });
        }


        // Other listeners remain the same
        if (openButton) { openButton.addEventListener('click', () => { /* ... Open All logic ... */ if (!processedUrls || processedUrls.length === 0) return; const urlCount = processedUrls.length; let confirmMsg = `您确定要同时打开 ${urlCount} 个网址吗？`; if (urlCount > 20) { confirmMsg += `\n\n警告：打开大量标签页可能会导致浏览器响应缓慢或崩溃，并可能被弹出窗口阻止程序拦截。`; } if (urlCount <= 10 || confirm(confirmMsg)) { hideStatus(); showStatus(`尝试打开 ${urlCount} 个网址... 请允许弹出窗口。`, 'info'); let openedCount = 0, blockedCount = 0; processedUrls.forEach(url => { const newWindow = window.open(url, '_blank', 'noopener,noreferrer'); if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') { blockedCount++; } else { openedCount++; } }); if (blockedCount > 0) { showStatus(`完成！成功打开 ${openedCount} 个，可能被阻止 ${blockedCount} 个。`, 'warning'); } else { showStatus(`完成！成功打开所有 ${openedCount} 个网址。`, 'success'); } } else { showStatus('打开操作已取消。', 'info'); } }); }
        if (batchOpenButton) { batchOpenButton.addEventListener('click', async () => { /* ... Batch Open logic ... */ if (!processedUrls || processedUrls.length === 0) return; const totalUrls = processedUrls.length; const totalBatches = Math.ceil(totalUrls / batchSize); let confirmMsg = `将分 ${totalBatches} 批打开共 ${totalUrls} 个网址，每批最多 ${batchSize} 个，间隔 ${batchDelay / 1000} 秒。确定开始？`; if (totalUrls > 50) { confirmMsg += `\n\n请确保浏览器允许弹出窗口。`; } if (!confirm(confirmMsg)) { showStatus('分批打开操作已取消。', 'info'); return; } batchOpenButton.disabled = true; if (openButton) openButton.disabled = true; hideStatus(); let openedCount = 0, blockedCount = 0; for (let batchNum = 0; batchNum < totalBatches; batchNum++) { const startIndex = batchNum * batchSize; const endIndex = Math.min(startIndex + batchSize, totalUrls); showStatus(`正在处理第 ${batchNum + 1}/${totalBatches} 批 (网址 ${startIndex + 1} - ${endIndex})...`, 'info'); const urlsInBatchIndices = []; for (let i = startIndex; i < endIndex; i++) { highlightUrlItem(i, true); urlsInBatchIndices.push(i); const url = processedUrls[i]; const newWindow = window.open(url, '_blank', 'noopener,noreferrer'); if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') { blockedCount++; showStatus(`打开 ${url.substring(0, 50)}... 可能已被阻止 (${blockedCount}个被阻止)。正在处理第 ${batchNum + 1}/${totalBatches} 批...`, 'warning'); } else { openedCount++; } await new Promise(resolve => setTimeout(resolve, 100)); } urlsInBatchIndices.forEach(idx => highlightUrlItem(idx, false)); if (batchNum < totalBatches - 1) { showStatus(`第 ${batchNum + 1}/${totalBatches} 批完成。暂停 ${batchDelay / 1000} 秒... (已打开 ${openedCount}, 阻止 ${blockedCount})`, 'info'); await new Promise(resolve => setTimeout(resolve, batchDelay)); } } showStatus(`所有批次处理完成！共打开 ${openedCount} 个，可能被阻止 ${blockedCount} 个。`, blockedCount > 0 ? 'warning' : 'success'); batchOpenButton.disabled = false; if (openButton) openButton.disabled = false; }); }
        if (copyInputButton) { copyInputButton.addEventListener('click', function() { copyTextToClipboard(urlsTextarea ? urlsTextarea.value : '', this); }); }
        if (copyProcessedButton) { copyProcessedButton.addEventListener('click', function() { copyTextToClipboard(processedUrlsTextarea ? processedUrlsTextarea.value : '', this); }); }
        if (clearInputButton && urlsTextarea) { clearInputButton.addEventListener('click', () => { urlsTextarea.value = ''; hideStatus(); }); }

    </script>

</body>
</html>