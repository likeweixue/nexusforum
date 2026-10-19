<?php
/**
 * 用户资料页
 *
 * @var array $profile 被查看的用户
 * @var array $stats   该用户的主题/回复统计
 * @var array|null $currentUser 当前登录用户
 */
/** @var array $profile */
/** @var array $stats */
$currentUser = NF_User::current();
$inviter = !empty($profile['invited_by']) ? NF_User::get((int) $profile['invited_by']) : null;
?>
<div class="breadcrumb"><a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo; <span>用户资料</span></div>

<div class="profile-box">
    <div class="profile-header">
        <h2><?= h($profile['username']) ?></h2>
        <span class="user-class class-<?= (int) $profile['class'] ?>"><?= h(NF_User::className((int) $profile['class'])) ?></span>
    </div>
    <table class="profile-table">
        <tr><th>用户 ID</th><td><?= (int) $profile['id'] ?></td></tr>
        <tr><th>注册时间</th><td><?= h(nf_time($profile['added'] ?? null)) ?></td></tr>
        <tr><th>最后登录</th><td><?= h(nf_time($profile['last_login'] ?? null)) ?></td></tr>
        <tr><th>主题数</th><td><?= nf_number((int) $stats['topics']) ?></td></tr>
        <tr><th>回复数</th><td><?= nf_number((int) $stats['posts']) ?></td></tr>
        <tr><th>邀请人</th><td><?= $inviter !== null ? h($inviter['username']) : '系统' ?></td></tr>
        <?php if (!empty($profile['signature'])): ?>
            <tr><th>签名</th><td><?= h($profile['signature']) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if ($currentUser !== null && (int) $currentUser['id'] === (int) $profile['id']): ?>
        <div class="profile-edit">
            <h3>修改个人资料</h3>
            <form method="post" action="<?= h(nf_url('index.php?action=user')) ?>">
                <?= nf_csrf_field() ?>
                <input type="hidden" name="op" value="profile">
                <div class="form-row">
                    <label>个性签名</label>
                    <input type="text" name="signature" maxlength="100" value="<?= h($profile['signature'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>修改密码（留空则不修改）</label>
                    <input type="password" name="new_password" placeholder="至少 6 位">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>
