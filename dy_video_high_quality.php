<?php
/**
*@Author: JiJiang
*@CreateTime: 2025年7月13日23:21:03
*@tip: 抖音视频图集去水印解析（视频优先最高画质）
*/
header("Access-Control-Allow-Origin: *");
header('Content-type: application/json');
function douyin($url)
{
    // 设置是否使用统计API（开启后每次调用多消耗0.001美元）
    $enable_statistics_api = false; // 默认关闭，如需开启请改为true
    $include_play_count = $enable_statistics_api; // 只有在开启调用fetch_video_statistics接口时才返回视频播放数
    
    // 1. 提取aweme_id
    $id = extractId($url);
    if (empty($id)) {
        return array('code' => 400, 'msg' => '无法解析视频 ID');
    }
    
    // 2. 优先调用TikHub最高画质接口  地址：https://api.tikhub.io/
    //    接口文档：https://docs.tikhub.io/312096107e0 
    //    到官网 https://user.tikhub.io/users/signup?referral_code=gxo42Ba1 自行申请key替换
    $tikhub_key = '你的apikey';
    $tikhub_api = 'https://api.tikhub.io/api/v1/douyin/app/v3/fetch_video_high_quality_play_url?aweme_id=' . $id;
    $tikhub_header = array('Authorization: Bearer ' . $tikhub_key);
    
    $tikhub_response = curl($tikhub_api, $tikhub_header);
    $tikhub_json = json_decode($tikhub_response, true);
    
    // 3. 调用统计数据接口获取统计信息
    $statistics = array(
        'digg_count' => 0,
        'comment_count' => 0,
        'share_count' => 0
    );
    
    // 只有在启用统计API时才添加play_count字段
    if ($include_play_count) {
        $statistics['play_count'] = 0;
    }
    
    if ($enable_statistics_api) {
        $statistics_api = 'https://api.tikhub.io/api/v1/douyin/app/v3/fetch_video_statistics?aweme_ids=' . $id;
        $statistics_response = curl($statistics_api, $tikhub_header);
        $statistics_json = json_decode($statistics_response, true);
        
        // 从统计数据API中提取数据
        if (isset($statistics_json['code']) && $statistics_json['code'] == 200) {
            if (isset($statistics_json['data']['statistics_list']) && is_array($statistics_json['data']['statistics_list'])) {
                foreach ($statistics_json['data']['statistics_list'] as $stat_item) {
                    if (isset($stat_item['aweme_id']) && $stat_item['aweme_id'] == $id) {
                        // 获取播放数
                        if (isset($stat_item['play_count'])) {
                            $statistics['play_count'] = intval($stat_item['play_count']);
                        }
                        
                        // 获取点赞数
                        if (isset($stat_item['digg_count'])) {
                            $statistics['digg_count'] = intval($stat_item['digg_count']);
                        }
                        
                        // 获取分享数
                        if (isset($stat_item['share_count'])) {
                            $statistics['share_count'] = intval($stat_item['share_count']);
                        }
                        
                        break;
                    }
                }
            }
        }
    }
    
    // 检查TikHub API返回结果
    if (isset($tikhub_json['code']) && $tikhub_json['code'] == 200 && isset($tikhub_json['data']['original_video_url']) && !empty($tikhub_json['data']['original_video_url'])) {
        // 视频，精简返回数据
        $video_data = $tikhub_json['data']['video_data']['aweme_detail'];
        $original_url = $tikhub_json['data']['original_video_url'];
        
        // 从第一个API(fetch_video_high_quality_play_url)获取分享数、评论数和点赞数
        if (isset($video_data['statistics'])) {
            $statistics['share_count'] = isset($video_data['statistics']['share_count']) ? intval($video_data['statistics']['share_count']) : 0;
            $statistics['comment_count'] = isset($video_data['statistics']['comment_count']) ? intval($video_data['statistics']['comment_count']) : 0;
            $statistics['digg_count'] = isset($video_data['statistics']['digg_count']) ? intval($video_data['statistics']['digg_count']) : 0;
        }
        
        // 获取音乐URL
        $music_url = '';
        if (isset($video_data['music']['play_url']['uri'])) {
            $music_url = $video_data['music']['play_url']['uri'];
        } else if (isset($video_data['music']['play_url']['url_list']) && !empty($video_data['music']['play_url']['url_list'])) {
            $music_url = $video_data['music']['play_url']['url_list'][0];
        }
        
        return array(
            'code' => 200,
            'msg' => '解析成功',
            'data' => array(
                'author' => $video_data['author']['nickname'], // 作者昵称
                'uid' => $video_data['author']['unique_id'], // 作者唯一ID
                'avatar' => $video_data['author']['avatar_medium']['url_list'][0], // 作者头像
                'title' => $video_data['desc'], // 视频描述文本
                'cover' => $video_data['video']['cover']['url_list'][0], // 视频封面图
                'url' => $original_url, // 无水印视频地址
                'music' => array(
                    'title' => $video_data['music']['title'], // 背景音乐标题
                    'author' => $video_data['music']['author'], // 背景音乐作者
                    'url' => $music_url // 背景音乐链接
                ),
                'statistics' => $statistics, // 统计数据
                'aweme_id' => $id, // 视频ID
            ),
        );
    }
    
    // 3. 非视频或TikHub失败，走本地解析（原有逻辑）
    $header = array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.61(0x18003d28) NetType/WIFI Language/zh_CN');
    $response = curl('https://www.iesdouyin.com/share/video/' . $id, $header);
    $pattern = '/window\._ROUTER_DATA\s*=\s*(.*?)<\/script>/s';
    
    if (!preg_match($pattern, $response, $matches) || empty($matches[1])) {
        return array('code' => 201, 'msg' => '解析失败');
    }
    
    $videoInfo = json_decode(trim($matches[1]), true);
    if (!isset($videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0])) {
        return array('code' => 201, 'msg' => '解析失败');
    }
    
    $item = $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0];
    $imgurljson = $item['images'] ?? [];
    $imgurl = [];
    
    if (is_array($imgurljson) && !empty($imgurljson)) {
        foreach ($imgurljson as $image) {
            if (isset($image['url_list']) && is_array($image['url_list']) && !empty($image['url_list'])) {
                $imgurl[] = $image['url_list'][0];
            }
        }
    }
    
    // 判断是否为图文还是视频
    $is_image = count($imgurl) > 0;
    
    // 获取视频URL
    $video_url = '';
    if (!$is_image && isset($item['video']['play_addr']['url_list'][0])) {
        $video_url = $item['video']['play_addr']['url_list'][0];
    }
    
    // 获取音乐URL
    $music_url = '';
    if (isset($item['music']['play_url']['uri'])) {
        $music_url = $item['music']['play_url']['uri'];
    } else if (isset($item['music']['play_url']['url_list']) && !empty($item['music']['play_url']['url_list'])) {
        $music_url = $item['music']['play_url']['url_list'][0];
    }
    
    // 获取统计数据（本地解析方式）
    $local_statistics = array(
        'digg_count' => isset($item['statistics']['digg_count']) ? intval($item['statistics']['digg_count']) : 0,
        'comment_count' => isset($item['statistics']['comment_count']) ? intval($item['statistics']['comment_count']) : 0,
        'share_count' => isset($item['statistics']['share_count']) ? intval($item['statistics']['share_count']) : 0
    );
    
    // 只有在启用统计API时才添加play_count字段
    if ($include_play_count) {
        $local_statistics['play_count'] = 0; // 本地解析方式下，如未调用统计API则不提供准确播放量
    }
    
    // 构建返回数据结构
    $result_data = array(
        'author' => $item['author']['nickname'], // 作者昵称
        'uid' => $item['author']['unique_id'], // 作者唯一ID
        'avatar' => $item['author']['avatar_medium']['url_list'][0], // 作者头像
        'title' => $item['desc'], // 视频描述文本
        'cover' => $item['video']['cover']['url_list'][0], // 视频封面图
        'url' => $is_image ? '当前为图文解析，图文数量为:' . count($imgurl) . '张图片' : $video_url, // 视频链接或图集提示
        'music' => array(
            'title' => $item['music']['title'], // 背景音乐标题
            'author' => $item['music']['author'], // 背景音乐作者
            'url' => $music_url // 背景音乐链接
        ),
        'statistics' => $local_statistics, // 统计数据
        'aweme_id' => $id // 视频ID
    );
    
    // 只有在是图集的情况下才添加images字段
    if ($is_image) {
        $result_data['images'] = $imgurl;
    }
    
    return array(
        'code' => 200,
        'msg' => '解析成功',
        'data' => $result_data,
        'type' => $is_image ? 'image' : 'video', // 内容类型：图文或视频
    );
}

