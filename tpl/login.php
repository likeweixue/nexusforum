<?php
/**
 * 登录页
 *
 * @var string $redirect 登录后回跳地址
 */
/** @var string $redirect */
?>
<div class="auth-box">
    <h2>登录</h2>
    <form method="post" action="<?= h(nf_url('index.php?action=login')) ?>">
        <?= nf_csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
        <div class="form-row">
            <label>用户名</label>
            <input type="text" name="username" required autofocus>
        </div>
        <div class="form-row">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">登录</button>
        </div>
    </form>
    <p class="auth-alt">还没有账号？<a href="<?= h(nf_url('index.php?action=register')) ?>">注册（需要邀请码）</a></p>
</div>
