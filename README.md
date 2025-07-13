# 2025.07.13
## 更新dy`视频`原画（用户上传画质）解析接口

### 本来就不是我的技术 我是靠原本就开源的代码加上AI改写了一下 不喜勿喷

根据 https://api.tikhub.io/ 显示：最高画质的视频链接***无法***从抖音APP或网页版直接获取，需要通过这个平台里面的接口获取
注册链接： https://user.tikhub.io/users/signup?referral_code=gxo42Ba1 
   
通过
```
https://api.tikhub.io/api/v1/douyin/app/v3/fetch_video_high_quality_play_url?aweme_id= 
```
获取的最高画质视频 为0.005u解析一次

通过
```
https://api.tikhub.io/api/v1/douyin/app/v3/fetch_video_statistics?aweme_ids= 
```
获取的播放数 为0.001u获取一次（默认关掉，如有需求再把第12行后面改为true即可）

5u≈35¥可以解析**1000**次，如果需求不大也没有那个必要

如果**有意愿搭建**的，麻烦走一下小弟的邀请码**gxo42Ba1** 谢谢！！

成为付费用户后，双方将各获得**2美元的余额**（约**2000**次请求量）
中国用户可直接使用任意银联信用/储蓄卡。 付款时无需注册 PayPal，请在页面选择「信用卡/借记卡」方式完成支付，或者直接欧易币安之类的app用U支付

~~充值完~~创建完账户完后：
- 在tikhub的api_keys界面创建APIKEY，直接选择所有权限
- 获取到的APIKEY 直接在dy.php里面替换 $tikhub_key 保存
- 访问你的服务器地址/dy.php?url=抖音链接

#### 返回JSON值实例：
``` 
{
    "code": 200,
    "msg": "解析成功",
    "data": {
        "author": "小郭聊数码（接回收置换）",
        "uid": "attybz2007623",
        "avatar": "https://p3.douyinpic.com/aweme/720x720/aweme-avatar/tos-cn-i-0813c001_57c5e32a64ce437588fa7655241d138d.webp?from=327834062",
        "title": "感受一下苹果的非线性动画#15promax #数码科技 #苹果原相机",
        "cover": "https://p3-sign.douyinpic.com/tos-cn-p-0015/oQpDEQjBIPBSai9gJIxXLA4AZwP1xvGVuiQNX~tplv-dy-resize-walign-adapt-aq:720:q75.webp?lk3s=138a59ce&x-expires=1753588800&x-signature=xyDAr2NoKIEIHex7s23KmoV8c3g%3D&from=327834062&s=PackSourceEnum_AWEME_DETAIL&se=false&sc=cover&biz_tag=aweme_video&l=2025071312240405BED8A9E483C1B6B293",
        "url": "https://v5-dy-o-detect.zjcdn.com/5e923a62ab6c006a6190eb18be4d37c9/68734307/video/tos/cn/tos-cn-v-0015c800/oAXQ1SEQDiDgGgI4BjAxuLx9ExiAkNaAAZAPP/?a=1128&ch=0&cr=0&dr=0&cd=0%7C0%7C0%7C0&cv=1&br=21828&bt=21828&ds=4&ft=BoM6Dn26sQOCCp5Sup-Q6_Mme-rLWa5eFoQVzSd-IebhjqkwUBMEq&mime_type=video_mp4&qs=13&rc=M2pmcGs5cjZmeTMzNGkzM0BpM2pmcGs5cjZmeTMzNGkzM0BwbmhfMmQ0YDZgLS1kLS9zYSNwbmhfMmQ0YDZgLS1kLS9zcw%3D%3D&btag=c0010e00050000&cquery=100y&dy_q=1752380644&l=202507131224043DBB96A7C80BF2ADAE53",
        "music": {
            "title": "@离别信创作的原声",
            "author": "离别信",
            "url": "https://sf5-hl-cdn-tos.douyinstatic.com/obj/ies-music/7452650418548542227.mp3"
        },
        "statistics": {
            "digg_count": 16816,
            "comment_count": 748,
            "share_count": 1709,
            "play_count": 406468
        },
        "aweme_id": "7481260613856005412"
    }
}

```
			

代码很乱 纯AI零手工 依托答辩但能用
### 最后谢谢各位star 欢迎大家fork！


 
