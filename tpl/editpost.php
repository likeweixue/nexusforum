<?php
/**
 * 编辑楼层页
 *
 * @var array $topic 主题
 * @var array $post  楼层元数据
 * @var string $body 楼层当前内容
 */
/** @var array $topic */
/** @var array $post */
/** @var string $body */
?>
<div class="breadcrumb">
    <a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo;
    <a href="<?= h(nf_url('index.php?action=topic&tid=' . (int) $topic['id'])) ?>"><?= h($topic['subject']) ?></a> &raquo;
    <span>编辑楼层</span>
</div>

<div class="form-box">
    <h2>编辑楼层 #<?= (int) $post['id'] ?></h2>
    <form method="post" action="<?= h(nf_url('index.php?action=editpost')) ?>">
        <?= nf_csrf_field() ?>
        <input type="hidden" name="tid" value="<?= (int) $topic['id'] ?>">
        <input type="hidden" name="pid" value="<?= (int) $post['id'] ?>">
        <div class="form-row">
            <label>内容 <span class="required">*</span></label>
            <textarea name="body" rows="12" required><?= h($body) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存修改</button>
            <a class="btn" href="<?= h(nf_url('index.php?action=topic&tid=' . (int) $topic['id'])) ?>">取消</a>
        </div>
    </form>
</div>
