<?php
/**
 * 抖音无水印视频与图集解析API
 * @Author: JiJiang
 * @Date: 2025年7月9日14:18:28
 * @Tg: @jijiang778
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// 统一响应格式
function dyResponse($code = 200, $msg = '解析成功', $data = []) {
    return [
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ];
}

// 主入口函数，处理请求
function parseDouyinMedia($inputUrl)
{
    $uaHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
    ];
    $videoId = getDouyinId($inputUrl);
    if (!$videoId) {
        return dyResponse(201, '未能提取到视频ID');
    }
    $html = httpRequest('https://www.iesdouyin.com/share/video/' . $videoId, $uaHeaders);
    if (!$html) {
        return dyResponse(201, '请求抖音页面失败');
    }
    // 匹配页面中的JSON数据
    if (!preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)<\/script>/s', $html, $jsonMatch)) {
        return dyResponse(201, '未能解析到视频数据');
    }
    $jsonStr = trim($jsonMatch[1]);
    $dataArr = json_decode($jsonStr, true);
    if (!isset($dataArr['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0])) {
        return dyResponse(201, '视频数据结构异常');
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
    return dyResponse(200, '解析成功', $result);
}

// 提取抖音视频ID（支持短链跳转）
function getDouyinId($shareUrl)
{
    $headers = @get_headers($shareUrl, true);
    if ($headers === false) {
        $finalUrl = $shareUrl;
    } else {
        if (isset($headers['Location'])) {
            $finalUrl = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        } else {
            $finalUrl = $shareUrl;
        }
    }
    if (!is_string($finalUrl)) {
        $finalUrl = strval($finalUrl);
    }
    preg_match('/(?<=video\/)[0-9]+|[0-9]{10,}/', $finalUrl, $match);
    return $match[0] ?? null;
}

// 通用HTTP请求封装（支持GET/POST）
function httpRequest($url, $headers = [], $postData = null)
{
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
    if ($output === false) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return $output;
}

// 入口参数校验与响应
$inputUrl = $_GET['url'] ?? '';
if (empty($inputUrl)) {
    echo json_encode(dyResponse(0, '缺少url参数'), 480);
    exit;
}
$result = parseDouyinMedia($inputUrl);
echo json_encode($result, 480);
?>
