<?php

namespace app\base\service;

use think\Log;

class WxPay
{
    public function __construct($w = null)
    {
        $this->appinfo = Common::getAppinfo($w);
    }

    /**
     * 返回给客服端预支付信息
     * @param array $res 
     */
    public function returnFront($res)
    {
        if (isset($res['result_code']) && $res['result_code'] == 'SUCCESS') {
            if ($res['trade_type'] == 'JSAPI') {
                // JSAPI支付
                $returnData = [
                    'appId' => $this->appinfo['appid'],
                    'timeStamp' => (string) time(),
                    'nonceStr' => $res['nonce_str'],
                    'package' => 'prepay_id=' . $res['prepay_id'],
                    'signType' => 'MD5',
                ];
                $returnData['paySign'] = $this->makeSign($returnData);
            }

            Common::res(['data' => $returnData]);
        } else {
            // 支付异常
            Common::res(['code' => 600, 'data' => $res]);
        }
    }

    /**获取签名 */
    public function makeSign($params)
    {
        ksort($params);
        foreach ($params as $key => $item) {
            if (!empty($item)) {
                $newArr[] = $key . '=' . $item;
            }
        }
        $stringA = implode("&", $newArr);
        // key是在商户平台API安全里自己设置的
        $payapikey = $this->appinfo['payapikey'];
        $stringSignTemp = $stringA . "&key=" . $payapikey;
        $stringSignTemp = MD5($stringSignTemp);
        $sign = strtoupper($stringSignTemp);
        return $sign;
    }

    /**支付成功返回数据 */
    public function notifyHandle()
    {
        //接收微信返回的数据数据,返回的xml格式
        $xmlData = file_get_contents('php://input');
        $data = Common::fromXml($xmlData);
        $sign = $data['sign'];
        unset($data['sign']); // 剔除sign再校验
        if ($sign == $this->makeSign($data) && $data['result_code'] == 'SUCCESS') {
            // 校验成功，返回data
            return $data;
        } else {
            die();
        }
    }

    /**业务处理成功，告知给微信 */
    public function returnSuccess()
    {
        echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }
}
