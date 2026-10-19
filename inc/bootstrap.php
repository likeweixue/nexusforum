<?php
/**
 * NexusForum - 基于 NexusPHP 论坛模块提取的独立论坛系统
 * 零依赖 / 纯文件存储 / 保留 PT 邀请制度
 *
 * 本文件为系统引导文件：常量定义、配置加载、会话启动、自动加载。
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// 路径常量
// ---------------------------------------------------------------
define('NF_ROOT', dirname(__DIR__));
define('NF_INC', NF_ROOT . '/inc');
define('NF_TPL', NF_ROOT . '/tpl');
define('NF_CONF', NF_ROOT . '/conf');
define('NF_DATA', NF_ROOT . '/data');
define('NF_ASSETS', NF_ROOT . '/assets');
define('NF_VERSION', '1.0.0');

// ---------------------------------------------------------------
// 时区
// ---------------------------------------------------------------
date_default_timezone_set('Asia/Shanghai');

// ---------------------------------------------------------------
// 错误处理：生产环境不暴露细节
// ---------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ---------------------------------------------------------------
// 会话（安全参数）
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_name('NEXUSFORUM_SID');
    session_start();
}

// ---------------------------------------------------------------
// 配置加载
// ---------------------------------------------------------------
function nf_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $file = NF_CONF . '/config.php';
    if (!is_file($file)) {
        $config = [];
        return $config;
    }
    $config = require $file;
    if (!is_array($config)) {
        $config = [];
    }
    // 时区以配置为准
    if (!empty($config['timezone'])) {
        date_default_timezone_set($config['timezone']);
    }
    return $config;
}

function nf_config_get(string $key, $default = null)
{
    $config = nf_config();
    return $config[$key] ?? $default;
}

function nf_installed(): bool
{
    return (bool) nf_config_get('installed', false);
}

// ---------------------------------------------------------------
// 自动加载 inc/ 下的类文件
// ---------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    if (strpos($class, 'NF_') !== 0) {
        return;
    }
    $file = NF_INC . '/' . strtolower(substr($class, 3)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------
// 致命错误捕获
// ---------------------------------------------------------------
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        // 记录日志
        $log = sprintf(
            "[%s] %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        @file_put_contents(NF_DATA . '/error.log', $log, FILE_APPEND | LOCK_EX);
        if (nf_installed()) {
            echo '<h1>500 - 服务器内部错误</h1><p>请稍后再试或联系管理员。</p>';
        }
    }
});
