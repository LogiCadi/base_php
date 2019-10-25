<?php
namespace app\base\service;

/**返回消息 */
class CodeSign
{

    const MSG = [
        ['code' => -1, 'msg' => '未知异常'],
        ['code' => 0, 'msg' => '操作成功'],
        ['code' => 1, 'msg' => '操作失败'],

        ['code' => 100, 'msg' => '参数错误'],

        ['code' => 200, 'msg' => '请登录'],
        ['code' => 201, 'msg' => '登录已过期'],
        ['code' => 202, 'msg' => '登录失败'],

        ['code' => 300, 'msg' => '货币不足'],
        ['code' => 301, 'msg' => '未加入该偶像圈'],
        ['code' => 302, 'msg' => '已加入该偶像圈'],
        ['code' => 303, 'msg' => '该商品已下架'],
        
        ['code' => 400, 'msg' => '数据异常，请稍后再试'],
        
        ['code' => 500, 'msg' => '发送失败'],
        
        ['code' => 600, 'msg' => '支付异常'],

    ];

    public static function getMsg($code)
    {
        $msg = self::MSG[0]['msg'];
        foreach (self::MSG as $v) {
            if ($v['code'] == $code) {
                $msg = $v['msg'];
                break;
            }
        }

        return $msg;
    }
}
