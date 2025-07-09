<?php
/**
 * 抖音无水印视频与图集解析API
 * @Author: JiJiang
 * @Date: 2025年7月10日02:51:10 （更新请求头防止dy检测）
 * @Tg: @jijiang778
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 统一响应格式
function douyinResponse($code = 200, $msg = '解析成功', $data = []) {
    return [
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ];
}

// 用cURL跟随跳转获取最终URL
function getDyFinalUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.61(0x18003d28) NetType/WIFI Language/zh_CN');
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return $finalUrl ?: $url;
}

// 主解析入口
function parseDouyinContent($inputUrl) {
    $uaHeaders = [
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.61(0x18003d28) NetType/WIFI Language/zh_CN',
        'Referer: https://www.douyin.com/',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1'
    ];
    $videoId = extractDyId($inputUrl);
    if (!$videoId) {
        return douyinResponse(201, '未能提取到视频ID');
    }
    $html = dyCurlGet('https://www.iesdouyin.com/share/video/' . $videoId, $uaHeaders);
    // 调试：保存页面内容
    file_put_contents('dy_debug.html', $html);
    if (!$html) {
        return douyinResponse(201, '请求抖音页面失败');
    }
    // 匹配页面中的JSON数据（兼容新版 _ROUTER_DATA 和 _RENDER_DATA）
    if (!preg_match('/window\.(?:_ROUTER_DATA|_RENDER_DATA)\s*=\s*(.*?);?\s*<\/script>/s', $html, $jsonMatch)) {
        return douyinResponse(201, '未能解析到视频数据');
    }
    $jsonStr = trim($jsonMatch[1]);
    $dataArr = json_decode($jsonStr, true);
    if (!isset($dataArr['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0])) {
        return douyinResponse(201, '视频数据结构异常');
    }
    $item = $dataArr['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0];
    // 视频直链
    $videoUrl = isset($item['video']['play_addr']['url_list'][0]) ? str_replace('playwm', 'play', $item['video']['play_addr']['url_list'][0]) : '';
    // 图集处理
    $imageList = [];
    if (!empty($item['images']) && is_array($item['images'])) {
        foreach ($item['images'] as $img) {
            if (!empty($img['url_list'][0])) {
                $imageList[] = $img['url_list'][0];
            }
        }
    }
    // 构建返回数据
    $result = [
        'author' => $item['author']['nickname'] ?? '',
        'uid' => $item['author']['unique_id'] ?? '',
        'avatar' => $item['author']['avatar_medium']['url_list'][0] ?? '',
        'like' => $item['statistics']['digg_count'] ?? 0,
        'time' => $item['create_time'] ?? '',
        'title' => $item['desc'] ?? '',
        'cover' => $item['video']['cover']['url_list'][0] ?? '',
        'images' => $imageList,
        'url' => count($imageList) > 0 ? '当前为图文解析，共' . count($imageList) . '张图片' : $videoUrl,
        'music' => [
            'title' => $item['music']['title'] ?? '',
            'author' => $item['music']['author'] ?? '',
            'avatar' => $item['music']['cover_large']['url_list'][0] ?? '',
            'url' => $item['video']['play_addr']['uri'] ?? ''
        ]
    ];
    return douyinResponse(200, '解析成功', $result);
}

// 提取抖音视频ID（支持短链跳转）
function extractDyId($shareUrl) {
    $finalUrl = getDyFinalUrl($shareUrl);
    preg_match('/(?<=video\/)[0-9]+|[0-9]{10,}/', $finalUrl, $match);
    return $match[0] ?? null;
}

// 通用CURL请求
function dyCurlGet($url, $headers = [], $postData = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// 入口参数校验与响应
$inputUrl = $_GET['url'] ?? '';
if (empty($inputUrl)) {
    echo json_encode(douyinResponse(0, '缺少url参数'), 480);
    exit;
}
$result = parseDouyinContent($inputUrl);
echo json_encode($result, 480);
?>
