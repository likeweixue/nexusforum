<?php
/**
 * NexusForum - 入口文件 / 前端控制器
 *
 * 所有请求经由此文件路由：
 *   index.php                     论坛首页
 *   index.php?action=forum&fid=1  板块主题列表
 *   index.php?action=topic&tid=1  主题详情
 *   index.php?action=login        登录
 *   index.php?action=register     注册（邀请制）
 *   index.php?action=invite       邀请管理
 *   index.php?action=user&uid=1   用户资料
 *   index.php?action=admin        管理面板
 *   ... 其余 action 见下方 switch
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/util.php';
require __DIR__ . '/inc/storage.php';
require __DIR__ . '/inc/user.php';
require __DIR__ . '/inc/invite.php';
require __DIR__ . '/inc/forum.php';
require __DIR__ . '/inc/bbcode.php';
require __DIR__ . '/inc/template.php';

// ---------------------------------------------------------------
// 未安装 -> 引导安装
// ---------------------------------------------------------------
if (!nf_installed()) {
    nf_render('notinstalled');
    exit;
}

// ---------------------------------------------------------------
// 公共变量
// ---------------------------------------------------------------
$action = $_GET['action'] ?? '';
$currentUser = NF_User::current();
$pageTitle = '';

/**
 * 渲染错误页并结束
 */
function nf_error(string $message): never
{
    nf_render('error', ['message' => $message, 'pageTitle' => '错误']);
    exit;
}

/**
 * 要求登录，未登录则跳转登录页
 */
function nf_require_login(): array
{
    $user = NF_User::current();
    if ($user === null) {
        nf_flash('error', '请先登录');
        nf_redirect(nf_url('index.php?action=login&redirect=' . urlencode(nf_current_url())));
    }
    return $user;
}

/**
 * 要求管理权限
 */
function nf_require_admin(): array
{
    $user = nf_require_login();
    if (!NF_User::isAdmin($user)) {
        nf_error('无权访问管理面板');
    }
    return $user;
}

/**
 * 校验 CSRF（用于 GET 方式的管理操作）
 */
function nf_require_csrf_get(): void
{
    if (!nf_csrf_verify($_GET['csrf_token'] ?? null)) {
        nf_error('请求验证失败，请刷新页面重试');
    }
}

