<?php
/**
 * 小红书无水印视频与图集解析API
 * @Author: JiJiang
 * @Date: 2025年7月9日14:18:28
 * @Tg: @jijiang778
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// 统一响应输出
function xhsJsonResponse($code, $msg, $data = []) {
    return json_encode([
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ], 480);
}

// 获取请求参数
function getXhsInputUrl() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fullUrl = $_SERVER['REQUEST_URI'];
        $urlPos = strpos($fullUrl, 'url=');
        if ($urlPos !== false) {
            $encoded = substr($fullUrl, $urlPos + 4);
            return urldecode($encoded) ?: null;
        }
    } else {
        return $_POST['url'] ?? null;
    }
    return null;
}

// 用cURL跟随重定向获取最终URL
function getFinalUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return $finalUrl ?: $url;
}

// 主解析入口
function parseXhsContent($inputUrl) {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36';
    $cookie = '你的小红书cookie';
    $domain = parse_url($inputUrl);
    if ($domain && isset($domain['host']) && $domain['host'] === 'xhs.com') {
        $parts = explode('/', $inputUrl);
        $inputUrl = 'http://xhslink.com/a/' . ($parts[4] ?? '');
        $domain = parse_url($inputUrl); // 重新解析
    }
    // 新增：自动处理xhslink.com短链跳转
    if ($domain && isset($domain['host']) && strpos($domain['host'], 'xhslink.com') !== false) {
        $inputUrl = getFinalUrl($inputUrl);
        $domain = parse_url($inputUrl); // 重新解析
    }
    if ($domain && isset($domain['host']) && $domain['host'] !== 'www.xiaohongshu.com') {
        $inputUrl = getFinalUrl($inputUrl);
    }
    $noteId = xhsExtractNoteId($inputUrl);
    $html = xhsCurlGet($inputUrl, $cookie, $ua);
    // 调试：保存页面内容
    file_put_contents('xhs_debug.html', $html);
    if (!$html) {
        return xhsJsonResponse(201, '页面请求失败');
    }
    $pattern = '/<script>\s*window.__INITIAL_STATE__\s*=\s*({[\s\S]*?})<\/script>/is';
    if (!preg_match($pattern, $html, $match)) {
        return xhsJsonResponse(201, '未匹配到页面数据');
    }
    $jsonStr = str_replace('undefined', 'null', $match[1]);
    $dataArr = json_decode($jsonStr, true);
    if (!$dataArr) {
        return xhsJsonResponse(201, '页面数据不是有效JSON');
    }
    // 视频直链
    $videoH264 = xhsSafeArr($dataArr, ['note', 'noteDetailMap', $noteId, 'note', 'video', 'media', 'stream', 'h264', 0, 'backupUrls', 0]);
    $videoH265 = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'video', 'media', 'stream', 'h265', 0, 'masterUrl']);
    $videoUrl = $videoH265 ?: $videoH264;
    // 图文数据
    $imgData = xhsSafeArr($dataArr, ['note', 'noteDetailMap', $noteId, 'note']);
    // 作者信息
    $author = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'user', 'nickName']) ?: xhsSafeArr($imgData, ['user', 'nickname'], '');
    $authorId = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'user', 'userId']) ?: xhsSafeArr($imgData, ['user', 'userId'], '');
    $title = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'title']) ?: xhsSafeArr($imgData, ['title'], '');
    $desc = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'desc']) ?: xhsSafeArr($imgData, ['desc']) ?: xhsSafeArr($dataArr, ['note', 'noteDetailMap', $noteId, 'note'], '');
    $avatar = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'user', 'avatar']) ?: xhsSafeArr($imgData, ['user', 'avatar'], '');
    $cover = xhsSafeArr($dataArr, ['noteData', 'data', 'noteData', 'imageList', 0, 'url']) ?: xhsSafeArr($dataArr, ['note', 'noteDetailMap', $noteId, 'note', 'imageList', 0, 'urlDefault'], '');
    if (!empty($videoUrl)) {
        $result = [
            'author' => $author,
            'authorID' => $authorId,
            'title' => $title,
            'desc' => $desc,
            'avatar' => $avatar,
            'cover' => $cover,
            'url' => $videoUrl
        ];
        return xhsJsonResponse(200, '解析成功', $result);
    } elseif (!empty($imgData) && isset($imgData['imageList'])) {
        $imgArr = [];
        foreach ($imgData['imageList'] as $item) {
            if (isset($item['urlDefault'])) {
                $imgArr[] = $item['urlDefault'];
            }
        }
        $result = [
            'author' => $author,
            'authorID' => $authorId,
            'title' => $title,
            'desc' => $desc,
            'avatar' => $avatar,
            'cover' => $cover,
            'imgurl' => $imgArr
        ];
        return xhsJsonResponse(200, '解析成功', $result);
    } else {
        return xhsJsonResponse(201, '未获取到视频或图片数据');
    }
}

// 提取小红书笔记ID
function xhsExtractNoteId($url) {
    $patterns = [
        '/discovery\/item\/([a-zA-Z0-9]+)/',
        '/explore\/([a-zA-Z0-9]+)/',
        '/item\/([a-zA-Z0-9]+)/',
        '/note\/([a-zA-Z0-9]+)/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $m) && !empty($m[1])) {
            return $m[1];
        }
    }
    return null;
}

// 通用CURL请求
function xhsCurlGet($url, $cookie, $ua) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://www.xiaohongshu.com/',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Origin: https://www.xiaohongshu.com'
    ]);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// 安全多维数组取值
function xhsSafeArr(array $arr, array $keys, $default = null) {
    $cur = $arr;
    foreach ($keys as $k) {
        if (!isset($cur[$k])) return $default;
        $cur = $cur[$k];
    }
    return $cur;
}

// 入口参数校验与响应
$inputUrl = getXhsInputUrl();
if (empty($inputUrl)) {
    echo xhsJsonResponse(0, '缺少url参数');
    exit;
}
echo parseXhsContent($inputUrl);
