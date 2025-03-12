<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加时区设置
date_default_timezone_set('Asia/Shanghai');

// 检查 SQLite3 扩展
if (!extension_loaded('sqlite3')) {
    die("错误：SQLite3 扩展未安装");
}

$db = require_once __DIR__ . '/includes/db.php';

// 检查数据库连接
if (!$db) {
    // 检查数据库文件
    $db_path = __DIR__ . '/urls.db';
    $error_msg = "数据库连接失败。\n";
    
    if (!file_exists($db_path)) {
        $error_msg .= "数据库文件不存在\n";
    } else {
        $error_msg .= "数据库文件权限：" . substr(sprintf('%o', fileperms($db_path)), -4) . "\n";
    }
    
    $error_msg .= "目录权限：" . substr(sprintf('%o', fileperms(__DIR__)), -4) . "\n";
    $error_msg .= "当前用户：" . exec('whoami') . "\n";
    
    die("<pre>" . htmlspecialchars($error_msg) . "</pre>");
}

// 获取历史记录
$history = [];
try {
    $result = $db->query("SELECT * FROM urls ORDER BY created_at DESC LIMIT 20");
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $history[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("查询错误: " . $e->getMessage());
    $history = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPA 分发平台</title>
    <link rel="stylesheet" href="assets/style.css">
    <!-- 添加二维码生成库 -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
</head>
<body>
    <div class="glass-container">
        <!-- 头部 -->
        <header class="header">
            <h1>IPA 分发平台</h1>
            <p>快速生成 iOS 应用分发链接</p>
        </header>

        <!-- 主要内容区 -->
        <main class="content-box">
            <!-- 输入表单 -->
            <form action="create.php" method="post" class="input-form">
                <div class="form-group">
                    <label>IPA 文件地址</label>
                    <input type="url" name="ipa_url" required 
                           placeholder="请输入可直接下载的IPA文件地址">
                </div>
                
                <div class="form-group">
                    <label>应用名称</label>
                    <input type="text" name="app_name" required
                           placeholder="请输入应用显示名称">
                </div>
                
                <div class="form-group">
                    <label>Bundle ID</label>
                    <input type="text" name="bundle_id" required
                           placeholder="如: com.example.app">
                </div>
                
                <div class="form-group">
                    <label>短链后缀</label>
                    <input type="text" name="suffix" required 
                           pattern="[A-Za-z0-9]{3,8}"
                           placeholder="3-8位字母数字组合">
                    <div class="suffix-suggestions">
                        建议：
                        <?php
                        // 生成随机建议的短链
                        function generateSuggestion() {
                            $chars = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; // 排除容易混淆的字符
                            $length = rand(3, 6);
                            $suggestion = '';
                            for ($i = 0; $i < $length; $i++) {
                                $suggestion .= $chars[rand(0, strlen($chars) - 1)];
                            }
                            return $suggestion;
                        }
                        
                        // 生成3个建议并检查是否已存在
                        $suggestions = [];
                        for ($i = 0; $i < 3; $i++) {
                            do {
                                $suggestion = generateSuggestion();
                                $stmt = $db->prepare("SELECT COUNT(*) as count FROM urls WHERE suffix = :suffix");
                                $stmt->bindValue(':suffix', $suggestion);
                                $result = $stmt->execute();
                                $row = $result->fetchArray(SQLITE3_ASSOC);
                                $exists = $row['count'] > 0;
                            } while ($exists);
                            $suggestions[] = $suggestion;
                        }
                        
                        echo implode(', ', array_map(function($s) {
                            return "<span class='suggestion' onclick='useSuggestion(this)'>" . htmlspecialchars($s) . "</span>";
                        }, $suggestions));
                        ?>
                    </div>
                </div>

                <button type="submit" class="submit-btn">生成安装链接</button>
                <div class="form-tip">
                    提示：生成的链接将显示在下方的"最近生成记录"中
                </div>
            </form>

            <!-- 历史记录 -->
            <?php if (!empty($history)): ?>
            <div class="history-section">
                <h2>最近生成记录（只保留24小时）</h2>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                    <div class="history-item">
                        <div class="app-info">
                            <span class="app-name">名称:<?= htmlspecialchars($item['app_name']) ?></span>
                            <span class="bundle-id">Bundle ID:<?= htmlspecialchars($item['bundle_id']) ?></span>
                            <div class="link-info">
                                <span class="created-time">创建时间：<?= date('Y-m-d H:i:s', strtotime($item['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="actions">
                            <a href="/install.php?suffix=<?= htmlspecialchars($item['suffix']) ?>" 
                               class="install-btn" 
                               target="_blank">
                                安装
                            </a>
                            <button class="copy-btn" 
                                    data-url="<?= htmlspecialchars($_SERVER['HTTP_HOST'] . '/install.php?suffix=' . $item['suffix']) ?>">
                                复制链接
                            </button>
                            <button class="qr-btn" 
                                    onclick="showQRCode(this)" 
                                    data-url="<?= 'https://' . htmlspecialchars($_SERVER['HTTP_HOST'] . '/install.php?suffix=' . $item['suffix']) ?>">
                                二维码
                            </button>
                        </div>
                        <div class="qr-container" style="display: none;">
                            <!-- 二维码将在这里生成 -->
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
        
        <!-- 添加页脚版权信息 -->
        <footer class="footer">
            <div class="copyright">
                <p>© <?= date('Y') ?> IPA 分发平台</p>
                <p class="footer-links">
                    <a href="javascript:void(0);">使用条款</a>
                    <span class="separator">|</span>
                    <a href="javascript:void(0);">隐私政策</a>
                </p>
                <p class="powered-by">Powered by <a href="javascript:void(0);">JieGe</a></p>
            </div>
        </footer>
    </div>

    <!-- 二维码弹窗 -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <div id="qrcode"></div>
            <p class="qr-tip">扫描二维码安装应用</p>
        </div>
    </div>

    <script>
    // 复制链接功能
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = 'https://' + btn.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                const originalText = btn.textContent;
                btn.textContent = '已复制';
                setTimeout(() => btn.textContent = originalText, 2000);
            });
        });
    });

    // 二维码生成和显示功能
    function showQRCode(button) {
        const modal = document.getElementById('qrModal');
        const qrcodeDiv = document.getElementById('qrcode');
        const url = button.dataset.url; // 这里已经是完整的URL了
        
        // 清空之前的二维码
        qrcodeDiv.innerHTML = '';
        
        // 生成二维码
        const qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        
        // 显示二维码
        qrcodeDiv.innerHTML = qr.createImgTag(5);
        
        // 显示弹窗
        modal.style.display = 'block';
    }

    // 关闭弹窗
    document.querySelector('.close-btn').onclick = function() {
        document.getElementById('qrModal').style.display = 'none';
    }

    // 点击弹窗外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('qrModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function useSuggestion(element) {
        document.querySelector('input[name="suffix"]').value = element.textContent;
    }
    </script>

    <style>
    /* 添加新样式 */
    .link-info {
        margin-top: 5px;
        font-size: 0.875rem;
        color: #666;
    }

    .qr-btn {
        background: #4CAF50;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.3s;
    }

    .qr-btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }

    /* 弹窗样式 */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: white;
        margin: 15% auto;
        padding: 20px;
        border-radius: 10px;
        width: 300px;
        text-align: center;
        position: relative;
    }

    .close-btn {
        position: absolute;
        right: 10px;
        top: 5px;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .close-btn:hover {
        color: #000;
    }

    #qrcode {
        margin: 20px 0;
    }

    #qrcode img {
        margin: 0 auto;
    }

    .qr-tip {
        color: #666;
        margin-top: 10px;
    }

    /* 响应式调整 */
    @media (max-width: 768px) {
        .history-item {
            flex-direction: column;
            gap: 1rem;
        }
        
        .actions {
            width: 100%;
            justify-content: space-between;
        }
    }

    .suffix-suggestions {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: #666;
    }

    .suggestion {
        display: inline-block;
        padding: 2px 8px;
        margin: 0 4px;
        background: #e0e7ff;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .suggestion:hover {
        background: #c7d2fe;
        transform: translateY(-1px);
    }

    /* 添加页脚样式 */
    .footer {
        margin-top: 40px;
        padding: 20px;
        text-align: center;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .copyright {
        color: rgba(255,255,255,0.8);
        font-size: 0.875rem;
    }

    .footer-links {
        margin: 10px 0;
    }

    .footer-links a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: color 0.3s;
    }

    .footer-links a:hover {
        color: #fff;
    }

    .separator {
        margin: 0 10px;
        color: rgba(255,255,255,0.5);
    }

    .powered-by {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.6);
    }

    .powered-by a {
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: color 0.3s;
    }

    .powered-by a:hover {
        color: #fff;
    }

    .form-tip {
        margin-top: 10px;
        padding: 8px;
        color: #666;
        font-size: 0.9em;
        text-align: center;
        border-radius: 4px;
        background-color: #f8f9fa;
        border: 1px dashed #ddd;
    }
    </style>
</body>
</html>