// ===============================================================
// 路由分发
// ===============================================================
switch ($action) {

    // -----------------------------------------------------------
    // 论坛首页
    // -----------------------------------------------------------
    case '':
        $pageTitle = '论坛首页';
        $GLOBALS['__nf_stats'] = NF_Forum::siteStats();
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'home']);
        nf_render('home', ['categories' => NF_Forum::indexData()]);
        break;

    // -----------------------------------------------------------
    // 板块主题列表
    // -----------------------------------------------------------
    case 'forum':
        $fid = (int) ($_GET['fid'] ?? 0);
        $forum = NF_Forum::getForum($fid);
        if ($forum === null) {
            nf_error('板块不存在');
        }
        // 阅读等级检查（游客可浏览公开板块，仅限制发帖）
        $userClass = $currentUser !== null ? (int) $currentUser['class'] : 0;
        if ($userClass < (int) ($forum['minclassread'] ?? 0)) {
            if ($currentUser === null) {
                nf_flash('error', '请先登录');
                nf_redirect(nf_url('index.php?action=login'));
            }
            nf_error('你的等级不足以浏览该板块');
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(5, (int) nf_config_get('topics_per_page', 20));
        $topics = NF_Forum::listTopicsByForum($fid);
        $total = count($topics);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $topics = array_slice($topics, ($page - 1) * $perPage, $perPage);

        $pageTitle = $forum['name'];
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'home']);
        nf_render('forum', [
            'forum' => $forum,
            'topics' => $topics,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
        break;

    // -----------------------------------------------------------
    // 主题详情
    // -----------------------------------------------------------
    case 'topic':
        $tid = (int) ($_GET['tid'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = NF_Forum::topicPage($tid, $page);
        if (empty($data['topic'])) {
            nf_error('主题不存在或已被删除');
        }
        $topic = $data['topic'];
        $forum = NF_Forum::getForum((int) $topic['forumid']);
        if ($forum === null) {
            nf_error('所属板块不存在');
        }
        // 阅读等级检查（游客可浏览公开板块，仅限制发帖）
        $userClass = $currentUser !== null ? (int) $currentUser['class'] : 0;
        if ($userClass < (int) ($forum['minclassread'] ?? 0)) {
            if ($currentUser === null) {
                nf_flash('error', '请先登录');
                nf_redirect(nf_url('index.php?action=login'));
            }
            nf_error('你的等级不足以浏览该板块');
        }
        // 浏览量 +1（简单防刷：同会话同主题只计一次）
        $viewKey = 'viewed_' . $tid;
        if (empty($_SESSION[$viewKey])) {
            NF_Forum::addView($tid);
            $_SESSION[$viewKey] = 1;
        }

        $pageTitle = $topic['subject'];
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'home']);
        nf_render('topic', [
            'topic' => $topic,
            'forum' => $forum,
            'posts' => $data['posts'],
            'page' => $data['page'],
            'pages' => $data['pages'],
            'total' => $data['total'],
        ]);
        break;

    // -----------------------------------------------------------
    // 发表新主题（GET 表单 / POST 处理）
    // -----------------------------------------------------------
    case 'newtopic':
        $user = nf_require_login();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            $fid = (int) ($_POST['fid'] ?? 0);
            $result = NF_Forum::createTopic($fid, (int) $user['id'], (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''));
            if (!$result['ok']) {
                nf_flash('error', $result['error']);
                nf_redirect(nf_url('index.php?action=newtopic&fid=' . $fid));
            }
            nf_flash('success', '主题发表成功');
            nf_redirect(nf_url('index.php?action=topic&tid=' . (int) $result['topic']['id']));
        }
        $fid = (int) ($_GET['fid'] ?? 0);
        $forum = NF_Forum::getForum($fid);
        if ($forum === null) {
            nf_error('板块不存在');
        }
        if ((int) $user['class'] < (int) ($forum['minclasscreate'] ?? 1)) {
            nf_error('你的等级不足以在该板块发帖');
        }
        $pageTitle = '发表新主题';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'home']);
        nf_render('newtopic', ['forum' => $forum]);
        break;

    // -----------------------------------------------------------
    // 回复主题（POST）
    // -----------------------------------------------------------
    case 'reply':
        $user = nf_require_login();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !nf_csrf_verify($_POST['csrf_token'] ?? null)) {
            nf_error('非法请求');
        }
        $tid = (int) ($_POST['tid'] ?? 0);
        $result = NF_Forum::createPost($tid, (int) $user['id'], (string) ($_POST['body'] ?? ''));
        if (!$result['ok']) {
            nf_flash('error', $result['error']);
            nf_redirect(nf_url('index.php?action=topic&tid=' . $tid));
        }
        nf_flash('success', '回复成功');
        nf_redirect(nf_url('index.php?action=topic&tid=' . $tid));
        break;

    // -----------------------------------------------------------
    // 编辑楼层（GET 表单 / POST 处理）
    // -----------------------------------------------------------
    case 'editpost':
        $user = nf_require_login();
        $tid = (int) ($_GET['tid'] ?? $_POST['tid'] ?? 0);
        $pid = (int) ($_GET['pid'] ?? $_POST['pid'] ?? 0);
        $topic = NF_Forum::getTopic($tid);
        if ($topic === null) {
            nf_error('主题不存在');
        }
        // 找到楼层
        $postMeta = null;
        foreach ($topic['posts'] ?? [] as $p) {
            if ((int) $p['id'] === $pid) {
                $postMeta = $p;
                break;
            }
        }
        if ($postMeta === null) {
            nf_error('楼层不存在');
        }
        // 权限：本人或管理组
        if ((int) $user['id'] !== (int) $postMeta['userid'] && !NF_User::isStaff($user)) {
            nf_error('你无权编辑该楼层');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            $result = NF_Forum::editPost($tid, $pid, (string) ($_POST['body'] ?? ''), (int) $user['id']);
            if (!$result['ok']) {
                nf_flash('error', $result['error']);
                nf_redirect(nf_url('index.php?action=editpost&tid=' . $tid . '&pid=' . $pid));
            }
            nf_flash('success', '楼层已更新');
            nf_redirect(nf_url('index.php?action=topic&tid=' . $tid));
        }
        $pageTitle = '编辑楼层';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'home']);
        nf_render('editpost', [
            'topic' => $topic,
            'post' => $postMeta,
            'body' => NF_Forum::postBody($tid, $pid),
        ]);
        break;

    // -----------------------------------------------------------
    // 版务操作（置顶/锁定/删除主题）
    // -----------------------------------------------------------
    case 'mod_topic':
        $user = nf_require_login();
        if (!NF_User::isStaff($user)) {
            nf_error('无权执行该操作');
        }
        nf_require_csrf_get();
        $tid = (int) ($_GET['tid'] ?? 0);
        $op = $_GET['op'] ?? '';
        $topic = NF_Forum::getTopic($tid);
        if ($topic === null) {
            nf_error('主题不存在');
        }
        switch ($op) {
            case 'sticky':
                NF_Forum::setTopicFlag($tid, 'sticky', ($topic['sticky'] ?? 'no') === 'yes' ? 'no' : 'yes');
                nf_flash('success', '操作成功');
                break;
            case 'lock':
                NF_Forum::setTopicFlag($tid, 'locked', ($topic['locked'] ?? 'no') === 'yes' ? 'no' : 'yes');
                nf_flash('success', '操作成功');
                break;
            case 'delete':
                NF_Forum::deleteTopic($tid);
                nf_flash('success', '主题已删除');
                nf_redirect(nf_url('index.php?action=forum&fid=' . (int) $topic['forumid']));
                break;
            default:
                nf_error('未知操作');
        }
        nf_redirect(nf_url('index.php?action=topic&tid=' . $tid));
        break;

    // -----------------------------------------------------------
    // 登录（GET 表单 / POST 处理）
    // -----------------------------------------------------------
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            $result = NF_User::login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
            if (!$result['ok']) {
                nf_flash('error', $result['error']);
                nf_redirect(nf_url('index.php?action=login'));
            }
            $redirect = $_POST['redirect'] ?? '';
            // 仅允许站内回跳
            if (!is_string($redirect) || $redirect === '' || strpos($redirect, '://') !== false) {
                $redirect = nf_url();
            }
            nf_flash('success', '欢迎回来，' . $result['user']['username']);
            nf_redirect($redirect);
        }
        $pageTitle = '登录';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => '']);
        nf_render('login', ['redirect' => $_GET['redirect'] ?? nf_current_url()]);
        break;

    // -----------------------------------------------------------
    // 退出
    // -----------------------------------------------------------
    case 'logout':
        nf_require_csrf_get();
        NF_User::logout();
        nf_flash('success', '已退出登录');
        nf_redirect(nf_url());
        break;

    // -----------------------------------------------------------
    // 注册（GET 表单 / POST 处理）—— 邀请制
    // -----------------------------------------------------------
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            if ($password !== $passwordConfirm) {
                nf_flash('error', '两次输入的密码不一致');
                nf_redirect(nf_url('index.php?action=register'));
            }
            $inviteHash = (string) ($_POST['invite_hash'] ?? '');
            $inviterUid = 0;

            // 邀请校验（PT 传统：必须持码注册）
            if (!empty(nf_config_get('invite_required', true))) {
                $check = NF_Invite::validate($inviteHash);
                if (!$check['ok']) {
                    nf_flash('error', $check['error']);
                    nf_redirect(nf_url('index.php?action=register'));
                }
                $inviterUid = (int) ($check['invite']['inviter'] ?? 0);
            }

            $result = NF_User::register(
                (string) ($_POST['username'] ?? ''),
                $password,
                (string) ($_POST['email'] ?? ''),
                $inviteHash,
                $inviterUid
            );
            if (!$result['ok']) {
                nf_flash('error', $result['error']);
                nf_redirect(nf_url('index.php?action=register'));
            }
            // 消耗邀请码
            if (!empty(nf_config_get('invite_required', true))) {
                NF_Invite::consume($inviteHash, (int) $result['user']['id']);
            }
            // 注册后自动登录
            NF_User::login((string) ($_POST['username'] ?? ''), $password);
            nf_flash('success', '注册成功，欢迎加入！');
            nf_redirect(nf_url());
        }
        $pageTitle = '注册';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => '']);
        nf_render('register', ['prefillHash' => $_GET['hash'] ?? '']);
        break;

    // -----------------------------------------------------------
    // 邀请管理（GET 列表 / POST 生成）
    // -----------------------------------------------------------
    case 'invite':
        $user = nf_require_login();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            if (($_POST['op'] ?? '') === 'generate') {
                $expireDays = (int) ($_POST['expire_days'] ?? 7);
                $result = NF_Invite::generate((int) $user['id'], (string) ($_POST['invitee'] ?? ''), $expireDays);
                if ($result['ok']) {
                    nf_flash('success', '邀请码已生成：' . $result['invite']['hash']);
                } else {
                    nf_flash('error', $result['error']);
                }
            }
            nf_redirect(nf_url('index.php?action=invite'));
        }
        $pageTitle = '我的邀请';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => '']);
        nf_render('invite', [
            'invites' => NF_Invite::listByInviter((int) $user['id']),
            'user' => $user,
            'quota' => (int) ($user['invites'] ?? 0),
            'canInvite' => NF_User::canInvite($user),
        ]);
        break;

    // -----------------------------------------------------------
    // 用户资料（GET 查看 / POST 修改自己的资料）
    // -----------------------------------------------------------
    case 'user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = nf_require_login();
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            if (($_POST['op'] ?? '') === 'profile') {
                $fields = ['signature' => mb_substr(trim((string) ($_POST['signature'] ?? '')), 0, 100)];
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 6) {
                        nf_flash('error', '密码长度至少 6 位');
                        nf_redirect(nf_url('index.php?action=user&uid=' . (int) $user['id']));
                    }
                    $fields['passhash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                NF_User::update((int) $user['id'], $fields);
                nf_flash('success', '资料已更新');
            }
            nf_redirect(nf_url('index.php?action=user&uid=' . (int) $user['id']));
        }
        $uid = (int) ($_GET['uid'] ?? 0);
        $profile = NF_User::get($uid);
        if ($profile === null) {
            nf_error('用户不存在');
        }
        $pageTitle = $profile['username'] . ' 的资料';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => '']);
        nf_render('user', [
            'profile' => $profile,
            'stats' => NF_User::stats($uid),
        ]);
        break;

    // -----------------------------------------------------------
    // 最新主题
    // -----------------------------------------------------------
    case 'latest':
        $pageTitle = '最新主题';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'latest']);
        nf_render('latest', ['topics' => NF_Forum::latestTopics(30)]);
        break;

    // -----------------------------------------------------------
    // 会员列表
    // -----------------------------------------------------------
    case 'members':
        $users = NF_Storage::scanJson('users', null, function (array $a, array $b): int {
            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        $pageTitle = '会员列表';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => 'members']);
        nf_render('members', ['users' => $users]);
        break;

    // -----------------------------------------------------------
    // 管理面板
    // -----------------------------------------------------------
    case 'admin':
        $user = nf_require_admin();

        // GET 快捷操作
        $op = $_GET['op'] ?? '';
        if ($op !== '') {
            nf_require_csrf_get();
            switch ($op) {
                case 'del_forum':
                    NF_Forum::deleteForum((int) ($_GET['fid'] ?? 0));
                    nf_flash('success', '板块已删除');
                    break;
                case 'del_overforum':
                    NF_Forum::deleteOverforum((int) ($_GET['ofid'] ?? 0));
                    nf_flash('success', '分类已删除');
                    break;
                case 'toggle_user':
                    $target = NF_User::get((int) ($_GET['uid'] ?? 0));
                    if ($target !== null && (int) $target['id'] !== (int) $user['id']) {
                        NF_User::update((int) $target['id'], ['enabled' => ($target['enabled'] ?? 'yes') === 'yes' ? 'no' : 'yes']);
                        nf_flash('success', '操作成功');
                    }
                    break;
                case 'revoke_invite':
                    NF_Invite::revoke((string) ($_GET['hash'] ?? ''));
                    nf_flash('success', '邀请码已作废');
                    break;
            }
            nf_redirect(nf_url('index.php?action=admin&tab=' . ($_GET['tab'] ?? 'settings')));
        }

        // POST 表单操作
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!nf_csrf_verify($_POST['csrf_token'] ?? null)) {
                nf_error('请求验证失败，请刷新页面重试');
            }
            $postOp = $_POST['op'] ?? '';
            switch ($postOp) {
                case 'settings':
                    $config = nf_config();
                    $config['site_name'] = trim((string) ($_POST['site_name'] ?? 'NexusForum'));
                    $config['site_desc'] = trim((string) ($_POST['site_desc'] ?? ''));
                    $config['invite_required'] = !empty($_POST['invite_required']);
                    $config['invite_min_class'] = (int) ($_POST['invite_min_class'] ?? 2);
                    $config['invite_expire_days'] = max(0, (int) ($_POST['invite_expire_days'] ?? 7));
                    $config['topics_per_page'] = max(5, min(100, (int) ($_POST['topics_per_page'] ?? 20)));
                    $config['posts_per_page'] = max(5, min(50, (int) ($_POST['posts_per_page'] ?? 10)));
                    nf_save_config($config);
                    nf_flash('success', '设置已保存');
                    break;
                case 'add_overforum':
                    $result = NF_Forum::createOverforum(
                        (string) ($_POST['name'] ?? ''),
                        (string) ($_POST['description'] ?? ''),
                        (int) ($_POST['sort'] ?? 0)
                    );
                    nf_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? '分类已添加' : $result['error']);
                    break;
                case 'add_forum':
                    $result = NF_Forum::createForum(
                        (string) ($_POST['name'] ?? ''),
                        (string) ($_POST['description'] ?? ''),
                        (int) ($_POST['forid'] ?? 0),
                        0,
                        0,
                        1,
                        (int) ($_POST['minclasscreate'] ?? 1)
                    );
                    nf_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? '板块已添加' : $result['error']);
                    break;
                case 'set_class':
                    $targetUid = (int) ($_POST['uid'] ?? 0);
                    if ($targetUid !== (int) $user['id']) {
                        NF_User::update($targetUid, ['class' => (int) ($_POST['class'] ?? 1)]);
                        nf_flash('success', '用户等级已更新');
                    }
                    break;
                case 'set_invites':
                    NF_User::update((int) ($_POST['uid'] ?? 0), ['invites' => max(0, (int) ($_POST['invites'] ?? 0))]);
                    nf_flash('success', '邀请名额已更新');
                    break;
                case 'gen_invite':
                    $result = NF_Invite::generate(0, (string) ($_POST['invitee'] ?? ''), (int) ($_POST['expire_days'] ?? 7));
                    nf_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? '邀请码已生成：' . $result['invite']['hash'] : $result['error']);
                    break;
            }
            nf_redirect(nf_url('index.php?action=admin&tab=' . ($_POST['tab'] ?? 'settings')));
        }

        // 渲染面板
        $tab = $_GET['tab'] ?? 'settings';
        if (!in_array($tab, ['settings', 'forums', 'users', 'invites'], true)) {
            $tab = 'settings';
        }
        $pageTitle = '管理面板';
        nf_render('layout', ['pageTitle' => $pageTitle, 'activeNav' => '']);
        nf_render('admin', [
            'tab' => $tab,
            'overforums' => NF_Forum::listOverforums(),
            'forums' => NF_Forum::listForums(),
            'users' => NF_Storage::scanJson('users', null, function (array $a, array $b): int {
                return ((int) $b['id']) <=> ((int) $a['id']);
            }),
            'invites' => NF_Invite::listAll(),
        ]);
        break;

    // -----------------------------------------------------------
    // 404
    // -----------------------------------------------------------
    default:
        nf_error('页面不存在');
}

// ===============================================================
// 页脚
// ===============================================================
nf_render('footer', []);
