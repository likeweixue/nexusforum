<?php
/**
 * NexusForum - 文件存储引擎
 *
 * 设计说明（对应需求：帖子/回复内容生成 TXT 文件存放）：
 *   data/users/{uid}.json          用户资料（元数据）
 *   data/forums/{fid}.json         板块定义
 *   data/overforums/{ofid}.json    板块分类（NexusPHP 的 overforum）
 *   data/topics/{tid}.json         主题元数据 + 楼层索引
 *   data/posts/{tid}/{pid}.txt     楼层正文（纯文本，BBCode 原文）
 *   data/invites/{hash}.json       邀请码
 *   data/counters.json             自增 ID 计数器
 *
 * 所有写操作使用 flock 排它锁，保证并发安全。
 */

declare(strict_types=1);

class NF_Storage
{
    /** @var string 数据根目录 */
    private static string $root = NF_DATA;

    /**
     * 初始化数据目录结构
     */
    public static function initDataDir(): void
    {
        $dirs = ['users', 'forums', 'overforums', 'topics', 'posts', 'invites'];
        foreach ($dirs as $d) {
            $path = self::$root . '/' . $d;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        if (!is_file(self::$root . '/counters.json')) {
            self::jsonWrite(self::$root . '/counters.json', [
                'user' => 10000,
                'forum' => 0,
                'overforum' => 0,
                'topic' => 0,
                'post' => 0,
            ]);
        }
        // 保护文件：阻止 Web 直接访问数据目录
        $guard = self::$root . '/.htaccess';
        if (!is_file($guard)) {
            file_put_contents($guard, "Require all denied\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
    }

    // ------------------------------------------------------------------
    // JSON 元数据读写
    // ------------------------------------------------------------------

    /**
     * 读取 JSON 文件，失败返回 null
     */
    public static function jsonRead(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        // 共享锁读
        if (flock($fh, LOCK_SH)) {
            $raw = stream_get_contents($fh);
            flock($fh, LOCK_UN);
        } else {
            $raw = stream_get_contents($fh);
        }
        fclose($fh);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 原子写入 JSON 文件（先写临时文件再 rename，避免写一半）
     */
    public static function jsonWrite(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.' . getmypid() . '.tmp';
        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            return false;
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            @unlink($tmp);
            return false;
        }
        fwrite($fh, $json);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return rename($tmp, $path);
    }

    // ------------------------------------------------------------------
    // TXT 内容读写（帖子正文）
    // ------------------------------------------------------------------

    /**
     * 读取 TXT 内容
     */
    public static function txtRead(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        if (flock($fh, LOCK_SH)) {
            $raw = stream_get_contents($fh);
            flock($fh, LOCK_UN);
        } else {
            $raw = stream_get_contents($fh);
        }
        fclose($fh);
        return (string) $raw;
    }

    /**
     * 原子写入 TXT 内容
     */
    public static function txtWrite(string $path, string $content): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = $path . '.' . getmypid() . '.tmp';
        $fh = fopen($tmp, 'wb');
        if ($fh === false) {
            return false;
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            @unlink($tmp);
            return false;
        }
        fwrite($fh, $content);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return rename($tmp, $path);
    }

    // ------------------------------------------------------------------
    // 自增 ID
    // ------------------------------------------------------------------

    /**
     * 获取下一个自增 ID（线程安全：排它锁整个计数器文件）
     */
    public static function nextId(string $name): int
    {
        $path = self::$root . '/counters.json';
        $fh = fopen($path, 'c+');
        if ($fh === false) {
            throw new RuntimeException('无法打开计数器文件');
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            throw new RuntimeException('无法锁定计数器文件');
        }
        $raw = stream_get_contents($fh);
        $counters = json_decode((string) $raw, true);
        if (!is_array($counters)) {
            $counters = ['user' => 10000, 'forum' => 0, 'overforum' => 0, 'topic' => 0, 'post' => 0];
        }
        $next = (int) ($counters[$name] ?? 0) + 1;
        $counters[$name] = $next;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($counters, JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return $next;
    }

    // ------------------------------------------------------------------
    // 目录扫描
    // ------------------------------------------------------------------

    /**
     * 列出目录下所有 .json 文件并读取，返回 [id => data]
     *
     * @param string $subdir 子目录名（users/forums/topics/...）
     * @param callable|null $filter 过滤回调 fn(array $data): bool
     * @param callable|null $sort 排序回调 usort 风格
     */
    public static function scanJson(string $subdir, ?callable $filter = null, ?callable $sort = null): array
    {
        $dir = self::$root . '/' . $subdir;
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        $files = glob($dir . '/*.json') ?: [];
        foreach ($files as $file) {
            $data = self::jsonRead($file);
            if ($data === null) {
                continue;
            }
            if ($filter !== null && !$filter($data)) {
                continue;
            }
            $id = $data['id'] ?? basename($file, '.json');
            $result[$id] = $data;
        }
        if ($sort !== null) {
            uasort($result, $sort);
        }
        return $result;
    }

    /**
     * 删除文件
     */
    public static function delete(string $path): bool
    {
        if (is_file($path)) {
            return unlink($path);
        }
        return false;
    }

    /**
     * 递归删除目录
     */
    public static function deleteDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::deleteDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    // ------------------------------------------------------------------
    // 路径辅助
    // ------------------------------------------------------------------

    public static function userPath(int $uid): string
    {
        return self::$root . '/users/' . $uid . '.json';
    }

    public static function forumPath(int $fid): string
    {
        return self::$root . '/forums/' . $fid . '.json';
    }

    public static function overforumPath(int $ofid): string
    {
        return self::$root . '/overforums/' . $ofid . '.json';
    }

    public static function topicPath(int $tid): string
    {
        return self::$root . '/topics/' . $tid . '.json';
    }

    public static function postTxtPath(int $tid, int $pid): string
    {
        return self::$root . '/posts/' . $tid . '/' . $pid . '.txt';
    }

    public static function invitePath(string $hash): string
    {
        return self::$root . '/invites/' . $hash . '.json';
    }

    /**
     * 数据目录是否可写
     */
    public static function dataWritable(): bool
    {
        $dir = self::$root;
        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true) && is_writable($dir);
        }
        return is_writable($dir);
    }
}
