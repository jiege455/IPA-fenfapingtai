<?php
$db_path = __DIR__ . '/../urls.db';

try {
    // 检查 SQLite3 扩展
    if (!class_exists('SQLite3')) {
        error_log("SQLite3 扩展未安装");
        return false;
    }

    // 检查目录权限
    $db_dir = dirname($db_path);
    if (!is_writable($db_dir)) {
        error_log("目录不可写：" . $db_dir);
        return false;
    }

    // 检查数据库文件
    if (file_exists($db_path) && !is_writable($db_path)) {
        error_log("数据库文件不可写：" . $db_path);
        return false;
    }

    $db = new SQLite3($db_path);
    $db->busyTimeout(5000);
    
    // 测试数据库连接
    $test = $db->query('SELECT 1');
    if (!$test) {
        error_log("数据库连接测试失败");
        return false;
    }
    
    // 创建数据表
    $db->exec('CREATE TABLE IF NOT EXISTS urls (
        suffix TEXT PRIMARY KEY,
        ipa_url TEXT NOT NULL,
        app_name TEXT NOT NULL,
        bundle_id TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    
    return $db;

} catch (Exception $e) {
    error_log("数据库错误: " . $e->getMessage());
    return false;
}
?>