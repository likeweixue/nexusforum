<?php
/**
 * NexusForum - 通用工具函数
 */

declare(strict_types=1);

/**
 * HTML 转义输出
 */
function h(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 跳转
 */
function nf_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * 当前请求的完整 URL（用于登录后回跳）
 */
function nf_current_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? 'index.php';
    return $uri;
}

/**
 * 站点基础 URL（如 https://bbs.example.com，末尾不带斜杠）
 */
function nf_base_url(): string
{
    $base = nf_config_get('base_url', '');
    if ($base !== '') {
        return rtrim($base, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // 去掉 index.php 之后的路径
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . $host . $dir;
}

/**
 * 生成站内链接
 */
function nf_url(string $path = ''): string
{
    return nf_base_url() . '/' . ltrim($path, '/');
}

/**
 * CSRF 令牌
 */
function nf_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function nf_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(nf_csrf_token()) . '">';
}

function nf_csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 时间格式化（NexusPHP 风格：2026-10-20 15:30）
 */
function nf_time(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    return date('Y-m-d H:i', $ts);
}

/**
 * 相对时间（几分钟前 / 几小时前 / 几天前）
 */
function nf_time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return intdiv($diff, 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return intdiv($diff, 3600) . ' 小时前';
    }
    if ($diff < 2592000) {
        return intdiv($diff, 86400) . ' 天前';
    }
    return nf_time($datetime);
}

/**
 * 数字千分位
 */
function nf_number(int $n): string
{
    return number_format($n);
}

/**
 * 分页 HTML
 *
 * @param int $total    总记录数
 * @param int $page     当前页（从 1 开始）
 * @param int $perPage  每页条数
 * @param string $urlPattern 链接模板，用 {page} 占位
 */
function nf_pagination(int $total, int $page, int $perPage, string $urlPattern): string
{
    if ($total <= 0) {
        return '';
    }
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $page = max(1, min($page, $pages));
    $html = '<div class="pagination">';
    if ($page > 1) {
        $html .= '<a href="' . h(str_replace('{page}', (string) ($page - 1), $urlPattern)) . '">&laquo; 上一页</a>';
    }
    // 显示页码窗口
    $start = max(1, $page - 3);
    $end = min($pages, $page + 3);
    if ($start > 1) {
        $html .= '<a href="' . h(str_replace('{page}', '1', $urlPattern)) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="ellipsis">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $html .= '<a href="' . h(str_replace('{page}', (string) $i, $urlPattern)) . '">' . $i . '</a>';
        }
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<span class="ellipsis">…</span>';
        }
        $html .= '<a href="' . h(str_replace('{page}', (string) $pages, $urlPattern)) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        $html .= '<a href="' . h(str_replace('{page}', (string) ($page + 1), $urlPattern)) . '">下一页 &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 客户端 IP
 */
function nf_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 安全的随机邀请码（去掉易混淆字符）
 */
function nf_random_code(int $length = 16): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($chars) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

/**
 * 截断字符串
 */
function nf_truncate(string $str, int $len = 60): string
{
    if (mb_strlen($str) <= $len) {
        return $str;
    }
    return mb_substr($str, 0, $len) . '…';
}

/**
 * 保存配置到 conf/config.php
 */
function nf_save_config(array $config): bool
{
    $config['installed'] = true;
    if (empty($config['install_time'])) {
        $config['install_time'] = date('Y-m-d H:i:s');
    }
    $php = "<?php\n\n/**\n * NexusForum 站点配置\n * 由安装程序生成，请勿手工改动格式。\n */\n\nreturn " . var_export($config, true) . ";\n";
    return file_put_contents(NF_CONF . '/config.php', $php) !== false;
}

/**
 * 闪存消息（一次性提示）
 */
function nf_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function nf_flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $msg) {
        $html .= '<div class="flash flash-' . h($msg['type']) . '">' . h($msg['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}
