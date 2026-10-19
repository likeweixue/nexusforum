<?php
/**
 * 布局模板：所有页面的公共框架
 *
 * @var array|null $user     当前登录用户
 * @var string     $pageTitle 页面标题
 * @var string     $activeNav 当前导航标识
 */
/** @var array|null $user */
/** @var string $pageTitle */
$siteName = nf_config_get('site_name', 'NexusForum');
$currentUser = NF_User::current();
$stats = NF_Forum::siteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? '') ?> - <?= h($siteName) ?></title>
<link rel="stylesheet" href="<?= h(nf_url('assets/style.css')) ?>">
</head>
<body>
<div class="container">

<!-- ======================= 顶部 ======================= -->
<div class="header">
    <div class="header-inner">
        <div class="logo">
            <a href="<?= h(nf_url()) ?>"><?= h($siteName) ?></a>
            <span class="logo-sub">NexusForum</span>
        </div>
        <div class="userpanel">
            <?php if ($currentUser !== null): ?>
                <span class="user-class class-<?= (int) $currentUser['class'] ?>"><?= h(NF_User::className((int) $currentUser['class'])) ?></span>
                <a href="<?= h(nf_url('index.php?action=user&uid=' . (int) $currentUser['id'])) ?>"><?= h($currentUser['username']) ?></a>
                <a href="<?= h(nf_url('index.php?action=invite')) ?>">邀请</a>
                <?php if (NF_User::isAdmin($currentUser)): ?>
                    <a href="<?= h(nf_url('index.php?action=admin')) ?>">管理面板</a>
                <?php endif; ?>
                <a href="<?= h(nf_url('index.php?action=logout&csrf_token=' . nf_csrf_token())) ?>">退出</a>
            <?php else: ?>
                <a href="<?= h(nf_url('index.php?action=login')) ?>">登录</a>
                <a href="<?= h(nf_url('index.php?action=register')) ?>">注册（需邀请码）</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================= 导航 ======================= -->
<div class="navbar">
    <a href="<?= h(nf_url()) ?>" class="<?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>">论坛首页</a>
    <a href="<?= h(nf_url('index.php?action=latest')) ?>" class="<?= ($activeNav ?? '') === 'latest' ? 'active' : '' ?>">最新主题</a>
    <a href="<?= h(nf_url('index.php?action=members')) ?>" class="<?= ($activeNav ?? '') === 'members' ? 'active' : '' ?>">会员列表</a>
    <span class="navbar-stats">
        主题: <?= nf_number((int) $stats['topics']) ?> | 帖子: <?= nf_number((int) $stats['posts']) ?> | 会员: <?= nf_number((int) $stats['members']) ?>
    </span>
</div>

<?= nf_flash_render() ?>

<!-- ======================= 主体 ======================= -->
<div class="main">
