<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = require_once __DIR__ . '/includes/db.php';

// 从URL中获取后缀
$suffix = isset($_GET['suffix']) ? $_GET['suffix'] : '';

// 检查数据库连接
if (!$db) {
    $error_msg = "数据库连接失败。\n";
    $db_path = __DIR__ . '/urls.db';
    
    if (!file_exists($db_path)) {
        $error_msg .= "数据库文件不存在\n";
    } else {
        $error_msg .= "数据库文件权限：" . substr(sprintf('%o', fileperms($db_path)), -4) . "\n";
    }
    
    die("<pre>" . htmlspecialchars($error_msg) . "</pre>");
}

try {
    // 查询数据库
    $stmt = $db->prepare("SELECT * FROM urls WHERE suffix = :suffix");
    if (!$stmt) {
        throw new Exception("准备SQL语句失败");
    }
    
    $stmt->bindValue(':suffix', $suffix);
    $result = $stmt->execute();
    
    if (!$result) {
        throw new Exception("执行查询失败");
    }
    
    $data = $result->fetchArray(SQLITE3_ASSOC);

    if ($data) {
        // 生成manifest.plist
        $manifest = file_get_contents(__DIR__ . '/templates/manifest.tpl');
        $manifest = str_replace(
            ['{{IPA_URL}}', '{{BUNDLE_ID}}', '{{APP_NAME}}'],
            [$data['ipa_url'], $data['bundle_id'], $data['app_name']],
            $manifest
        );
        
        // 创建临时manifest文件
        $manifest_path = __DIR__ . '/temp/' . uniqid() . '.plist';
        file_put_contents($manifest_path, $manifest);
        
        // 获取完整的manifest URL
        $manifest_url = 'https://' . $_SERVER['HTTP_HOST'] . '/temp/' . basename($manifest_path);
        
        // 确保URL是经过正确编码的
        $encoded_manifest_url = urlencode($manifest_url);
        
        // 添加时间变量
        $now = date('Y-m-d H:i:s');
        
        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <title>{$data['app_name']} - 应用安装</title>
            <script>
            function install() {
                var iOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                if (!iOS) {
                    alert('请使用iPhone或iPad访问此页面安装应用！\\n当前设备不是iOS设备。');
                    return;
                }
                
                var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
                if (!isSafari) {
                    alert('请使用Safari浏览器打开此页面！');
                    return;
                }

                var installUrl = 'itms-services://?action=download-manifest&url={$encoded_manifest_url}';
                window.location.href = installUrl;
                
                setTimeout(function() {
                    alert('如果没有弹出安装提示，请检查：\\n1. 是否允许安装企业应用\\n2. 证书是否已经信任\\n3. 设备是否接入互联网');
                }, 3000);
            }
            </script>
            <style>
                body { font-family: -apple-system, sans-serif; padding: 20px; text-align: center; }
                .install-btn { 
                    display: inline-block; 
                    padding: 15px 30px; 
                    background-color: #007AFF; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px;
                    font-size: 18px;
                    margin: 20px 0;
                }
                .warning {
                    color: red;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .qr-section {
                    margin-top: 30px;
                    padding: 20px;
                    border-top: 1px solid #eee;
                }
                
                .qr-section p {
                    color: #666;
                    margin-bottom: 15px;
                }
                
                #qrcode {
                    display: flex;
                    justify-content: center;
                    margin: 0 auto;
                }
                
                #qrcode img {
                    padding: 10px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }

                /* 添加页脚样式 */
                .footer {
                    margin-top: 40px;
                    padding: 20px;
                    text-align: center;
                    border-top: 1px solid #eee;
                }

                .copyright {
                    color: #666;
                    font-size: 0.875rem;
                }

                .powered-by {
                    margin-top: 5px;
                    font-size: 0.75rem;
                    color: #999;
                }

                .powered-by a {
                    color: #666;
                    text-decoration: none;
                    transition: color 0.3s;
                }

                .powered-by a:hover {
                    color: #333;
                }
            </style>
        </head>
        <body>
            <h2>{$data['app_name']} - 应用安装</h2>
            <div id="deviceWarning" class="warning" style="display:none;">
                请使用iOS设备的Safari浏览器访问此页面！
            </div>
            <button onclick='install()' class='install-btn'>点击安装{$data['app_name']}</button>
            <div style='margin-top: 20px; color: #666;'>
                <p>安装步骤：</p>
                <ol style='text-align: left;'>
                    <li>使用iOS设备的Safari浏览器访问此页面</li>
                    <li>点击上方"安装{$data['app_name']}"按钮</li>
                    <li>在弹出的确认框中点击"安装"</li>
                    <li>等待应用下载完成</li>
                    <li>如果是首次安装，请前往：设置 > 通用 > 描述文件与设备管理，信任开发者证书</li>
                </ol>
            </div>

            <!-- 添加二维码部分 -->
            <div class="qr-section">
                <p>或使用iOS设备扫描二维码安装{$data['app_name']}：</p>
                <div id="qrcode"></div>
            </div>

            <!-- 添加页脚版权信息 -->
            <footer class="footer">
                <div class="copyright">
                    <p>© <?= date('Y') ?> IPA 分发平台</p>
                    <p class="powered-by">Powered by <a href="javascript:void(0);">JieGe</a></p>
                </div>
            </footer>

            <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
            <script>
                // 检查设备类型并显示警告
                if(!/iPad|iPhone|iPod/.test(navigator.userAgent) || !/^((?!chrome|android).)*safari/i.test(navigator.userAgent)) {
                    document.getElementById('deviceWarning').style.display = 'block';
                }

                // 生成二维码
                window.onload = function() {
                    var currentUrl = window.location.href;
                    var qr = qrcode(0, 'M');
                    qr.addData(currentUrl);
                    qr.make();
                    document.getElementById('qrcode').innerHTML = qr.createImgTag(5);
                }
            </script>
        </body>
        </html>
HTML;
        exit;
    } else {
        http_response_code(404);
        echo "未找到安装链接";
    }
} catch (Exception $e) {
    error_log("安装错误: " . $e->getMessage());
    die("发生错误：" . htmlspecialchars($e->getMessage()));
}
?>