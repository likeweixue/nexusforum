<?php
/**
 * 发表新主题页
 *
 * @var array $forum 板块信息
 */
/** @var array $forum */
?>
<div class="breadcrumb">
    <a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo;
    <a href="<?= h(nf_url('index.php?action=forum&fid=' . (int) $forum['id'])) ?>"><?= h($forum['name']) ?></a> &raquo;
    <span>发表新主题</span>
</div>

<div class="form-box">
    <h2>发表新主题</h2>
    <form method="post" action="<?= h(nf_url('index.php?action=newtopic')) ?>">
        <?= nf_csrf_field() ?>
        <input type="hidden" name="fid" value="<?= (int) $forum['id'] ?>">
        <div class="form-row">
            <label>标题 <span class="required">*</span></label>
            <input type="text" name="subject" required maxlength="128" placeholder="请输入主题标题">
        </div>
        <div class="form-row">
            <label>内容 <span class="required">*</span></label>
            <div class="bbcode-bar">
                <span class="muted small">支持 BBCode：[b] [i] [u] [s] [url=链接] [img] [quote] [code] [color=red] [size=14] [list]</span>
            </div>
            <textarea name="body" rows="12" required placeholder="请输入主题内容..."></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">发表主题</button>
            <a class="btn" href="<?= h(nf_url('index.php?action=forum&fid=' . (int) $forum['id'])) ?>">取消</a>
        </div>
    </form>
</div>
