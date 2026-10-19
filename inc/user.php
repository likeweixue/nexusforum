<?php
/**
 * NexusForum - 用户系统
 *
 * 保留 NexusPHP 的用户等级（class）传统：
 *   0   禁言/未验证 (Peasant)
 *   1   用户 (User)
 *   2   力量用户 (Power User)   —— 起可发邀请
 *   3   精英用户 (Elite User)
 *   4   疯狂用户 (Crazy User)
 *   5   极客用户 (Insane User)
 *   10  VIP
 *   100 版主 (Moderator)
 *   200 管理员 (Administrator)
 */

declare(strict_types=1);

class NF_User
{
    /** 等级定义 */
    public const CLASS_NAMES = [
        0   => '禁言用户',
        1   => '用户',
        2   => '力量用户',
        3   => '精英用户',
        4   => '疯狂用户',
        5   => '极客用户',
        10  => 'VIP',
        100 => '版主',
        200 => '管理员',
    ];

    /**
     * 注册新用户（需有效邀请码，邀请逻辑在 NF_Invite 中处理）
     *
     * @return array{ok:bool, error?:string, user?:array}
     */
    public static function register(string $username, string $password, string $email, string $inviteHash, int $inviterUid = 0): array
    {
        $username = trim($username);
        $email = trim($email);

        // 校验用户名
        if ($username === '' || mb_strlen($username) < 2 || mb_strlen($username) > 20) {
            return ['ok' => false, 'error' => '用户名长度需为 2-20 个字符'];
        }
        if (!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]+$/u', $username)) {
            return ['ok' => false, 'error' => '用户名只能包含中英文、数字和下划线'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => '密码长度至少 6 位'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => '邮箱格式不正确'];
        }

        // 用户名唯一性
        if (self::findByUsername($username) !== null) {
            return ['ok' => false, 'error' => '该用户名已被注册'];
        }

        // 邮箱唯一性
        if (self::findByEmail($email) !== null) {
            return ['ok' => false, 'error' => '该邮箱已被注册'];
        }

        $uid = NF_Storage::nextId('user');
        $now = date('Y-m-d H:i:s');
        $user = [
            'id' => $uid,
            'username' => $username,
            'passhash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'class' => 1,
            'added' => $now,
            'last_login' => $now,
            'last_access' => $now,
            'last_post' => null,
            'invites' => 0,             // 可用邀请数
            'invited_by' => $inviterUid, // 邀请人
            'avatar' => '',
            'signature' => '',
            'enabled' => 'yes',
            'ip' => nf_client_ip(),
        ];

        if (!NF_Storage::jsonWrite(NF_Storage::userPath($uid), $user)) {
            return ['ok' => false, 'error' => '用户数据写入失败'];
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * 创建管理员（安装时使用）
     */
    public static function createAdmin(string $username, string $password, string $email): array
    {
        $result = self::register($username, $password, $email, '', 0);
        if (!$result['ok']) {
            return $result;
        }
        $uid = $result['user']['id'];
        $user = $result['user'];
        $user['class'] = 200;
        $user['invites'] = 99;
        NF_Storage::jsonWrite(NF_Storage::userPath($uid), $user);
        return ['ok' => true, 'user' => $user];
    }

    /**
     * 登录验证
     *
     * @return array{ok:bool, error?:string, user?:array}
     */
    public static function login(string $username, string $password): array
    {
        $user = self::findByUsername($username);
        if ($user === null) {
            return ['ok' => false, 'error' => '用户名或密码错误'];
        }
        if ($user['enabled'] !== 'yes') {
            return ['ok' => false, 'error' => '该账号已被禁用，请联系管理员'];
        }
        if (!password_verify($password, $user['passhash'])) {
            return ['ok' => false, 'error' => '用户名或密码错误'];
        }
        // 更新登录信息
        $user['last_login'] = date('Y-m-d H:i:s');
        $user['last_access'] = $user['last_login'];
        NF_Storage::jsonWrite(NF_Storage::userPath((int) $user['id']), $user);

        // 会话登录
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['class'] = (int) $user['class'];

        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    /**
     * 当前登录用户（未登录返回 null）
     */
    public static function current(): ?array
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $user = self::get((int) $_SESSION['uid']);
        if ($user === null || $user['enabled'] !== 'yes') {
            return null;
        }
        // 更新最后访问时间（每分钟最多写一次，避免频繁 IO）
        $now = time();
        $last = !empty($user['last_access']) ? strtotime($user['last_access']) : 0;
        if ($now - $last > 60) {
            $user['last_access'] = date('Y-m-d H:i:s', $now);
            NF_Storage::jsonWrite(NF_Storage::userPath((int) $user['id']), $user);
        }
        return $user;
    }

    public static function isLoggedIn(): bool
    {
        return self::current() !== null;
    }

    public static function get(int $uid): ?array
    {
        return NF_Storage::jsonRead(NF_Storage::userPath($uid));
    }

    public static function findByUsername(string $username): ?array
    {
        $users = NF_Storage::scanJson('users', function (array $u) use ($username): bool {
            return strcasecmp($u['username'] ?? '', $username) === 0;
        });
        return $users ? reset($users) : null;
    }

    public static function findByEmail(string $email): ?array
    {
        $users = NF_Storage::scanJson('users', function (array $u) use ($email): bool {
            return strcasecmp($u['email'] ?? '', $email) === 0;
        });
        return $users ? reset($users) : null;
    }

    public static function update(int $uid, array $fields): bool
    {
        $user = self::get($uid);
        if ($user === null) {
            return false;
        }
        $user = array_merge($user, $fields);
        return NF_Storage::jsonWrite(NF_Storage::userPath($uid), $user);
    }

    /**
     * 等级名称
     */
    public static function className(int $class): string
    {
        return self::CLASS_NAMES[$class] ?? ('等级 ' . $class);
    }

    /**
     * 是否为管理组成员（版主及以上）
     */
    public static function isStaff(?array $user): bool
    {
        return $user !== null && (int) $user['class'] >= 100;
    }

    /**
     * 是否为管理员
     */
    public static function isAdmin(?array $user): bool
    {
        return $user !== null && (int) $user['class'] >= 200;
    }

    /**
     * 用户是否可以发邀请（PT 传统：达到等级且还有邀请名额）
     */
    public static function canInvite(?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        $minClass = (int) nf_config_get('invite_min_class', 2);
        return (int) $user['class'] >= $minClass && (int) ($user['invites'] ?? 0) > 0;
    }

    /**
     * 用户统计（主题数/回复数）
     */
    public static function stats(int $uid): array
    {
        $topics = 0;
        $posts = 0;
        $allTopics = NF_Storage::scanJson('topics');
        foreach ($allTopics as $topic) {
            if ((int) ($topic['userid'] ?? 0) === $uid) {
                $topics++;
            }
            foreach ($topic['posts'] ?? [] as $post) {
                if ((int) ($post['userid'] ?? 0) === $uid) {
                    $posts++;
                }
            }
        }
        return ['topics' => $topics, 'posts' => $posts];
    }
}
