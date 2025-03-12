<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加时区设置
date_default_timezone_set('Asia/Shanghai');

// 连接数据库
$db = require_once __DIR__ . '/includes/db.php';

if (!$db) {
    die("数据库连接失败");
}

try {
    // 直接清理所有记录
    $stmt = $db->prepare("DELETE FROM urls");
    $result = $stmt->execute();
    $db_changes = $db->changes();
    
    // 清理 temp 文件夹中的所有文件
    $temp_dir = __DIR__ . '/temp';
    $files_removed = 0;
    
    if (is_dir($temp_dir)) {
        $files = scandir($temp_dir);
        
        foreach ($files as $file) {
            $file_path = $temp_dir . '/' . $file;
            // 跳过 . 和 .. 目录
            if ($file == '.' || $file == '..') {
                continue;
            }
            
            // 删除所有文件
            if (is_file($file_path) && unlink($file_path)) {
                $files_removed++;
            }
        }
    }
    
    // 记录日志
    $log_message = date('Y-m-d H:i:s') . " - 已清理数据库 {$db_changes} 条记录，删除临时文件 {$files_removed} 个\n";
    file_put_contents(__DIR__ . '/clean_history.log', $log_message, FILE_APPEND);
    
    echo "清理完成，共删除数据库记录 {$db_changes} 条，临时文件 {$files_removed} 个";

} catch (Exception $e) {
    $error_message = date('Y-m-d H:i:s') . " - 清理失败：" . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/clean_history.log', $error_message, FILE_APPEND);
    echo "清理失败：" . $e->getMessage();
} 