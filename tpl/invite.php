<?php
/**
 * 邀请管理页：我的邀请 + 生成邀请
 *
 * @var array       $invites   邀请列表
 * @var array       $user      当前用户
 * @var int         $quota     剩余邀请名额
 * @var bool        $canInvite 是否可生成邀请
 */
/** @var array $invites */
/** @var array $user */
/** @var int $quota */
/** @var bool $canInvite */
?>
<div class="breadcrumb"><a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo; <span>我的邀请</span></div>

<div class="toolbar">
    <div class="toolbar-left">
        <h2>我的邀请</h2>
        <p class="muted">
            当前等级：<span class="user-class class-<?= (int) $user['class'] ?>"><?= h(NF_User::className((int) $user['class'])) ?></span>
            · 剩余邀请名额：<b><?= $quota ?></b>
        </p>
    </div>
</div>

<?php if ($canInvite): ?>
    <div class="invite-generate">
        <h3>生成邀请码</h3>
        <form method="post" action="<?= h(nf_url('index.php?action=invite')) ?>" class="inline-form">
            <?= nf_csrf_field() ?>
            <input type="hidden" name="op" value="generate">
            <div class="form-row">
                <label>预绑定受邀人（可选）</label>
                <input type="text" name="invitee" placeholder="受邀人邮箱或用户名，留空则不限制">
            </div>
            <div class="form-row">
                <label>有效期</label>
                <select name="expire_days">
                    <option value="1">1 天</option>
                    <option value="7" selected>7 天</option>
                    <option value="30">30 天</option>
                    <option value="0">永久</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">生成邀请码</button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="notice">
        <?php if ((int) $user['class'] < (int) nf_config_get('invite_min_class', 2)): ?>
            达到 <b><?= h(NF_User::className((int) nf_config_get('invite_min_class', 2))) ?></b> 等级后即可获得邀请权限。
        <?php else: ?>
            你的邀请名额已用完。
        <?php endif; ?>
    </div>
<?php endif; ?>

<h3>我发出的邀请</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>邀请码</th>
            <th>受邀人</th>
            <th>生成时间</th>
            <th>有效期至</th>
            <th>状态</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($invites)): ?>
        <tr><td colspan="5" class="empty">暂无邀请记录</td></tr>
    <?php else: ?>
        <?php foreach ($invites as $invite): ?>
            <tr>
                <td><code class="invite-code"><?= h($invite['hash']) ?></code></td>
                <td><?= !empty($invite['invitee']) ? h($invite['invitee']) : '<span class="muted">不限</span>' ?></td>
                <td><?= h(nf_time($invite['time_invited'] ?? null)) ?></td>
                <td><?= !empty($invite['expired_at']) ? h(nf_time($invite['expired_at'])) : '永久' ?></td>
                <td>
                    <?php $status = NF_Invite::statusText($invite); ?>
                    <span class="badge badge-<?= $status === '有效' ? 'ok' : 'muted' ?>"><?= h($status) ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
