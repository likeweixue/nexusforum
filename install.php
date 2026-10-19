<?php
/**
 * NexusForum - 安装程序（DokuWiki 风格）
 *
 * 使用方法：
 *   1. 将整个目录上传到网站根目录
 *   2. 浏览器访问 install.php
 *   3. 按提示填写站点信息和管理员账号
 *   4. 安装完成后请删除本文件
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/util.php';
require __DIR__ . '/inc/storage.php';
require __DIR__ . '/inc/user.php';
require __DIR__ . '/inc/invite.php';
require __DIR__ . '/inc/forum.php';

// ---------------------------------------------------------------
// 已安装则锁定
// ---------------------------------------------------------------
if (is_file(NF_CONF . '/config.php')) {
    $existing = require NF_CONF . '/config.php';
    if (is_array($existing) && !empty($existing['installed'])) {
        nf_install_locked();
        exit;
    }
}

$step = $_GET['step'] ?? 'check';
$errors = [];

// ---------------------------------------------------------------
// 步骤处理
// ---------------------------------------------------------------

// 第一步：环境检查
if ($step === 'check') {
    $checks = [
        ['name' => 'PHP 版本 >= 8.0', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'value' => PHP_VERSION],
        ['name' => 'JSON 扩展', 'ok' => extension_loaded('json'), 'value' => extension_loaded('json') ? '已加载' : '缺失'],
        ['name' => 'MBString 扩展', 'ok' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? '已加载' : '缺失'],
        ['name' => 'conf/ 目录可写', 'ok' => is_writable(NF_CONF), 'value' => is_writable(NF_CONF) ? '可写' : '不可写'],
        ['name' => 'data/ 目录可写', 'ok' => is_writable(NF_DATA), 'value' => is_writable(NF_DATA) ? '可写' : '不可写'],
        ['name' => 'Session 可用', 'ok' => function_exists('session_start'), 'value' => function_exists('session_start') ? '可用' : '不可用'],
    ];
    $allOk = true;
    foreach ($checks as $c) {
        if (!$c['ok']) {
            $allOk = false;
        }
    }
    nf_install_page('环境检查', function () use ($checks, $allOk): void {
        ?>
        <table class="check-table">
            <?php foreach ($checks as $c): ?>
                <tr>
                    <td><?= h($c['name']) ?></td>
                    <td><?= h((string) $c['value']) ?></td>
                    <td class="<?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? '✓ 通过' : '✗ 未通过' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php if ($allOk): ?>
            <p class="ok-text">所有检查通过，可以继续安装。</p>
            <a class="btn btn-primary" href="<?= h(nf_url('install.php?step=form')) ?>">下一步：填写站点信息</a>
        <?php else: ?>
            <p class="fail-text">存在未通过的检查项，请先解决后再安装。</p>
        <?php endif; ?>
        <?php
    });
    exit;
}

// 第二步：填写信息
if ($step === 'form') {
    nf_install_page('站点设置', function (): void {
        ?>
        <form method="post" action="<?= h(nf_url('install.php?step=install')) ?>">
            <div class="form-row">
                <label>站点名称 <span class="required">*</span></label>
                <input type="text" name="site_name" value="NexusForum" required>
            </div>
            <div class="form-row">
                <label>站点描述</label>
                <input type="text" name="site_desc" value="一个简洁的 PT 风格论坛">
            </div>
            <div class="form-row">
                <label>管理员用户名 <span class="required">*</span></label>
                <input type="text" name="admin_username" value="admin" required>
            </div>
            <div class="form-row">
                <label>管理员密码 <span class="required">*</span></label>
                <input type="password" name="admin_password" required placeholder="至少 6 位">
            </div>
            <div class="form-row">
                <label>确认密码 <span class="required">*</span></label>
                <input type="password" name="admin_password_confirm" required>
            </div>
            <div class="form-row">
                <label>管理员邮箱 <span class="required">*</span></label>
                <input type="email" name="admin_email" required>
            </div>
            <div class="form-row">
                <label>注册方式</label>
                <select name="invite_required">
                    <option value="1" selected>邀请注册制（PT 传统，推荐）</option>
                    <option value="0">开放注册</option>
                </select>
            </div>
            <div class="form-row">
                <label>时区</label>
                <select name="timezone">
                    <option value="Asia/Shanghai" selected>Asia/Shanghai（中国标准时间）</option>
                    <option value="Asia/Tokyo">Asia/Tokyo（日本）</option>
                    <option value="UTC">UTC</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">开始安装</button>
            </div>
        </form>
        <?php
    });
    exit;
}

// 第三步：执行安装
if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    $siteDesc = trim((string) ($_POST['site_desc'] ?? ''));
    $adminUsername = trim((string) ($_POST['admin_username'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $inviteRequired = !empty($_POST['invite_required']);
    $timezone = (string) ($_POST['timezone'] ?? 'Asia/Shanghai');

    // 校验
    if ($siteName === '') {
        $errors[] = '站点名称不能为空';
    }
    if ($adminUsername === '' || mb_strlen($adminUsername) < 2 || mb_strlen($adminUsername) > 20) {
        $errors[] = '管理员用户名长度需为 2-20 个字符';
    }
    if (strlen($adminPassword) < 6) {
        $errors[] = '管理员密码长度至少 6 位';
    }
    if ($adminPassword !== $adminPasswordConfirm) {
        $errors[] = '两次输入的密码不一致';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '管理员邮箱格式不正确';
    }

    if (empty($errors)) {
        // 1. 初始化数据目录
        NF_Storage::initDataDir();

        // 2. 创建管理员
        $result = NF_User::createAdmin($adminUsername, $adminPassword, $adminEmail);
        if (!$result['ok']) {
            $errors[] = '管理员创建失败：' . ($result['error'] ?? '未知错误');
        }
    }

    if (empty($errors)) {
        // 3. 写入配置
        $config = [
            'site_name' => $siteName,
            'site_desc' => $siteDesc,
            'base_url' => '',
            'timezone' => $timezone,
            'admin_email' => $adminEmail,
            'invite_required' => $inviteRequired,
            'invite_min_class' => 2,
            'invite_expire_days' => 7,
            'topics_per_page' => 20,
            'posts_per_page' => 10,
            'installed' => true,
            'install_time' => date('Y-m-d H:i:s'),
        ];
        if (!nf_save_config($config)) {
            $errors[] = '配置文件写入失败，请检查 conf/ 目录权限';
        }
    }

    if (empty($errors)) {
        // 4. 创建默认板块结构（NexusPHP 风格）
        $cat1 = NF_Forum::createOverforum('站务区', '站点公告与事务', 0);
        $cat2 = NF_Forum::createOverforum('讨论区', '自由交流', 1);
        if ($cat1['ok']) {
            NF_Forum::createForum('论坛公告', '站点重要公告与规则', (int) $cat1['forum']['id'], 0, 0, 100, 200);
            NF_Forum::createForum('建议反馈', '对站点的建议与问题反馈', (int) $cat1['forum']['id'], 1);
        }
        if ($cat2['ok']) {
            NF_Forum::createForum('新手报到', '新会员自我介绍', (int) $cat2['forum']['id'], 0);
            NF_Forum::createForum('闲聊灌水', '轻松话题随意聊', (int) $cat2['forum']['id'], 1);
        }

        // 5. 管理员赠送 99 个邀请名额（已在 createAdmin 中设置）
        nf_install_page('安装完成', function () use ($adminUsername): void {
            ?>
            <div class="install-success">
                <h2>🎉 安装成功！</h2>
                <p>NexusForum 已经安装完成，管理员账号：<b><?= h($adminUsername) ?></b></p>
                <p class="warn-text">安全提醒：请立即删除 <code>install.php</code> 文件，防止重复安装。</p>
                <div class="form-actions">
                    <a class="btn btn-primary" href="<?= h(nf_url()) ?>">进入论坛</a>
                </div>
            </div>
            <?php
        });
        exit;
    }
}

// 出错处理
nf_install_page('安装出错', function () use ($errors): void {
    ?>
    <div class="install-error">
        <h2>安装未能完成</h2>
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <a class="btn" href="<?= h(nf_url('install.php?step=form')) ?>">返回重试</a>
    </div>
    <?php
});

// ===============================================================
// 辅助函数
// ===============================================================

/**
 * 安装页框架
 */
