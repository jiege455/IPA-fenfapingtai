<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加时区设置
date_default_timezone_set('Asia/Shanghai');

// 首先只获取数据库连接
$db = require_once __DIR__ . '/includes/db.php';

// 检查数据库连接
if (!$db) {
    $error_msg = "数据库连接失败。\n";
    $db_path = __DIR__ . '/urls.db';
    
    if (!file_exists($db_path)) {
        $error_msg .= "数据库文件不存在\n";
    } else {
        $error_msg .= "数据库文件权限：" . substr(sprintf('%o', fileperms($db_path)), -4) . "\n";
    }
    
    $error_msg .= "目录权限：" . substr(sprintf('%o', fileperms(__DIR__)), -4) . "\n";
    $error_msg .= "当前用户：" . exec('whoami') . "\n";
    
    error_log($error_msg);
    header("Location: index.php?error=" . urlencode("数据库连接失败，请联系管理员"));
    exit;
}

try {
    // 获取POST数据
    $ipa_url = $_POST['ipa_url'] ?? '';
    $app_name = $_POST['app_name'] ?? '';
    $bundle_id = $_POST['bundle_id'] ?? '';
    $suffix = $_POST['suffix'] ?? '';

    // 验证输入
    if (empty($ipa_url) || !filter_var($ipa_url, FILTER_VALIDATE_URL)) {
        throw new Exception("IPA地址无效");
    }
    if (empty($suffix) || !preg_match('/^[a-zA-Z0-9]{3,8}$/', $suffix)) {
        throw new Exception("短链后缀需3-8位字母数字");
    }
    if (empty($app_name)) {
        throw new Exception("应用名称不能为空");
    }
    if (empty($bundle_id)) {
        throw new Exception("Bundle ID不能为空");
    }

    // 检查suffix是否已存在
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM urls WHERE suffix = :suffix");
    if (!$check_stmt) {
        throw new Exception("数据库查询准备失败");
    }
    
    $check_stmt->bindValue(':suffix', $suffix);
    $result = $check_stmt->execute();
    if (!$result) {
        throw new Exception("数据库查询执行失败");
    }
    
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row['count'] > 0) {
        throw new Exception("该短链后缀已被使用");
    }

    // 插入数据库
    $insert_stmt = $db->prepare("INSERT INTO urls (suffix, ipa_url, app_name, bundle_id, created_at) VALUES (:suffix, :ipa_url, :app_name, :bundle_id, datetime('now', 'localtime'))");
    if (!$insert_stmt) {
        throw new Exception("数据库插入准备失败");
    }
    
    $insert_stmt->bindValue(':suffix', $suffix);
    $insert_stmt->bindValue(':ipa_url', $ipa_url);
    $insert_stmt->bindValue(':app_name', $app_name);
    $insert_stmt->bindValue(':bundle_id', $bundle_id);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("数据库插入执行失败");
    }

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {
    error_log("创建错误: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>