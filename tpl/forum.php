<?php
/**
 * 板块页：主题列表
 *
 * @var array       $forum   板块信息
 * @var array       $topics  主题列表
 * @var int         $page    当前页
 * @var int         $pages   总页数
 * @var int         $total   主题总数
 * @var array|null  $user    当前用户
 */
/** @var array $forum */
/** @var array $topics */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
$currentUser = NF_User::current();
$canPost = $currentUser !== null && (int) $currentUser['class'] >= (int) ($forum['minclasscreate'] ?? 1);
?>
<div class="breadcrumb">
    <a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo;
    <span><?= h($forum['name']) ?></span>
</div>

<div class="toolbar">
    <div class="toolbar-left">
        <h2><?= h($forum['name']) ?></h2>
        <?php if (!empty($forum['description'])): ?>
            <p class="forum-desc"><?= h($forum['description']) ?></p>
        <?php endif; ?>
    </div>
    <div class="toolbar-right">
        <?php if ($canPost): ?>
            <a class="btn btn-primary" href="<?= h(nf_url('index.php?action=newtopic&fid=' . (int) $forum['id'])) ?>">发表新主题</a>
        <?php elseif ($currentUser === null): ?>
            <a class="btn" href="<?= h(nf_url('index.php?action=login')) ?>">登录后发帖</a>
        <?php else: ?>
            <span class="muted">等级不足，无法发帖</span>
        <?php endif; ?>
    </div>
</div>

<table class="topic-table">
    <thead>
        <tr>
            <th class="col-state">状态</th>
            <th class="col-subject">主题</th>
            <th class="col-author">作者</th>
            <th class="col-count">回复</th>
            <th class="col-count">查看</th>
            <th class="col-last">最后发表</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($topics)): ?>
        <tr><td colspan="6" class="empty">暂无主题</td></tr>
    <?php else: ?>
        <?php foreach ($topics as $topic): ?>
            <?php
            $author = NF_User::get((int) $topic['userid']);
            $posts = $topic['posts'] ?? [];
            $lastPost = $posts ? end($posts) : null;
            $lastUser = $lastPost !== null ? NF_User::get((int) $lastPost['userid']) : null;
            $replyCount = max(0, count($posts) - 1);
            ?>
            <tr class="<?= ($topic['sticky'] ?? 'no') === 'yes' ? 'sticky' : '' ?>">
                <td class="col-state">
                    <?php if (($topic['locked'] ?? 'no') === 'yes'): ?>
                        <span class="topic-icon locked" title="已锁定">🔒</span>
                    <?php elseif (($topic['sticky'] ?? 'no') === 'yes'): ?>
                        <span class="topic-icon sticky" title="置顶">📌</span>
                    <?php else: ?>
                        <span class="topic-icon" title="普通">💬</span>
                    <?php endif; ?>
                </td>
                <td class="col-subject">
                    <a class="topic-subject" href="<?= h(nf_url('index.php?action=topic&tid=' . (int) $topic['id'])) ?>"><?= h($topic['subject']) ?></a>
                    <?php if (($topic['sticky'] ?? 'no') === 'yes'): ?>
                        <span class="badge badge-sticky">置顶</span>
                    <?php endif; ?>
                    <?php if (($topic['locked'] ?? 'no') === 'yes'): ?>
                        <span class="badge badge-locked">锁定</span>
                    <?php endif; ?>
                </td>
                <td class="col-author">
                    <?= $author !== null ? h($author['username']) : '未知' ?>
                    <div class="muted small"><?= h(nf_time_ago($topic['added'] ?? null)) ?></div>
                </td>
                <td class="col-count"><?= nf_number($replyCount) ?></td>
                <td class="col-count"><?= nf_number((int) ($topic['views'] ?? 0)) ?></td>
                <td class="col-last">
                    <?php if ($lastPost !== null): ?>
                        <?= $lastUser !== null ? h($lastUser['username']) : '未知' ?>
                        <div class="muted small"><?= h(nf_time_ago($lastPost['added'] ?? null)) ?></div>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?= nf_pagination($total, $page, max(5, (int) nf_config_get('topics_per_page', 20)), nf_url('index.php?action=forum&fid=' . (int) $forum['id'] . '&page={page}')) ?>