function nf_install_page(string $title, callable $content): void
{
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> - NexusForum 安装程序</title>
    <link rel="stylesheet" href="<?= h(nf_url('assets/style.css')) ?>">
    <style>
        .install-wrap { max-width: 640px; margin: 40px auto; padding: 0 16px; }
        .install-box { border: 1px solid #d5dde8; background: #fff; padding: 28px; }
        .install-box h1 { color: #2c5282; font-size: 20px; margin-bottom: 6px; }
        .install-box .subtitle { color: #888; font-size: 13px; margin-bottom: 20px; }
        .check-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .check-table td { border: 1px solid #e3e3e3; padding: 7px 12px; font-size: 13px; }
        .check-table .ok { color: #5cb85c; font-weight: bold; }
        .check-table .fail { color: #d9534f; font-weight: bold; }
        .ok-text { color: #3c763d; margin-bottom: 14px; }
        .fail-text { color: #a94442; margin-bottom: 14px; }
        .warn-text { color: #8a6d3b; background: #fcf8e3; border: 1px solid #faebcc; padding: 8px 12px; border-radius: 3px; margin: 12px 0; }
        .install-success h2 { color: #3c763d; margin-bottom: 12px; }
        .install-error h2 { color: #a94442; margin-bottom: 12px; }
        .install-error ul { margin: 0 0 14px 20px; color: #a94442; }
        .install-steps { display: flex; gap: 6px; margin-bottom: 20px; font-size: 12px; color: #999; }
        .install-steps .done { color: #5cb85c; }
        .install-steps .current { color: #2c5282; font-weight: bold; }
    </style>
    </head>
    <body style="background:#e8e8e8">
    <div class="install-wrap">
        <div class="install-box">
            <h1>NexusForum 安装程序</h1>
            <p class="subtitle">基于 NexusPHP 论坛模块 · 零依赖 · 纯文件存储 · GPL-2.0</p>
            <div class="install-steps">
                <span class="<?= $title === '环境检查' ? 'current' : 'done' ?>">1. 环境检查</span> &raquo;
                <span class="<?= in_array($title, ['站点设置'], true) ? 'current' : ($title === '环境检查' ? '' : 'done') ?>">2. 站点设置</span> &raquo;
                <span class="<?= in_array($title, ['安装完成', '安装出错'], true) ? 'current' : '' ?>">3. 完成</span>
            </div>
            <h2 style="border:none;padding:0;margin-bottom:14px"><?= h($title) ?></h2>
            <?php $content(); ?>
        </div>
        <p style="text-align:center;color:#999;font-size:12px;margin-top:14px">NexusForum v<?= NF_VERSION ?> · Powered by NexusPHP (GPL-2.0)</p>
    </div>
    </body>
    </html>
    <?php
}

/**
 * 已安装锁定页
 */
function nf_install_locked(): void
{
    nf_install_page('已安装', function (): void {
        ?>
        <p>NexusForum 已经安装。</p>
        <p class="warn-text">为防止重复安装，请删除 <code>install.php</code> 文件。</p>
        <a class="btn btn-primary" href="<?= h(nf_url()) ?>">进入论坛</a>
        <?php
    });
}
