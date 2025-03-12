# IPA 分发平台搭建教程

## 一、环境要求
1. PHP 7.4+ 或 PHP 8.0+
2. SQLite3 扩展
3. 支持 HTTPS 的 Web 服务器（必需，因为 iOS 要求使用 HTTPS）
4. 域名（需要备案）也可以用香港服务器，不用备案。
5. clean_history.php 这个文件是执行清理记录的。

## 二、项目结构说明
```
ipa-deploy/
├── assets/
│   └── style.css         # 样式文件
├── includes/
│   └── db.php           # 数据库配置
├── index.php            # 主页面
├── create.php           # 创建链接
├── install.php          # 安装页面
├── schema.sql          # 数据库结构
└── urls.db             # SQLite 数据库文件
```

## 三、Nginx 伪静态配置

location / {
try_files $uri $uri/ /index.php?$query_string;

# 伪静态规则
rewrite ^/install/([a-zA-Z0-9]+)$ /install.php?suffix=$1 last;
rewrite ^/d/([a-zA-Z0-9]+)$ /download.php?suffix=$1 last;
}


## 四、使用说明
1. 访问 `https://plist.jiege6.cn`
2. 填写 IPA 文件地址、应用名称、Bundle ID 和自定义短链
3. 点击生成按钮获取安装链接
4. 在 iOS 设备上访问生成的链接即可安装应用

## 五、注意事项
1. IPA 文件必须是企业签名或开发者签名的
2. IPA 文件需要放在可以直接下载的地址
3. 服务器必须支持 HTTPS
4. 建议定期清理过期的安装记录
5. 确保 urls.db 文件有正确的读写权限


