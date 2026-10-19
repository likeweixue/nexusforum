<?php
/**
 * NexusForum - BBCode 解析器
 *
 * 支持：[b] [i] [u] [s] [url] [url=] [img] [quote] [code]
 *       [color=] [size=] [list] [*]
 * 安全策略：先转义 HTML，再解析 BBCode，输出均为安全标签。
 */

declare(strict_types=1);

class NF_Bbcode
{
    /**
     * 解析 BBCode 为 HTML
     */
    public static function parse(string $text): string
    {
        // 统一换行
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 1. 先整体 HTML 转义（防 XSS）
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 2. 保护 [code] 内容（内部不做其他解析）
        $codeBlocks = [];
        $text = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', function (array $m) use (&$codeBlocks): string {
            $key = "\x00CODE" . count($codeBlocks) . "\x00";
            $codeBlocks[$key] = '<pre class="bbcode-code">' . trim($m[1]) . '</pre>';
            return $key;
        }, $text) ?? $text;

        // 3. 简单标签
        $simple = [
            '/\[b\](.*?)\[\/b\]/is'      => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/is'      => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/is'      => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is'      => '<s>$1</s>',
        ];
        foreach ($simple as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // 4. 带参数标签
        // [url=链接]文字[/url]
        $text = preg_replace_callback('/\[url=([^\]]+)\](.*?)\[\/url\]/is', function (array $m): string {
            $url = self::sanitizeUrl($m[1]);
            $label = $m[2] !== '' ? $m[2] : $m[1];
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a>';
        }, $text) ?? $text;
        // [url]链接[/url]
        $text = preg_replace_callback('/\[url\](.*?)\[\/url\]/is', function (array $m): string {
            $url = self::sanitizeUrl($m[1]);
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>';
        }, $text) ?? $text;
        // [img]地址[/img]
        $text = preg_replace_callback('/\[img\](.*?)\[\/img\]/is', function (array $m): string {
            $url = self::sanitizeUrl($m[1]);
            return '<img src="' . $url . '" alt="图片" class="bbcode-img" loading="lazy">';
        }, $text) ?? $text;
        // [color=red]文字[/color]
        $text = preg_replace_callback('/\[color=([#\w]+)\](.*?)\[\/color\]/is', function (array $m): string {
            $color = preg_match('/^(#[0-9a-f]{3,8}|[a-z]+)$/i', $m[1]) ? $m[1] : 'inherit';
            return '<span style="color:' . $color . '">' . $m[2] . '</span>';
        }, $text) ?? $text;
        // [size=14]文字[/size]
        $text = preg_replace_callback('/\[size=(\d{1,3})\](.*?)\[\/size\]/is', function (array $m): string {
            $size = max(8, min(48, (int) $m[1]));
            return '<span style="font-size:' . $size . 'px">' . $m[2] . '</span>';
        }, $text) ?? $text;

        // 5. 引用（支持 quote=作者）
        $text = preg_replace_callback('/\[quote(?:=([^\]]+))?\](.*?)\[\/quote\]/is', function (array $m): string {
            $author = !empty($m[1]) ? '<div class="bbcode-quote-author">' . $m[1] . ' 写道：</div>' : '';
            return '<blockquote class="bbcode-quote">' . $author . '<div>' . $m[2] . '</div></blockquote>';
        }, $text) ?? $text;

        // 6. 列表
        $text = preg_replace('/\[list\](.*?)\[\/list\]/is', '<ul class="bbcode-list">$1</ul>', $text) ?? $text;
        $text = preg_replace('/\[\*\](.*?)(?=\n|\[\*\]|<\/ul>|$)/is', '<li>$1</li>', $text) ?? $text;

        // 7. 还原 code 块
        foreach ($codeBlocks as $key => $html) {
            $text = str_replace($key, $html, $text);
        }

        // 8. 段落与换行
        $text = nl2br($text);

        return $text;
    }

    /**
     * URL 安全过滤：仅允许 http/https，防止 javascript: 等协议注入
     */
    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        // 已转义过的 &amp; 还原判断
        $check = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('#^(https?:)//#i', $check)) {
            return $url;
        }
        if (preg_match('#^(/|\./|\.\./|\?|#)#', $check)) {
            return $url;
        }
        // 裸域名补 https://
        if (preg_match('#^[\w.-]+\.[a-z]{2,}(/.*)?$#i', $check)) {
            return 'https://' . $url;
        }
        return '#';
    }

    /**
     * 从 BBCode 提取纯文本（用于摘要）
     */
    public static function toPlainText(string $text, int $limit = 120): string
    {
        $text = preg_replace('/\[[^\]]+\]/', '', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return nf_truncate(trim($text), $limit);
    }
}
