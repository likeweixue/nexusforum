<?php
/**
 * 主题页：楼层列表 + 回复框
 *
 * @var array       $topic    主题信息
 * @var array       $forum    所属板块
 * @var array       $posts    当前页楼层
 * @var int         $page     当前页
 * @var int         $pages    总页数
 * @var int         $total    楼层总数
 * @var array|null  $user     当前用户
 */
/** @var array $topic */
/** @var array $forum */
/** @var array $posts */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
$currentUser = NF_User::current();
$canReply = $currentUser !== null
    && ($topic['locked'] ?? 'no') !== 'yes'
    && (int) $currentUser['class'] >= (int) ($forum['minclasswrite'] ?? 1);
$floorBase = ($page - 1) * max(5, (int) nf_config_get('posts_per_page', 10));
?>
<div class="breadcrumb">
    <a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo;
    <a href="<?= h(nf_url('index.php?action=forum&fid=' . (int) $forum['id'])) ?>"><?= h($forum['name']) ?></a> &raquo;
    <span><?= h($topic['subject']) ?></span>
</div>

<div class="toolbar">
    <div class="toolbar-left">
        <h2>
            <?= h($topic['subject']) ?>
            <?php if (($topic['sticky'] ?? 'no') === 'yes'): ?><span class="badge badge-sticky">置顶</span><?php endif; ?>
            <?php if (($topic['locked'] ?? 'no') === 'yes'): ?><span class="badge badge-locked">锁定</span><?php endif; ?>
        </h2>
        <p class="muted small">
            查看 <?= nf_number((int) ($topic['views'] ?? 0)) ?> 次 · 回复 <?= nf_number(max(0, $total - 1)) ?> 次
        </p>
    </div>
    <div class="toolbar-right">
        <?php if (NF_User::isStaff($currentUser)): ?>
            <div class="mod-actions">
                <a class="btn btn-small" href="<?= h(nf_url('index.php?action=mod_topic&tid=' . (int) $topic['id'] . '&op=sticky&csrf_token=' . nf_csrf_token())) ?>">
                    <?= ($topic['sticky'] ?? 'no') === 'yes' ? '取消置顶' : '置顶' ?>
                </a>
                <a class="btn btn-small" href="<?= h(nf_url('index.php?action=mod_topic&tid=' . (int) $topic['id'] . '&op=lock&csrf_token=' . nf_csrf_token())) ?>">
                    <?= ($topic['locked'] ?? 'no') === 'yes' ? '解除锁定' : '锁定' ?>
                </a>
                <a class="btn btn-small btn-danger" href="<?= h(nf_url('index.php?action=mod_topic&tid=' . (int) $topic['id'] . '&op=delete&csrf_token=' . nf_csrf_token())) ?>" onclick="return confirm('确定删除该主题及其所有回复？')">删除</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($posts as $index => $post): ?>
    <?php
    $postUser = NF_User::get((int) $post['userid']);
    $floor = $floorBase + $index + 1;
    $body = NF_Forum::postBody((int) $topic['id'], (int) $post['id']);
    $canEdit = $currentUser !== null
        && ((int) $currentUser['id'] === (int) $post['userid'] || NF_User::isStaff($currentUser));
    ?>
    <div class="post" id="post-<?= (int) $post['id'] ?>">
        <div class="post-user">
            <div class="post-username">
                <a href="<?= h(nf_url('index.php?action=user&uid=' . (int) $post['userid'])) ?>"><?= $postUser !== null ? h($postUser['username']) : '未知用户' ?></a>
            </div>
            <div class="post-class class-<?= $postUser !== null ? (int) $postUser['class'] : 0 ?>">
                <?= $postUser !== null ? h(NF_User::className((int) $postUser['class'])) : '-' ?>
            </div>
            <?php if ($postUser !== null && !empty($postUser['signature'])): ?>
                <div class="post-signature"><?= h($postUser['signature']) ?></div>
            <?php endif; ?>
        </div>
        <div class="post-body">
            <div class="post-meta">
                <span class="floor">#<?= $floor ?></span>
                <span class="muted small"><?= h(nf_time($post['added'] ?? null)) ?></span>
                <?php if (!empty($post['editedby'])): ?>
                    <?php $editor = NF_User::get((int) $post['editedby']); ?>
                    <span class="muted small">
                        （<?= $editor !== null ? h($editor['username']) : '版主' ?> 编辑于 <?= h(nf_time($post['editdate'] ?? null)) ?>）
                    </span>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                    <a class="small" href="<?= h(nf_url('index.php?action=editpost&tid=' . (int) $topic['id'] . '&pid=' . (int) $post['id'])) ?>">编辑</a>
                <?php endif; ?>
            </div>
            <div class="post-content">
                <?= NF_Bbcode::parse($body) ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= nf_pagination($total, $page, max(5, (int) nf_config_get('posts_per_page', 10)), nf_url('index.php?action=topic&tid=' . (int) $topic['id'] . '&page={page}')) ?>

<?php if ($canReply): ?>
    <div class="reply-box">
        <h3>发表回复</h3>
        <form method="post" action="<?= h(nf_url('index.php?action=reply')) ?>">
            <?= nf_csrf_field() ?>
            <input type="hidden" name="tid" value="<?= (int) $topic['id'] ?>">
            <div class="bbcode-bar">
                <span class="muted small">支持 BBCode：[b] [i] [u] [s] [url=链接] [img] [quote] [code] [color=red] [size=14] [list]</span>
            </div>
            <textarea name="body" rows="8" required placeholder="请输入回复内容..."></textarea>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">提交回复</button>
            </div>
        </form>
    </div>
<?php elseif (($topic['locked'] ?? 'no') === 'yes'): ?>
    <div class="notice">该主题已锁定，无法回复。</div>
<?php elseif ($currentUser === null): ?>
    <div class="notice">请 <a href="<?= h(nf_url('index.php?action=login')) ?>">登录</a> 后回复。</div>
<?php else: ?>
    <div class="notice">你的等级不足以在该板块回复。</div>
<?php endif; ?>
