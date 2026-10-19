<?php
/**
 * 管理面板
 *
 * @var string $tab 当前标签页
 * @var array  $overforums 分类列表
 * @var array  $forums     板块列表
 * @var array  $users      用户列表
 * @var array  $invites    邀请列表
 */
/** @var string $tab */
/** @var array $overforums */
/** @var array $forums */
/** @var array $users */
/** @var array $invites */
$config = nf_config();
?>
<div class="breadcrumb"><a href="<?= h(nf_url()) ?>">论坛首页</a> &raquo; <span>管理面板</span></div>

<div class="admin-tabs">
    <a href="<?= h(nf_url('index.php?action=admin&tab=settings')) ?>" class="<?= $tab === 'settings' ? 'active' : '' ?>">站点设置</a>
    <a href="<?= h(nf_url('index.php?action=admin&tab=forums')) ?>" class="<?= $tab === 'forums' ? 'active' : '' ?>">板块管理</a>
    <a href="<?= h(nf_url('index.php?action=admin&tab=users')) ?>" class="<?= $tab === 'users' ? 'active' : '' ?>">用户管理</a>
    <a href="<?= h(nf_url('index.php?action=admin&tab=invites')) ?>" class="<?= $tab === 'invites' ? 'active' : '' ?>">邀请管理</a>
</div>

<?php if ($tab === 'settings'): ?>
    <div class="form-box">
        <h2>站点设置</h2>
        <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>">
            <?= nf_csrf_field() ?>
            <input type="hidden" name="op" value="settings">
            <div class="form-row">
                <label>站点名称</label>
                <input type="text" name="site_name" value="<?= h($config['site_name'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <label>站点描述</label>
                <input type="text" name="site_desc" value="<?= h($config['site_desc'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label>注册需邀请码</label>
                <select name="invite_required">
                    <option value="1" <?= !empty($config['invite_required']) ? 'selected' : '' ?>>是（PT 传统）</option>
                    <option value="0" <?= empty($config['invite_required']) ? 'selected' : '' ?>>否（开放注册）</option>
                </select>
            </div>
            <div class="form-row">
                <label>可发邀请的最低等级</label>
                <select name="invite_min_class">
                    <?php foreach (NF_User::CLASS_NAMES as $class => $name): ?>
                        <?php if ($class >= 1 && $class < 100): ?>
                            <option value="<?= $class ?>" <?= (int) ($config['invite_min_class'] ?? 2) === $class ? 'selected' : '' ?>><?= $class ?> - <?= h($name) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label>邀请码有效期（天，0=永久）</label>
                <input type="number" name="invite_expire_days" value="<?= (int) ($config['invite_expire_days'] ?? 7) ?>" min="0" max="365">
            </div>
            <div class="form-row">
                <label>每页主题数</label>
                <input type="number" name="topics_per_page" value="<?= (int) ($config['topics_per_page'] ?? 20) ?>" min="5" max="100">
            </div>
            <div class="form-row">
                <label>每页楼层数</label>
                <input type="number" name="posts_per_page" value="<?= (int) ($config['posts_per_page'] ?? 10) ?>" min="5" max="50">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>

