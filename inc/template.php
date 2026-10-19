<?php
/**
 * NexusForum - 极简模板引擎
 *
 * 用法：
 *   nf_render('topic', ['topic' => $topic, ...]);
 * 模板文件位于 tpl/ 目录，以 .php 结尾，可直接使用传入的变量。
 */

declare(strict_types=1);

/**
 * 渲染模板并输出
 *
 * @param string $tpl   模板名（不含扩展名）
 * @param array  $vars  模板变量
 */
function nf_render(string $tpl, array $vars = []): void
{
    $file = NF_TPL . '/' . $tpl . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo '<h1>模板不存在：' . h($tpl) . '</h1>';
        return;
    }
    extract($vars, EXTR_SKIP);
    require $file;
}

/**
 * 渲染模板为字符串
 */
function nf_render_string(string $tpl, array $vars = []): string
{
    ob_start();
    nf_render($tpl, $vars);
    return (string) ob_get_clean();
}
