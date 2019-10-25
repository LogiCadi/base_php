<?php

namespace app\base\service;

use app\api\model\Cfg;
use app\base\model\Appinfo;
use think\Db;

class Common
{
    /**接口数据返回 */
    public static function res($res = [])
    {
        if (isset($res['code'])) {
            $return['code'] = $res['code'];
        } else {
            $return['code'] = 0;
        }
        if (isset($res['msg'])) {
            $return['msg'] = $res['msg'];
        } else {
            $return['msg'] = CodeSign::getMsg($return['code']);
        }

        if (isset($res['data'])) {
            $return['data'] = $res['data'];
        } else {
            $return['data'] = [];
        }

        Db::rollback(); // 怀疑可能出现开启事务后不提交也不回滚的情况
        header('Access-Control-Allow-Origin:*');
        header('Content-Type:application/json');
        die(json_encode($return, JSON_UNESCAPED_UNICODE));
    }

    /**服务端基础curl */
    public static function request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if ($data) {
            //设置post方式提交
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $res = curl_exec($curl);
        curl_close($curl);

        $p_res = json_decode($res, true);
        if ($p_res) {
            return $p_res;
        } else {
            return $res;
        }
    }

    /**fsockopen发送的异步POST请求 */
    public static function requestAsync($url, $data = null)
    {
        $urlInfo = parse_url($url);

        $host = $urlInfo['host'];
        $port = $urlInfo['scheme'] == 'http' ? 80 : 443;
        $path = isset($urlInfo['query']) ? $urlInfo['path'] . '?' . $urlInfo['query'] : $urlInfo['path'];

        $fp = fsockopen($port == 80 ? $host : 'ssl://' . $host, $port);

        $out  = "POST " . $path . " HTTP/1.1\r\n";
        $out .= "host: " . $host . "\r\n";
        $out .= "content-length: " . strlen($data) . "\r\n";
        $out .= "content-type: application/x-www-form-urlencoded\r\n";
        $out .= "connection: close\r\n\r\n";
        $out .= $data;

        fwrite($fp, $out);
        fclose($fp);
    }

    /**加密uid生成token */
    public static function setSession($uid)
    {
        $salt = Cfg::getCfg('salt');
        $token = base64_encode($salt . base64_encode(time() . '&' . $_SERVER['REMOTE_ADDR'] . '&' . $uid));
        return $token;
    }

    /**解密token获得uid */
    public static function getSession($token)
    {
        $salt = Cfg::getCfg('salt');
        if ($token == $salt) return -1;
        $code = base64_decode(str_replace($salt, '', base64_decode($token)));
        $arr = explode('&', $code);

        try {
            $time = $arr[0];
            $ip = $arr[1];
            $uid = $arr[2];

            if ($time < time() - 3600 * 2) {
                // token超过2小时表示已过期，请重新登录
                return false;
            } else if ($ip != $_SERVER['REMOTE_ADDR']) {
                // ip不一致
                return $uid;
            } else {
                return $uid;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**将array转换为xml */
    public static function toXml($data)
    {
        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**将xml转换为array */
    public static function fromXml($xml)
    {
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**产生随机字符串 */
    public static function getRandCode($len = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $len; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 获取微信APP信息
     * @param string $w appid或type 默认miniapp
     */
    public static function getAppinfo($w)
    {
        if ($w) {
            $appinfo = Appinfo::get(['appid' => $w]);
            if (!$appinfo) $appinfo = Appinfo::get(['type' => $w]);
            if (!$appinfo) $appinfo = Appinfo::get(['type' => 'miniapp']);
        } else {
            $appinfo = Appinfo::get(['type' => 'miniapp']);
        }

        return $appinfo;
    }

    /**
     * 抽奖算法
     * @param array $data
     *  exp: [
     *         ['id' => 1,'chance'=>50, ...],
     *         ['id' => 2,'chance'=>30, ...],
     *         ['id' => 3,'chance'=>20, ...],
     *       ]
     * 三个奖品中抽一个
     * @return ['id' => 3,'chance'=>20, ...],
     */
    public static function lottery($data, $chance = 'chance')
    {
        // 数组需要先根据chance 升序排序
        foreach ($data as $key => $value) {
            $sort[$key] = $value[$chance];
        }
        array_multisort($sort, SORT_ASC, $data);
        $totalPt = array_sum(array_column($data, $chance));
        $randPt = mt_rand(0, $totalPt);  // 随机一个分数
        $basePt = 0; // 分数堆叠
        foreach ($data as $value) {
            if ($randPt <= $value[$chance] + $basePt) {
                return $value;
            } else {
                $basePt += $value[$chance];
            }
        }
    }

    /**微信数据解密算法 */
    public static function wxDecrypt($appid, $sessionKey, $encryptedData, $iv)
    {
        require_once APP_PATH . 'wx/aes/wxBizDataCrypt.php';
        $pc = new \WXBizDataCrypt($appid, $sessionKey);
        $errcode = $pc->decryptData($encryptedData, $iv, $data);
        $data = json_decode($data, true);

        return [
            'errcode' => $errcode,
            'data' => $data
        ];
    }

    /**
     * 随机获取某个数据表中的一条数据
     * @param string $tableName 表名（不含前缀）
     */
    public static function dbRandOne($tableName)
    {
        $tableName = config('database.prefix') . $tableName;

        return Db::query("SELECT * FROM `{$tableName}` AS t1 
                JOIN (SELECT ROUND(
                    RAND() * ((SELECT MAX(id) FROM `{$tableName}`)-(SELECT MIN(id) FROM `{$tableName}`))+(SELECT MIN(id) FROM `{$tableName}`)
                ) AS id) AS t2 
                WHERE t1.id >= t2.id
                ORDER BY t1.id LIMIT 1;");
    }
}
