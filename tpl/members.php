<?php
/**
 * 会员列表页
 *
 * @var array $users 用户列表
 */
/** @var array $users */
?>
<div class="breadcrumb"><a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo; <span>会员列表</span></div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>用户名</th>
            <th>等级</th>
            <th>注册时间</th>
            <th>最后登录</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($users)): ?>
        <tr><td colspan="5" class="empty">暂无会员</td></tr>
    <?php else: ?>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int) $u['id'] ?></td>
                <td><a href="<?= h(nf_url('index.php?action=user&uid=' . (int) $u['id'])) ?>"><?= h($u['username']) ?></a></td>
                <td><span class="user-class class-<?= (int) $u['class'] ?>"><?= h(NF_User::className((int) $u['class'])) ?></span></td>
                <td><?= h(nf_time($u['added'] ?? null)) ?></td>
                <td><?= h(nf_time_ago($u['last_login'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