<?php elseif ($tab === 'forums'): ?>
    <div class="admin-section">
        <h2>板块管理</h2>

        <div class="admin-form">
            <h3>新增分类</h3>
            <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>" class="inline-form">
                <?= nf_csrf_field() ?>
                <input type="hidden" name="op" value="add_overforum">
                <input type="text" name="name" placeholder="分类名称" required>
                <input type="text" name="description" placeholder="分类描述（可选）">
                <input type="number" name="sort" value="0" min="0" title="排序">
                <button type="submit" class="btn btn-primary">添加分类</button>
            </form>
        </div>

        <div class="admin-form">
            <h3>新增板块</h3>
            <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>" class="inline-form">
                <?= nf_csrf_field() ?>
                <input type="hidden" name="op" value="add_forum">
                <input type="text" name="name" placeholder="板块名称" required>
                <input type="text" name="description" placeholder="板块描述（可选）">
                <select name="forid" required>
                    <option value="">所属分类</option>
                    <?php foreach ($overforums as $of): ?>
                        <option value="<?= (int) $of['id'] ?>"><?= h($of['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="minclasscreate" title="发帖等级">
                    <?php foreach (NF_User::CLASS_NAMES as $class => $cname): ?>
                        <?php if ($class < 100): ?>
                            <option value="<?= $class ?>" <?= $class === 1 ? 'selected' : '' ?>><?= $class ?> - <?= h($cname) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">添加板块</button>
            </form>
        </div>

        <h3>现有板块</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>所属分类</th>
                    <th>板块名称</th>
                    <th>主题</th>
                    <th>帖子</th>
                    <th>发帖等级</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($forums as $forum): ?>
                <?php $of = NF_Forum::getOverforum((int) ($forum['forid'] ?? 0)); ?>
                <tr>
                    <td><?= (int) $forum['id'] ?></td>
                    <td><?= $of !== null ? h($of['name']) : '-' ?></td>
                    <td><?= h($forum['name']) ?></td>
                    <td><?= nf_number((int) ($forum['topiccount'] ?? 0)) ?></td>
                    <td><?= nf_number((int) ($forum['postcount'] ?? 0)) ?></td>
                    <td><?= (int) ($forum['minclasscreate'] ?? 1) ?></td>
                    <td>
                        <a class="btn btn-small btn-danger" href="<?= h(nf_url('index.php?action=admin&op=del_forum&fid=' . (int) $forum['id'] . '&csrf_token=' . nf_csrf_token())) ?>" onclick="return confirm('删除板块将同时删除其下所有主题，确定？')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>现有分类</h3>
        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>名称</th><th>描述</th><th>排序</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php foreach ($overforums as $of): ?>
                <tr>
                    <td><?= (int) $of['id'] ?></td>
                    <td><?= h($of['name']) ?></td>
                    <td><?= h($of['description'] ?? '') ?></td>
                    <td><?= (int) ($of['sort'] ?? 0) ?></td>
                    <td>
                        <a class="btn btn-small btn-danger" href="<?= h(nf_url('index.php?action=admin&op=del_overforum&ofid=' . (int) $of['id'] . '&csrf_token=' . nf_csrf_token())) ?>" onclick="return confirm('删除分类将同时删除其下所有板块，确定？')">删除</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'users'): ?>
    <div class="admin-section">
        <h2>用户管理</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>等级</th>
                    <th>邀请名额</th>
                    <th>状态</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td><?= h($u['username']) ?></td>
                    <td><?= h($u['email'] ?? '') ?></td>
                    <td>
                        <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>" class="inline-form">
                            <?= nf_csrf_field() ?>
                            <input type="hidden" name="op" value="set_class">
                            <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
                            <select name="class" onchange="this.form.submit()">
                                <?php foreach (NF_User::CLASS_NAMES as $class => $cname): ?>
                                    <option value="<?= $class ?>" <?= (int) $u['class'] === $class ? 'selected' : '' ?>><?= $class ?> - <?= h($cname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>" class="inline-form">
                            <?= nf_csrf_field() ?>
                            <input type="hidden" name="op" value="set_invites">
                            <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
                            <input type="number" name="invites" value="<?= (int) ($u['invites'] ?? 0) ?>" min="0" max="999" style="width:70px" onchange="this.form.submit()">
                        </form>
                    </td>
                    <td><?= ($u['enabled'] ?? 'yes') === 'yes' ? '<span class="badge badge-ok">正常</span>' : '<span class="badge badge-locked">禁用</span>' ?></td>
                    <td><?= h(nf_time($u['added'] ?? null)) ?></td>
                    <td>
                        <?php if ((int) $u['id'] !== (int) ($_SESSION['uid'] ?? 0)): ?>
                            <a class="btn btn-small" href="<?= h(nf_url('index.php?action=admin&op=toggle_user&uid=' . (int) $u['id'] . '&csrf_token=' . nf_csrf_token())) ?>">
                                <?= ($u['enabled'] ?? 'yes') === 'yes' ? '禁用' : '启用' ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($tab === 'invites'): ?>
    <div class="admin-section">
        <h2>邀请管理</h2>

        <div class="admin-form">
            <h3>管理员生成邀请码</h3>
            <form method="post" action="<?= h(nf_url('index.php?action=admin')) ?>" class="inline-form">
                <?= nf_csrf_field() ?>
                <input type="hidden" name="op" value="gen_invite">
                <input type="text" name="invitee" placeholder="预绑定受邀人（可选）">
                <select name="expire_days">
                    <option value="1">1 天</option>
                    <option value="7" selected>7 天</option>
                    <option value="30">30 天</option>
                    <option value="0">永久</option>
                </select>
                <button type="submit" class="btn btn-primary">生成</button>
            </form>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>邀请码</th>
                    <th>邀请人</th>
                    <th>受邀人</th>
                    <th>生成时间</th>
                    <th>有效期至</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($invites)): ?>
                <tr><td colspan="7" class="empty">暂无邀请</td></tr>
            <?php else: ?>
                <?php foreach ($invites as $invite): ?>
                    <?php
                    $inviter = (int) ($invite['inviter'] ?? 0) > 0 ? NF_User::get((int) $invite['inviter']) : null;
                    $status = NF_Invite::statusText($invite);
                    ?>
                    <tr>
                        <td><code class="invite-code"><?= h($invite['hash']) ?></code></td>
                        <td><?= $inviter !== null ? h($inviter['username']) : '系统' ?></td>
                        <td><?= !empty($invite['invitee']) ? h($invite['invitee']) : '<span class="muted">不限</span>' ?></td>
                        <td><?= h(nf_time($invite['time_invited'] ?? null)) ?></td>
                        <td><?= !empty($invite['expired_at']) ? h(nf_time($invite['expired_at'])) : '永久' ?></td>
                        <td><span class="badge badge-<?= $status === '有效' ? 'ok' : 'muted' ?>"><?= h($status) ?></span></td>
                        <td>
                            <?php if ($status === '有效'): ?>
                                <a class="btn btn-small btn-danger" href="<?= h(nf_url('index.php?action=admin&op=revoke_invite&hash=' . urlencode($invite['hash']) . '&csrf_token=' . nf_csrf_token())) ?>">作废</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
