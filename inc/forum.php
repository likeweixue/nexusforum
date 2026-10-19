<?php
/**
 * NexusForum - 论坛核心逻辑
 *
 * 数据模型参照 NexusPHP：
 *   overforums（分类） -> forums（板块） -> topics（主题） -> posts（楼层）
 *   主题置顶(sticky)/锁定(locked)、板块发帖等级限制(minclasscreate)等传统均保留。
 */

declare(strict_types=1);

class NF_Forum
{
    // ------------------------------------------------------------------
    // 分类（OverForum）
    // ------------------------------------------------------------------

    /**
     * @return array{ok:bool, error?:string, forum?:array}
     */
    public static function createOverforum(string $name, string $description = '', int $sort = 0): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => '分类名称不能为空'];
        }
        $id = NF_Storage::nextId('overforum');
        $of = [
            'id' => $id,
            'name' => $name,
            'description' => trim($description),
            'minclassview' => 0,
            'sort' => $sort,
        ];
        if (!NF_Storage::jsonWrite(NF_Storage::overforumPath($id), $of)) {
            return ['ok' => false, 'error' => '分类写入失败'];
        }
        return ['ok' => true, 'forum' => $of];
    }

    public static function listOverforums(): array
    {
        return NF_Storage::scanJson('overforums', null, function (array $a, array $b): int {
            return ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0));
        });
    }

    public static function getOverforum(int $id): ?array
    {
        return NF_Storage::jsonRead(NF_Storage::overforumPath($id));
    }

    public static function deleteOverforum(int $id): bool
    {
        // 先删除其下板块
        foreach (self::listForums() as $forum) {
            if ((int) ($forum['forid'] ?? 0) === $id) {
                self::deleteForum((int) $forum['id']);
            }
        }
        return NF_Storage::delete(NF_Storage::overforumPath($id));
    }

    // ------------------------------------------------------------------
    // 板块（Forum）
    // ------------------------------------------------------------------

    /**
     * @return array{ok:bool, error?:string, forum?:array}
     */
    public static function createForum(string $name, string $description, int $forid, int $sort = 0, int $minclassread = 0, int $minclasswrite = 1, int $minclasscreate = 1): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => '板块名称不能为空'];
        }
        $id = NF_Storage::nextId('forum');
        $forum = [
            'id' => $id,
            'forid' => $forid,
            'sort' => $sort,
            'name' => $name,
            'description' => trim($description),
            'minclassread' => $minclassread,
            'minclasswrite' => $minclasswrite,
            'minclasscreate' => $minclasscreate,
            'topiccount' => 0,
            'postcount' => 0,
        ];
        if (!NF_Storage::jsonWrite(NF_Storage::forumPath($id), $forum)) {
            return ['ok' => false, 'error' => '板块写入失败'];
        }
        return ['ok' => true, 'forum' => $forum];
    }

    public static function listForums(): array
    {
        return NF_Storage::scanJson('forums', null, function (array $a, array $b): int {
            $c = ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0));
            return $c !== 0 ? $c : ((int) $a['id']) <=> ((int) $b['id']);
        });
    }

    public static function getForum(int $id): ?array
    {
        return NF_Storage::jsonRead(NF_Storage::forumPath($id));
    }

    public static function updateForum(int $id, array $fields): bool
    {
        $forum = self::getForum($id);
        if ($forum === null) {
            return false;
        }
        return NF_Storage::jsonWrite(NF_Storage::forumPath($id), array_merge($forum, $fields));
    }

    public static function deleteForum(int $id): bool
    {
        // 删除板块下所有主题
        foreach (self::listTopicsByForum($id) as $topic) {
            self::deleteTopic((int) $topic['id']);
        }
        return NF_Storage::delete(NF_Storage::forumPath($id));
    }

    /**
     * 论坛首页数据：分类 -> 板块（含最后发表信息）
     */
    public static function indexData(): array
    {
        $overforums = self::listOverforums();
        $forums = self::listForums();
        $result = [];
        foreach ($overforums as $of) {
            $result[(int) $of['id']] = $of + ['forums' => []];
        }
        foreach ($forums as $forum) {
            $fid = (int) ($forum['forid'] ?? 0);
            if (!isset($result[$fid])) {
                continue;
            }
            // 最后发表
            $lastTopic = null;
            $lastPost = null;
            foreach (self::listTopicsByForum((int) $forum['id']) as $topic) {
                if ($lastTopic === null || (int) $topic['lastpost'] > (int) $lastTopic['lastpost']) {
                    $lastTopic = $topic;
                }
            }
            if ($lastTopic !== null) {
                $posts = $lastTopic['posts'] ?? [];
                $lastPost = $posts ? end($posts) : null;
            }
            $forum['last_topic'] = $lastTopic;
            $forum['last_post'] = $lastPost;
            $result[$fid]['forums'][] = $forum;
        }
        return array_values($result);
    }

    // ------------------------------------------------------------------
    // 主题（Topic）
    // ------------------------------------------------------------------

    /**
     * 发表新主题（首贴一起写入）
     *
     * @return array{ok:bool, error?:string, topic?:array}
     */
    public static function createTopic(int $forumId, int $userId, string $subject, string $body): array
    {
        $forum = self::getForum($forumId);
        if ($forum === null) {
            return ['ok' => false, 'error' => '板块不存在'];
        }
        $user = NF_User::get($userId);
        if ($user === null) {
            return ['ok' => false, 'error' => '用户不存在'];
        }
        if ((int) $user['class'] < (int) ($forum['minclasscreate'] ?? 1)) {
            return ['ok' => false, 'error' => '你的等级不足以在该板块发帖'];
        }
        $subject = trim($subject);
        if ($subject === '' || mb_strlen($subject) > 128) {
            return ['ok' => false, 'error' => '标题不能为空且不超过 128 字'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => '内容不能为空'];
        }
        if (mb_strlen($body) > 60000) {
            return ['ok' => false, 'error' => '内容过长（上限 60000 字符）'];
        }

        $now = date('Y-m-d H:i:s');
        $tid = NF_Storage::nextId('topic');
        $pid = NF_Storage::nextId('post');

        // 楼层正文 -> TXT 文件
        if (!NF_Storage::txtWrite(NF_Storage::postTxtPath($tid, $pid), $body)) {
            return ['ok' => false, 'error' => '内容写入失败'];
        }

        $topic = [
            'id' => $tid,
            'forumid' => $forumId,
            'userid' => $userId,
            'subject' => $subject,
            'added' => $now,
            'lastpost' => $pid,
            'views' => 0,
            'sticky' => 'no',
            'locked' => 'no',
            'hlcolor' => 0,
            'posts' => [
                [
                    'id' => $pid,
                    'userid' => $userId,
                    'added' => $now,
                    'editedby' => 0,
                    'editdate' => null,
                ],
            ],
        ];
        if (!NF_Storage::jsonWrite(NF_Storage::topicPath($tid), $topic)) {
            NF_Storage::delete(NF_Storage::postTxtPath($tid, $pid));
            return ['ok' => false, 'error' => '主题写入失败'];
        }

        // 更新板块计数
        self::updateForum($forumId, [
            'topiccount' => (int) ($forum['topiccount'] ?? 0) + 1,
            'postcount' => (int) ($forum['postcount'] ?? 0) + 1,
        ]);

        // 更新用户最后发帖时间
        NF_User::update($userId, ['last_post' => $now]);

        return ['ok' => true, 'topic' => $topic];
    }

    /**
     * 回复主题
     *
     * @return array{ok:bool, error?:string, post?:array}
     */
    public static function createPost(int $topicId, int $userId, string $body): array
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return ['ok' => false, 'error' => '主题不存在'];
        }
        if ($topic['locked'] === 'yes') {
            return ['ok' => false, 'error' => '该主题已锁定，无法回复'];
        }
        $forum = self::getForum((int) $topic['forumid']);
        if ($forum === null) {
            return ['ok' => false, 'error' => '板块不存在'];
        }
        $user = NF_User::get($userId);
        if ($user === null) {
            return ['ok' => false, 'error' => '用户不存在'];
        }
        if ((int) $user['class'] < (int) ($forum['minclasswrite'] ?? 1)) {
            return ['ok' => false, 'error' => '你的等级不足以在该板块回复'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => '内容不能为空'];
        }
        if (mb_strlen($body) > 60000) {
            return ['ok' => false, 'error' => '内容过长（上限 60000 字符）'];
        }

        $now = date('Y-m-d H:i:s');
        $pid = NF_Storage::nextId('post');

        if (!NF_Storage::txtWrite(NF_Storage::postTxtPath($topicId, $pid), $body)) {
            return ['ok' => false, 'error' => '内容写入失败'];
        }

        $topic['posts'][] = [
            'id' => $pid,
            'userid' => $userId,
            'added' => $now,
            'editedby' => 0,
            'editdate' => null,
        ];
        $topic['lastpost'] = $pid;

        if (!NF_Storage::jsonWrite(NF_Storage::topicPath($topicId), $topic)) {
            NF_Storage::delete(NF_Storage::postTxtPath($topicId, $pid));
            return ['ok' => false, 'error' => '回复写入失败'];
        }

        self::updateForum((int) $topic['forumid'], [
            'postcount' => (int) ($forum['postcount'] ?? 0) + 1,
        ]);
        NF_User::update($userId, ['last_post' => $now]);

        return ['ok' => true, 'post' => end($topic['posts'])];
    }

    public static function getTopic(int $id): ?array
    {
        return NF_Storage::jsonRead(NF_Storage::topicPath($id));
    }

    /**
     * 板块下的主题列表（置顶优先，按最后回复倒序）
     */
    public static function listTopicsByForum(int $forumId): array
    {
        return NF_Storage::scanJson('topics', function (array $t) use ($forumId): bool {
            return (int) ($t['forumid'] ?? 0) === $forumId;
        }, function (array $a, array $b): int {
            $sa = ($a['sticky'] ?? 'no') === 'yes' ? 1 : 0;
            $sb = ($b['sticky'] ?? 'no') === 'yes' ? 1 : 0;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            return ((int) ($b['lastpost'] ?? 0)) <=> ((int) ($a['lastpost'] ?? 0));
        });
    }

    /**
     * 主题分页数据
     *
     * @return array{topic:array, posts:array, total:int, page:int, pages:int}
     */
    public static function topicPage(int $topicId, int $page): array
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return ['topic' => [], 'posts' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        $perPage = max(5, (int) nf_config_get('posts_per_page', 10));
        $allPosts = $topic['posts'] ?? [];
        $total = count($allPosts);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $slice = array_slice($allPosts, ($page - 1) * $perPage, $perPage);
        return [
            'topic' => $topic,
            'posts' => $slice,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * 增加主题浏览量
     */
    public static function addView(int $topicId): void
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return;
        }
        $topic['views'] = (int) ($topic['views'] ?? 0) + 1;
        NF_Storage::jsonWrite(NF_Storage::topicPath($topicId), $topic);
    }

    /**
     * 编辑楼层（仅内容，元数据记录编辑人/时间）
     */
    public static function editPost(int $topicId, int $postId, string $body, int $editorId): array
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return ['ok' => false, 'error' => '主题不存在'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => '内容不能为空'];
        }
        $found = false;
        foreach ($topic['posts'] as &$post) {
            if ((int) $post['id'] === $postId) {
                $post['editedby'] = $editorId;
                $post['editdate'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($post);
        if (!$found) {
            return ['ok' => false, 'error' => '楼层不存在'];
        }
        if (!NF_Storage::txtWrite(NF_Storage::postTxtPath($topicId, $postId), $body)) {
            return ['ok' => false, 'error' => '内容写入失败'];
        }
        NF_Storage::jsonWrite(NF_Storage::topicPath($topicId), $topic);
        return ['ok' => true];
    }

    /**
     * 删除主题（含所有楼层 TXT）
     */
    public static function deleteTopic(int $topicId): bool
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return false;
        }
        // 删除楼层文件
        foreach ($topic['posts'] ?? [] as $post) {
            NF_Storage::delete(NF_Storage::postTxtPath($topicId, (int) $post['id']));
        }
        // 删除主题目录
        NF_Storage::deleteDir(NF_DATA . '/posts/' . $topicId);
        // 更新板块计数
        $forum = self::getForum((int) $topic['forumid']);
        if ($forum !== null) {
            self::updateForum((int) $forum['id'], [
                'topiccount' => max(0, (int) ($forum['topiccount'] ?? 0) - 1),
                'postcount' => max(0, (int) ($forum['postcount'] ?? 0) - count($topic['posts'] ?? [])),
            ]);
        }
        return NF_Storage::delete(NF_Storage::topicPath($topicId));
    }

    /**
     * 设置主题属性（置顶/锁定/高亮）
     */
    public static function setTopicFlag(int $topicId, string $field, string $value): bool
    {
        $topic = self::getTopic($topicId);
        if ($topic === null) {
            return false;
        }
        $topic[$field] = $value;
        return NF_Storage::jsonWrite(NF_Storage::topicPath($topicId), $topic);
    }

    /**
     * 读取楼层正文
     */
    public static function postBody(int $topicId, int $postId): string
    {
        return NF_Storage::txtRead(NF_Storage::postTxtPath($topicId, $postId));
    }

    /**
     * 全站最新主题（侧栏/统计用）
     */
    public static function latestTopics(int $limit = 10): array
    {
        $topics = NF_Storage::scanJson('topics', null, function (array $a, array $b): int {
            return strtotime($b['added'] ?? '0') <=> strtotime($a['added'] ?? '0');
        });
        return array_slice($topics, 0, $limit, true);
    }

    /**
     * 全站统计
     */
    public static function siteStats(): array
    {
        $topics = NF_Storage::scanJson('topics');
        $users = NF_Storage::scanJson('users');
        $topicCount = count($topics);
        $postCount = 0;
        foreach ($topics as $t) {
            $postCount += count($t['posts'] ?? []);
        }
        return [
            'topics' => $topicCount,
            'posts' => $postCount,
            'members' => count($users),
        ];
    }
}
