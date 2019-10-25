<?php

namespace app\base\service;

use app\base\model\Appinfo;

/**服务端wx接口 */
class WxAPI
{

    public function __construct($w = null)
    {
        if (input('platform') == 'MP-QQ') {
            $this->apiHost = 'api.q.qq.com';
            $type = 'qq';
        } else {
            $this->apiHost = 'api.weixin.qq.com';
            $type = 'miniapp';
        }
        if (!$w) $w = $type;
        $this->appinfo = Common::getAppinfo($w);
    }

    /**
     * 小程序登录
     */
    public function code2session($js_code)
    {
        $url = 'https://' . $this->apiHost . '/sns/jscode2session?appid=APPID&secret=SECRET&js_code=JSCODE&grant_type=authorization_code';
        $url = str_replace('APPID', $this->appinfo['appid'], $url);
        $url = str_replace('SECRET', $this->appinfo['appsecret'], $url);
        $url = str_replace('JSCODE', $js_code, $url);

        return Common::request($url, false);
    }

    /**
     * 公众号授权
     * 通过code换取网页授权access_token
     * 该接口可能返回unionid
     */
    public function getAuth($code)
    {
        $url = 'https://' . $this->apiHost . '/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code';

        $url = str_replace('APPID', $this->appinfo['appid'], $url);
        $url = str_replace('SECRET', $this->appinfo['appsecret'], $url);
        $url = str_replace('CODE', $code, $url);

        return Common::request($url, false);
    }

    /**
     * 公众号授权
     * 拉取用户信息
     * @param string $accessToken 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
     * @param string $openid 用户的唯一标识
     */
    public function getUserInfo($accessToken, $openid)
    {
        $url = 'https://' . $this->apiHost . '/sns/userinfo?access_token=ACCESS_TOKEN&openid=OPENID&lang=zh_CN';

        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);
        $url = str_replace('OPENID', $openid, $url);

        return Common::request($url, false);
    }

    /**统一下单API */
    public function unifiedorder($config)
    {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $params = [
            'appid' => $this->appinfo['appid'],
            'mch_id' => $this->appinfo['paymchid'],
            'nonce_str' => Common::getRandCode(), // 随机字符串
            'body' => $config['body'], // 商品描述
            'out_trade_no' => $config['orderId'], // 商户订单号
            'total_fee' => $config['totalFee'] * 100, // 总金额 单位 分
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
            'notify_url' => $config['notifyUrl'],
            'trade_type' => $config['tradeType'],
        ];
        if ($config['tradeType'] == 'JSAPI') {
            // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识
            $params['openid'] = $config['openid'];
        }
        // 签名
        $params['sign'] = (new WxPay($this->appinfo['appid']))->makeSign($params);
        // 发送请求
        $xml = Common::toXml($params);
        $res = Common::request($url, $xml);
        return Common::fromXml($res);
    }

    /**
     * 检查更新accessToken
     * @return string access_token
     */
    public function getAccessToken()
    {
        if (strtotime($this->appinfo['access_token_expire']) - 600 < time()) {
            // 更新accessToken
            $url = 'https://' . $this->apiHost . '/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET';
            $url = str_replace('APPID', $this->appinfo['appid'], $url);
            $url = str_replace('APPSECRET', $this->appinfo['appsecret'], $url);

            $res = Common::request($url, false);
            if (isset($res['access_token'])) {
                // 将新的token保存到数据库
                Appinfo::where(['id' => $this->appinfo['id']])->update([
                    'access_token' => $res['access_token'],
                    'access_token_expire' => date('Y-m-d H:i:s', time() + $res['expires_in']),
                ]);
                return $res['access_token'];
            } else {
                Common::res(['code' => 1, 'data' => $res]);
            }
        } else {
            return $this->appinfo['access_token'];
        }
    }

    /**
     * (主动)发送客服消息
     * customerServiceMessage.send
     * @param string $openid 用户openid
     * @param array $msgType
     * @param array $msgBody
     */
    public function sendCustomerMsg($openid, $msgType, $msgBody)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/custom/send?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);

        $data = [
            'touser' => $openid,
            "msgtype" => $msgType,
            $msgType => $msgBody
        ];

        return Common::request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 新增临时素材
     */
    public function uploadMedia($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/upload?access_token=ACCESS_TOKEN&type=TYPE';
        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);
        $url = str_replace('TYPE', 'image', $url);

        $data = ['media' => new \CURLFile($filePath, false, false)];

        return Common::request($url, $data);
    }

    /**
     * 获取临时素材
     * @param mixed $mediaId 媒体文件ID，即uploadVoice接口返回的serverID
     */
    public function getMedia($mediaId)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/get?access_token=ACCESS_TOKEN&media_id=MEDIA_ID';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);
        $url = str_replace('MEDIA_ID', $mediaId, $url);

        return Common::request($url, false);
    }


    /**
     * 新增其他类型永久素材
     */
    public function addMaterial($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/material/add_material?access_token=ACCESS_TOKEN&type=TYPE';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);
        $url = str_replace('TYPE', 'image', $url);

        $data = ['media' => new \CURLFile($filePath, false, false)];
        return Common::request($url, $data);
    }

    /**使用公众号接口上传图片 */
    public function uploadimg($filePath)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/media/uploadimg?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);

        $data = ['media' => new \CURLFile($filePath, false, false)];
        return Common::request($url, $data);
    }

    /**
     * 获取小程序码，适用于需要的码数量较少的业务场景。
     * @param string $path 扫码进入的小程序页面路径
     */
    public function getwxacode($path = '/pages/index/index')
    {
        $url = 'https://' . $this->apiHost . '/wxa/getwxacode?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);

        $data = ['path' => $path];
        return Common::request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送模板消息
     * method post
     */
    public function sendTemplate($datas)
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/wxopen/template/send?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);

        foreach ($datas as $data) {
            Common::requestAsync($url, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        // return Common::request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 发送模板消息
     * method post
     */
    public function sendTemplateSync()
    {
        $url = 'https://' . $this->apiHost . '/cgi-bin/message/wxopen/template/send?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);


        $data = [
            "touser" => 'oj77y5LIpHuIWUU2kW8BHVP4goPc',
            "template_id" => "T54MtDdRAPe8kNNtt2tQlj7P7ut7yEe-F8-CaMrKcvw",
            "page" => "/pages/index/index",
            "form_id" => "b5664ec137d44c4c8332130d065a4e48",
            "data" => [
                "keyword1" => [
                    "value" => 100 . "元"
                ],
                "keyword2" => [
                    "value" =>  11 . "已成功解锁" . 100 . "元应援金，赶快邀请后援会入驻领取吧，活动进行中，最多可解锁1000元。"
                ]
            ],
            "emphasis_keyword" => "keyword1.DATA"
        ];

        // Common::requestAsync($url, json_encode($data, JSON_UNESCAPED_UNICODE));

        return Common::request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 检查一段文本是否含有违法违规内容。
     * 若接口errcode返回87014(内容含有违法违规内容)
     */
    public function msgCheck($content)
    {
        $url = 'https://' . $this->apiHost . '/wxa/msg_sec_check?access_token=ACCESS_TOKEN';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) return false;
        $url = str_replace('ACCESS_TOKEN', $accessToken, $url);

        $data = [
            'content' => $content
        ];
        return Common::request($url, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
