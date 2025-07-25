<?php
/**
 * 抖音无水印视频与图集解析API
 * @Author: JiJiang
 * @Date: 2025年7月25日22:06:18
 * @Tg: @jijiang778
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 统一的API密钥
$TIKHUB_API_KEY = '8JlncGV3wnQemnsW2kTttnE8418qhKD2qYJ0/wjNkD17rHIrYeR5zDjvJg==';

// 缓存配置
$CACHE_DIR = __DIR__ . '/cache';
$CACHE_EXPIRE_TIME = 60; // 1分钟过期

// 确保缓存目录存在
if (!is_dir($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

// 清理过期缓存
cleanExpiredCache();

// 通用CURL请求函数
function curlRequest($url, $headers = [], $postData = null, $isHeadRequest = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($isHeadRequest) {
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    return ['response' => $response, 'info' => $info];
}

// 缓存系统相关函数
function getCacheFilePath($videoId) {
    global $CACHE_DIR;
    return $CACHE_DIR . '/video_' . $videoId . '.cache';
}

function getCache($videoId) {
    global $CACHE_EXPIRE_TIME;
    $cacheFile = getCacheFilePath($videoId);
    
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (isset($cacheData['timestamp']) && (time() - $cacheData['timestamp']) < $CACHE_EXPIRE_TIME) {
            return $cacheData;
        }
    }
    
    return null;
}

function setCache($videoId, $highQualityData, $statistics) {
    $cacheData = [
        'timestamp' => time(),
        'highQualityData' => $highQualityData,
        'statistics' => $statistics
    ];
    
    file_put_contents(getCacheFilePath($videoId), json_encode($cacheData));
}

function cleanExpiredCache() {
    global $CACHE_DIR, $CACHE_EXPIRE_TIME;
    
    if (!is_dir($CACHE_DIR)) {
        return;
    }
    
    $files = scandir($CACHE_DIR);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $CACHE_DIR . '/' . $file;
        
        // 只处理缓存文件
        if (!is_file($filePath) || !strpos($file, '.cache')) {
            continue;
        }
        
        // 检查文件修改时间，超过缓存时间则删除
        if ((time() - filemtime($filePath)) > $CACHE_EXPIRE_TIME) {
            @unlink($filePath);
        }
    }
}

// 获取视频最高画质播放链接（原画）- TikHub API
function getHighQualityVideoUrl($videoId) {
    global $TIKHUB_API_KEY;
    
    // 检查缓存
    $cache = getCache($videoId);
    if ($cache && isset($cache['highQualityData'])) {
        return $cache['highQualityData'];
    }
    
    $apiUrl = "https://api.tikhub.io/api/v1/douyin/web/fetch_video_high_quality_play_url?aweme_id={$videoId}";
    
    $result = curlRequest($apiUrl, [
        "Authorization: Bearer {$TIKHUB_API_KEY}",
        'Content-Type: application/json'
    ]);
    
    if ($result['info']['http_code'] == 200) {
        $data = json_decode($result['response'], true);
        if (isset($data['data'])) {
            return $data;
        }
    }
    
    return null;
}

// 获取视频统计数据 - TikHub API
function getVideoStatistics($videoId) {
    global $TIKHUB_API_KEY;
    
    // 检查缓存
    $cache = getCache($videoId);
    if ($cache && isset($cache['statistics'])) {
        return $cache['statistics'];
    }
    
    $apiUrl = "https://api.tikhub.io/api/v1/douyin/app/v3/fetch_video_statistics?aweme_ids={$videoId}";
    
    $result = curlRequest($apiUrl, [
        "Authorization: Bearer {$TIKHUB_API_KEY}",
        'Content-Type: application/json'
    ]);
    
    if ($result['info']['http_code'] == 200) {
        $data = json_decode($result['response'], true);
        
        if (isset($data['data']['statistics_list']) && !empty($data['data']['statistics_list'])) {
            foreach ($data['data']['statistics_list'] as $item) {
                if (isset($item['aweme_id']) && $item['aweme_id'] == $videoId) {
                    return $item;
                }
            }
        }
    }
    
    // 返回默认值
    return [
        'aweme_id' => $videoId,
        'play_count' => 0,
        'digg_count' => 0,
        'comment_count' => 0,
        'share_count' => 0,
        'download_count' => 0
    ];
}

// 格式化文件大小
function formatFileSize($bytes, $precision = 2) {
    if ($bytes == 0) {
        return '0 Bytes';
    }
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// 获取远程文件大小
function getRemoteFileSize($url) {
    $result = curlRequest($url, [], null, true);
    
    $contentLength = $result['info']['download_content_length'];
    if ($contentLength > 0) {
        return formatFileSize($contentLength);
    }
    
    // 如果通过Content-Length获取失败，尝试从header中解析
    if ($result['response']) {
        $headers = [];
        foreach (explode("\n", $result['response']) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        if (isset($headers['Content-Length'])) {
            return formatFileSize((int)$headers['Content-Length']);
        }
    }
    
    return '';
}

// 用cURL跟随跳转获取最终URL
function getDyFinalUrl($url) {
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.61(0x18003d28) NetType/WIFI Language/zh_CN';
    
    $result = curlRequest($url, ['User-Agent: ' . $userAgent], null, true);
    return $result['info']['url'] ?: $url;
}

// 提取抖音视频ID
function extractDyId($shareUrl) {
    $finalUrl = getDyFinalUrl($shareUrl);
    preg_match('/(?<=video\/)[0-9]+|[0-9]{10,}/', $finalUrl, $match);
    return $match[0] ?? null;
}

// 获取原画视频大小
function getOriginalVideoSize($highQualityData, $item, $originalVideoUrl) {
    $originalSize = '';
    
    // 尝试多种方式获取原画大小
    if (isset($highQualityData['data']['original_size'])) {
        $originalSize = formatFileSize($highQualityData['data']['original_size']);
    } elseif (isset($highQualityData['data']['video_data']['aweme_detail']['video']['play_addr']['data_size'])) {
        $originalSize = formatFileSize($highQualityData['data']['video_data']['aweme_detail']['video']['play_addr']['data_size']);
    } elseif (isset($highQualityData['data']['video_data']['aweme_detail']['video']['play_addr']['size'])) {
        $originalSize = formatFileSize($highQualityData['data']['video_data']['aweme_detail']['video']['play_addr']['size']);
    } elseif (isset($highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'][0]['play_addr']['data_size'])) {
        $originalSize = formatFileSize($highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'][0]['play_addr']['data_size']);
    } elseif (isset($highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'][0]['play_addr']['size'])) {
        $originalSize = formatFileSize($highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'][0]['play_addr']['size']);
    } elseif (isset($item['video']['play_addr']['data_size'])) {
        $originalSize = formatFileSize($item['video']['play_addr']['data_size']);
    } elseif (isset($item['video']['play_addr']['size'])) {
        $originalSize = formatFileSize($item['video']['play_addr']['size']);
    } else {
        // 尝试通过HTTP HEAD请求获取文件大小
        $originalSize = getRemoteFileSize($originalVideoUrl);
    }
    
    // 如果仍然无法获取大小，设置一个默认值
    if (empty($originalSize)) {
        $originalSize = '未知大小';
    }
    
    return $originalSize;
}

// 获取视频清晰度列表
function getVideoQualityList($highQualityData, $item, $videoId, $originalVideoUrl) {
    $videoList = [];
    $statistics = getVideoStatistics($videoId);
    $playCount = isset($statistics['play_count']) ? number_format($statistics['play_count']) : '未知';
    
    // 添加提示信息
    $videoList[] = [
        'url' => 'javascript:void(0)',
        'level' => "仅供学习测试用，请勿违法搭建"
    ];
    
    $videoList[] = [
        'url' => 'javascript:void(0)',
        'level' => "当前作品的播放量为：{$playCount}"
    ];
    
    // 添加原画选项
    if ($originalVideoUrl) {
        $originalSize = getOriginalVideoSize($highQualityData, $item, $originalVideoUrl);
        
        $videoList[] = [
            'url' => $originalVideoUrl,
            'level' => "【原画】" . ($originalSize ? " - {$originalSize}" : "")
        ];
    }
    
    // 创建清晰度映射，避免重复
    $resolutionMap = [];
    $addedCount = 0;
    $neededResolutions = ["【1080P】", "【720P】"];
    
    // 尝试从video_data.aweme_detail.video.bit_rate获取不同清晰度的视频
    if (isset($highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'])) {
        $bitRates = $highQualityData['data']['video_data']['aweme_detail']['video']['bit_rate'];
        
        foreach ($bitRates as $bitRate) {
            if ($addedCount >= 2) break;
            
            if (isset($bitRate['play_addr']['url_list'][0])) {
                $url = $bitRate['play_addr']['url_list'][0];
                
                // 获取视频大小
                $size = '';
                if (isset($bitRate['play_addr']['data_size'])) {
                    $size = formatFileSize($bitRate['play_addr']['data_size']);
                } elseif (isset($bitRate['play_addr']['size'])) {
                    $size = formatFileSize($bitRate['play_addr']['size']);
                }
                
                // 确定清晰度
                $resolution = null;
                if (isset($bitRate['gear_name'])) {
                    if (strpos($bitRate['gear_name'], '1080') !== false) {
                        $resolution = "【1080P】";
                    } elseif (strpos($bitRate['gear_name'], '720') !== false) {
                        $resolution = "【720P】";
                    }
                }
                
                // 添加清晰度
                if ($resolution && in_array($resolution, $neededResolutions) && !isset($resolutionMap[$resolution])) {
                    $resolutionMap[$resolution] = true;
                    $videoList[] = [
                        'url' => $url,
                        'level' => $resolution . ($size ? " - {$size}" : "")
                    ];
                    $addedCount++;
                }
            }
        }
    }
    
    // 如果清晰度不足，尝试从play_url获取
    if ($addedCount < 2 && isset($highQualityData['data']['play_url']['url_list'])) {
        $playUrls = $highQualityData['data']['play_url']['url_list'];
        
        // 获取视频大小
        $playUrlSize = '';
        if (isset($highQualityData['data']['play_url']['data_size'])) {
            $playUrlSize = formatFileSize($highQualityData['data']['play_url']['data_size']);
        } elseif (isset($highQualityData['data']['play_url']['size'])) {
            $playUrlSize = formatFileSize($highQualityData['data']['play_url']['size']);
        }
        
        // 添加1080P
        if (!isset($resolutionMap["【1080P】"]) && count($playUrls) >= 1) {
            $videoList[] = [
                'url' => $playUrls[0],
                'level' => "【1080P】" . ($playUrlSize ? " - {$playUrlSize}" : "")
            ];
            $resolutionMap["【1080P】"] = true;
            $addedCount++;
        }
        
        // 添加720P
        if ($addedCount < 2 && !isset($resolutionMap["【720P】"]) && count($playUrls) >= 2) {
            $videoList[] = [
                'url' => $playUrls[1],
                'level' => "【720P】" . ($playUrlSize ? " - {$playUrlSize}" : "")
            ];
        }
    }
    
    // 如果仍然清晰度不足，使用抖音API的清晰度作为备用
    if (count($videoList) <= 3 && isset($item['video']['bit_rate'])) {
        $addedCount = 0;
        foreach ($item['video']['bit_rate'] as $index => $bitRate) {
            if ($addedCount >= 2) break;
            
            if (isset($bitRate['play_addr']['url_list'][0])) {
                $url = $bitRate['play_addr']['url_list'][0];
                
                // 获取视频大小
                $size = '';
                if (isset($bitRate['play_addr']['data_size'])) {
                    $size = formatFileSize($bitRate['play_addr']['data_size']);
                } elseif (isset($bitRate['play_addr']['size'])) {
                    $size = formatFileSize($bitRate['play_addr']['size']);
                }
                
                // 确定清晰度
                $quality = null;
                if ($index == 0 && !isset($resolutionMap["【1080P】"])) {
                    $quality = "【1080P】";
                } elseif ($index == 1 && !isset($resolutionMap["【720P】"])) {
                    $quality = "【720P】";
                }
                
                // 添加清晰度
                if ($quality) {
                    $resolutionMap[$quality] = true;
                    $videoList[] = [
                        'url' => $url,
                        'level' => $quality . ($size ? " - {$size}" : "")
                    ];
                    $addedCount++;
                }
            }
        }
    }
    
    return ['videoList' => $videoList, 'statistics' => $statistics];
}

// 统一响应格式
function douyinResponse($code = 200, $msg = '解析成功', $data = []) {
    return [
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ];
}

// 主解析入口
function parseDouyinContent($inputUrl) {
    $videoId = extractDyId($inputUrl);
    if (!$videoId) {
        return douyinResponse(201, '未能提取到视频ID');
    }
    
    // 获取抖音页面内容
    $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.61(0x18003d28) NetType/WIFI Language/zh_CN';
    $headers = [
        'User-Agent: ' . $userAgent,
        'Referer: https://www.douyin.com/',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
    ];
    
    $result = curlRequest('https://www.iesdouyin.com/share/video/' . $videoId, $headers);
    $html = $result['response'];
    
    if (!$html) {
        return douyinResponse(201, '请求抖音页面失败');
    }
    
    // 匹配页面中的JSON数据
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
    
    // 构建基本返回数据
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
            'url' => $item['music']['play_url']['url_list'][0] ?? ''
        ],
        'aweme_id' => $videoId
    ];
    
    // 如果是视频（非图集），获取高清链接和统计数据
    if (count($imageList) == 0) {
        // 获取高质量视频数据和统计数据
        $highQualityData = getHighQualityVideoUrl($videoId);
        $originalVideoUrl = isset($highQualityData['data']['original_video_url']) ? $highQualityData['data']['original_video_url'] : '';
        
        // 获取视频清晰度列表和统计数据
        $videoData = getVideoQualityList($highQualityData, $item, $videoId, $originalVideoUrl);
        $videoList = $videoData['videoList'];
        $statistics = $videoData['statistics'];
        
        // 更新缓存
        setCache($videoId, $highQualityData, $statistics);
        
        // 添加统计数据
        $result['play_count'] = $statistics['play_count'] ?? 0;
        $result['digg_count'] = $statistics['digg_count'] ?? 0;
        $result['comment_count'] = $statistics['comment_count'] ?? 0;
        $result['share_count'] = $statistics['share_count'] ?? 0;
        $result['download_count'] = $statistics['download_count'] ?? 0;
        
        // 添加视频列表
        if (!empty($videoList)) {
            $result['video_list'] = $videoList;
        }
        
        // 使用原画链接作为主URL
        if ($originalVideoUrl) {
            $result['url'] = $originalVideoUrl;
        }
    }
    
    return douyinResponse(200, '解析成功', $result);
}

// 入口参数校验与响应
$inputUrl = $_GET['url'] ?? '';
if (empty($inputUrl)) {
    echo json_encode(douyinResponse(400, '缺少url参数'), JSON_UNESCAPED_UNICODE);
    exit;
}

$result = parseDouyinContent($inputUrl);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>