function extractId($url)
{
    // 直接提取长链中的aweme_id
    if (preg_match('/video\/([0-9]+)/', $url, $id1)) {
        return $id1[1];
    }
    
    // 若为短链，简化跳转获取真实链接
    $context = stream_context_create([
        'http' => ['method' => 'HEAD', 'follow_location' => 0]
    ]);
    
    $headers = @get_headers($url, 1, $context);
    if ($headers && isset($headers['Location'])) {
        $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        if (preg_match('/video\/([0-9]+)/', $location, $id2)) {
            return $id2[1];
        }
        
        // 兜底：提取所有数字串
        if (preg_match('/([0-9]{8,})/', $location, $id3)) {
            return $id3[1];
        }
    }
    
    return null;
}

function curl($url, $header = null, $data = null)
{
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($con, CURLOPT_AUTOREFERER, 1);
    if (isset($header)) {
        curl_setopt($con, CURLOPT_HTTPHEADER, $header);
    }
    if (isset($data)) {
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    }
    // 优化超时设置
    curl_setopt($con, CURLOPT_TIMEOUT, 15); // 总超时15秒
    curl_setopt($con, CURLOPT_CONNECTTIMEOUT, 5); // 连接超时5秒
    curl_setopt($con, CURLOPT_ENCODING, ''); // 自动处理压缩内容
    $result = curl_exec($con);
    if ($result === false) {
        $error = curl_error($con);
        curl_close($con);
        return false;
    }
    curl_close($con);
    return $result;
}

// 使用空合并运算符检查 url 参数
$url = $_GET['url'] ?? '';
if (empty($url)) {
    echo json_encode(
        ['code' => 201, 'msg' => 'url为空'],//,'Auther' => 'JiJiang', 'Tg' => '@jijiang778'], 
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// 设置执行超时时间
set_time_limit(30);

$response = douyin($url);
echo json_encode(
    $response ?: ['code' => 404, 'msg' => '获取超时'], // 如果解析失败返回超时信息
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES // 确保中文正常显示、格式化JSON、不转义斜杠
);
?>
