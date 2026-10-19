<?php
/**
 * 错误页
 *
 * @var string $message 错误信息
 */
/** @var string $message */
?>
<div class="error-box">
    <h2>出错了</h2>
    <p><?= h($message) ?></p>
    <p><a href="<?= h(nf_url()) ?>">返回论坛首页</a></p>
</div>
