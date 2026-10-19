<?php
/**
 * 未安装提示页
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装 - NexusForum</title>
<link rel="stylesheet" href="<?= h(nf_url('assets/style.css')) ?>">
</head>
<body>
<div class="container">
    <div class="auth-box">
        <h2>NexusForum 尚未安装</h2>
        <p>请先运行安装程序完成初始化配置。</p>
        <p><a class="btn btn-primary" href="<?= h(nf_url('install.php')) ?>">开始安装</a></p>
    </div>
</div>
</body>
</html>
