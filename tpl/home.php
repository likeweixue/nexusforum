<?php
/**
 * 论坛首页：分类 -> 板块列表
 *
 * @var array $categories  NF_Forum::indexData() 的结果
 */
/** @var array $categories */
$currentUser = NF_User::current();
?>
<div class="forum-index">
<?php foreach ($categories as $cat): ?>
    <div class="category">
        <div class="category-header">
            <span class="category-name"><?= h($cat['name']) ?></span>
            <?php if (!empty($cat['description'])): ?>
                <span class="category-desc"><?= h($cat['description']) ?></span>
            <?php endif; ?>
        </div>
        <table class="forum-table">
            <thead>
                <tr>
                    <th class="col-forum">板块</th>
                    <th class="col-count">主题</th>
                    <th class="col-count">帖子</th>
                    <th class="col-last">最后发表</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cat['forums'] as $forum): ?>
                <?php
                $lastTopic = $forum['last_topic'] ?? null;
                $lastPost = $forum['last_post'] ?? null;
                $lastUser = $lastPost !== null ? NF_User::get((int) $lastPost['userid']) : null;
                ?>
                <tr>
                    <td class="col-forum">
                        <a class="forum-name" href="<?= h(nf_url('index.php?action=forum&fid=' . (int) $forum['id'])) ?>"><?= h($forum['name']) ?></a>
                        <?php if (!empty($forum['description'])): ?>
                            <div class="forum-desc"><?= h($forum['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="col-count"><?= nf_number((int) ($forum['topiccount'] ?? 0)) ?></td>
                    <td class="col-count"><?= nf_number((int) ($forum['postcount'] ?? 0)) ?></td>
                    <td class="col-last">
                        <?php if ($lastTopic !== null): ?>
                            <a href="<?= h(nf_url('index.php?action=topic&tid=' . (int) $lastTopic['id'])) ?>"><?= h(nf_truncate($lastTopic['subject'], 30)) ?></a>
                            <div class="last-meta">
                                by <?= $lastUser !== null ? h($lastUser['username']) : '未知' ?>
                                · <?= h(nf_time_ago($lastPost['added'] ?? null)) ?>
                            </div>
                        <?php else: ?>
                            <span class="muted">暂无主题</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
</div>

<div class="side-box">
    <h3>论坛统计</h3>
    <ul class="stat-list">
        <li>我们的会员已经发表了 <b><?= nf_number((int) $GLOBALS['__nf_stats']['posts']) ?></b> 篇帖子</li>
        <li>主题数：<b><?= nf_number((int) $GLOBALS['__nf_stats']['topics']) ?></b></li>
        <li>会员数：<b><?= nf_number((int) $GLOBALS['__nf_stats']['members']) ?></b></li>
    </ul>
</div>
