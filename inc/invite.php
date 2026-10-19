<?php
/**
 * NexusForum - 邀请系统（保留 PT 站邀请制度传统）
 *
 * 规则（参照 NexusPHP invites 表设计）：
 *   - 注册必须持有有效邀请码
 *   - 邀请码由管理员生成，或达到指定等级的用户自行生成
 *   - 邀请码可设置有效期（默认 7 天，可配置）
 *   - 邀请码被使用后立即失效，并记录使用者
 *   - 支持"预注册"：生成时绑定受邀邮箱/用户名（可选）
 */

declare(strict_types=1);

class NF_Invite
{
    /**
     * 生成邀请码
     *
     * @param int    $inviterUid 邀请人 UID（0 = 系统/管理员）
     * @param string $invitee    预绑定的受邀人（邮箱或用户名，可空）
     * @param int    $expireDays 有效天数（0 = 永久）
     * @return array{ok:bool, error?:string, invite?:array}
     */
    public static function generate(int $inviterUid, string $invitee = '', int $expireDays = 0): array
    {
        $inviter = $inviterUid > 0 ? NF_User::get($inviterUid) : null;
        if ($inviterUid > 0 && $inviter === null) {
            return ['ok' => false, 'error' => '邀请人不存在'];
        }

        // 普通用户生成：扣减邀请名额
        if ($inviter !== null && (int) $inviter['class'] < 200) {
            if (!NF_User::canInvite($inviter)) {
                return ['ok' => false, 'error' => '你没有邀请权限或邀请名额已用完'];
            }
        }

        // 有效期：默认取配置
        if ($expireDays <= 0) {
            $expireDays = (int) nf_config_get('invite_expire_days', 7);
        }

        $hash = nf_random_code(16);
        $now = date('Y-m-d H:i:s');
        $invite = [
            'hash' => $hash,
            'inviter' => $inviterUid,
            'invitee' => $invitee,
            'time_invited' => $now,
            'valid' => 'yes',
            'expired_at' => $expireDays > 0 ? date('Y-m-d H:i:s', strtotime($now . " +{$expireDays} days")) : null,
            'invitee_register_uid' => 0,
        ];

        if (!NF_Storage::jsonWrite(NF_Storage::invitePath($hash), $invite)) {
            return ['ok' => false, 'error' => '邀请码写入失败'];
        }

        // 扣减邀请名额
        if ($inviter !== null && (int) $inviter['class'] < 200) {
            NF_User::update($inviterUid, ['invites' => max(0, (int) $inviter['invites'] - 1)]);
        }

        return ['ok' => true, 'invite' => $invite];
    }

    /**
     * 校验邀请码（不消耗）
     *
     * @return array{ok:bool, error?:string, invite?:array}
     */
    public static function validate(string $hash): array
    {
        $hash = strtoupper(trim($hash));
        if ($hash === '' || !preg_match('/^[A-Z0-9]{4,32}$/', $hash)) {
            return ['ok' => false, 'error' => '邀请码格式不正确'];
        }
        $invite = NF_Storage::jsonRead(NF_Storage::invitePath($hash));
        if ($invite === null) {
            return ['ok' => false, 'error' => '邀请码不存在'];
        }
        if ($invite['valid'] !== 'yes') {
            return ['ok' => false, 'error' => '该邀请码已被使用'];
        }
        if (!empty($invite['expired_at']) && strtotime($invite['expired_at']) < time()) {
            return ['ok' => false, 'error' => '该邀请码已过期'];
        }
        return ['ok' => true, 'invite' => $invite];
    }

    /**
     * 消耗邀请码（注册成功后调用）
     */
    public static function consume(string $hash, int $newUid): bool
    {
        $hash = strtoupper(trim($hash));
        $invite = NF_Storage::jsonRead(NF_Storage::invitePath($hash));
        if ($invite === null || $invite['valid'] !== 'yes') {
            return false;
        }
        $invite['valid'] = 'no';
        $invite['invitee_register_uid'] = $newUid;
        return NF_Storage::jsonWrite(NF_Storage::invitePath($hash), $invite);
    }

    /**
     * 获取某用户发出的邀请列表
     */
    public static function listByInviter(int $uid): array
    {
        return NF_Storage::scanJson('invites', function (array $i) use ($uid): bool {
            return (int) ($i['inviter'] ?? 0) === $uid;
        }, function (array $a, array $b): int {
            return strtotime($b['time_invited'] ?? '0') <=> strtotime($a['time_invited'] ?? '0');
        });
    }

    /**
     * 全部邀请（管理用）
     */
    public static function listAll(): array
    {
        return NF_Storage::scanJson('invites', null, function (array $a, array $b): int {
            return strtotime($b['time_invited'] ?? '0') <=> strtotime($a['time_invited'] ?? '0');
        });
    }

    /**
     * 作废邀请码
     */
    public static function revoke(string $hash): bool
    {
        $invite = NF_Storage::jsonRead(NF_Storage::invitePath($hash));
        if ($invite === null) {
            return false;
        }
        $invite['valid'] = 'no';
        return NF_Storage::jsonWrite(NF_Storage::invitePath($hash), $invite);
    }

    /**
     * 邀请状态文本
     */
    public static function statusText(array $invite): string
    {
        if ($invite['valid'] !== 'yes') {
            return '已使用';
        }
        if (!empty($invite['expired_at']) && strtotime($invite['expired_at']) < time()) {
            return '已过期';
        }
        return '有效';
    }
}
