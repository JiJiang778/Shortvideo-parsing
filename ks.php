<?php
/**
 * 快手无水印视频与图集解析API
 * @Author: JiJiang
 * @Date: 2025年7月9日14:18:28
 * @Tg: @jijiang778
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 统一响应格式
function ksResponse($code = 200, $msg = '解析成功', $data = []) {
    return [
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ];
}

// 主解析入口
function parseKuaishouMedia($inputUrl) {
    $httpHeaders = [
        'Cookie:你的快手cookie ',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
    ];
    $finalUrl = ksGetFinalUrl($inputUrl);
    $shortPattern = '/short-video\/([^?]+)/';
    $photoPattern = '/photo\/([^?]+)/';
    $rawHtml = '';
    $mediaId = '';
    if (preg_match($shortPattern, $finalUrl, $match)) {
        $mediaId = $match[1];
        $rawHtml = ksCurlRequest($inputUrl, $httpHeaders);
        while ($rawHtml === null) {
            $rawHtml = ksCurlRequest($inputUrl, $httpHeaders);
        }
    } elseif (preg_match($photoPattern, $finalUrl, $match)) {
        $mediaId = $match[1];
        $rawHtml = ksCurlRequest("https://www.kuaishou.com/short-video/{$mediaId}", $httpHeaders);
        while ($rawHtml === null) {
            $rawHtml = ksCurlRequest("https://www.kuaishou.com/short-video/{$mediaId}", $httpHeaders);
        }
    }
    if ($rawHtml) {
        $apolloPattern = '/window\.__APOLLO_STATE__\s*=\s*(.*?)<\/script>/s';
        if (preg_match($apolloPattern, $rawHtml, $jsonMatch)) {
            $funcPattern = '/function\s*\([^)]*\)\s*{[^}]*}/';
            $cleanApollo = preg_replace($funcPattern, ':', $jsonMatch[1]);
            $cleanApollo = preg_replace('/,\s*(?=}|])/', '', $cleanApollo);
            $cleanApollo = str_replace(';(:());', '', $cleanApollo);
            $apolloArr = json_decode($cleanApollo, true);
            $mediaData = $apolloArr['defaultClient'] ?? null;
            $videoUrl = '';
            if (!empty($mediaData)) {
                $key = "VisionVideoDetailPhoto:{$mediaId}";
                $item = $mediaData[$key] ?? null;
                if ($item) {
                    $videoUrl = $item['photoUrl'] ?? '';
                }
            } else {
                return ksResponse(201, '未能解析到视频数据');
            }
        }
        if (!empty($videoUrl)) {
            $result = [
                'title' => $item['caption'] ?? '',
                'cover' => $item['coverUrl'] ?? '',
                'url' => $videoUrl,
            ];
            return ksResponse(200, '解析成功', $result);
        } else {
            return ksResponse(201, '未获取到视频直链');
        }
    } else {
        return ksResponse(201, '页面请求失败');
    }
}

// 获取重定向后的最终URL
function ksGetFinalUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return $url;
    }
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return $finalUrl;
}

// 通用CURL请求
function ksCurlRequest($url, $headers = null, $postData = null) {
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
    echo json_encode(ksResponse(0, '缺少url参数'), 480);
    exit;
}
$mediaInfo = parseKuaishouMedia($inputUrl);
echo json_encode($mediaInfo, 480);
