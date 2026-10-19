<?php
/**
 * 注册页（邀请制）
 *
 * @var string $prefillHash 预填邀请码
 */
/** @var string $prefillHash */
?>
<div class="auth-box">
    <h2>注册新账号</h2>
    <p class="notice">本站实行邀请注册制，你需要一枚有效的邀请码。</p>
    <form method="post" action="<?= h(nf_url('index.php?action=register')) ?>">
        <?= nf_csrf_field() ?>
        <div class="form-row">
            <label>邀请码 <span class="required">*</span></label>
            <input type="text" name="invite_hash" required value="<?= h($prefillHash) ?>" placeholder="请输入邀请码" style="text-transform:uppercase">
        </div>
        <div class="form-row">
            <label>用户名 <span class="required">*</span></label>
            <input type="text" name="username" required placeholder="2-20 个中英文/数字/下划线">
        </div>
        <div class="form-row">
            <label>密码 <span class="required">*</span></label>
            <input type="password" name="password" required placeholder="至少 6 位">
        </div>
        <div class="form-row">
            <label>确认密码 <span class="required">*</span></label>
            <input type="password" name="password_confirm" required>
        </div>
        <div class="form-row">
            <label>邮箱 <span class="required">*</span></label>
            <input type="email" name="email" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">注册</button>
        </div>
    </form>
    <p class="auth-alt">已有账号？<a href="<?= h(nf_url('index.php?action=login')) ?>">直接登录</a></p>
</div>
