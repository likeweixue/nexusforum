<?php
/**
 * 最新主题页
 *
 * @var array $topics 主题列表
 */
/** @var array $topics */
?>
<div class="breadcrumb"><a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo; <span>最新主题</span></div>

<table class="topic-table">
    <thead>
        <tr>
            <th class="col-subject">主题</th>
            <th class="col-author">作者</th>
            <th class="col-count">回复</th>
            <th class="col-last">发表时间</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($topics)): ?>
        <tr><td colspan="4" class="empty">暂无主题</td></tr>
    <?php else: ?>
        <?php foreach ($topics as $topic): ?>
            <?php
            $author = NF_User::get((int) $topic['userid']);
            $forum = NF_Forum::getForum((int) $topic['forumid']);
            $replyCount = max(0, count($topic['posts'] ?? []) - 1);
            ?>
            <tr>
                <td class="col-subject">
                    <a class="topic-subject" href="<?= h(nf_url('index.php?action=topic&tid=' . (int) $topic['id'])) ?>"><?= h($topic['subject']) ?></a>
                    <div class="muted small"><?= $forum !== null ? h($forum['name']) : '' ?></div>
                </td>
                <td class="col-author"><?= $author !== null ? h($author['username']) : '未知' ?></td>
                <td class="col-count"><?= nf_number($replyCount) ?></td>
                <td class="col-last"><?= h(nf_time_ago($topic['added'] ?? null)) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
