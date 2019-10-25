<?php

namespace app\base\controller;

use think\Controller;
use app\base\service\Common;
use app\api\model\User;
use think\Validate;

class Base extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $version = input('version');
    }

    /**获取访问用户uid */
    protected function getUser()
    {
        $token = input('token');
        if (!$token) Common::res(['code' => 200]);
        $this->uid = Common::getSession($token);
        if (!$this->uid) Common::res(['code' => 201]);
    }

    protected function getUserByUnionid()
    {
        $this->uid = User::where('unionid', input('unionid'))->value('id');
        if (!$this->uid) Common::res(['code' => 1, 'msg' => '不存在的用户']);
    }

    /**
     * 获取并校验请求参数
     * https://www.kancloud.cn/manual/thinkphp5_1/354107
     */
    protected function req($field, $validate = '', $default = null)
    {
        $value = input($field, $default);
        if ($validate && !Validate::checkRule($value, $validate)) {
            Common::res(['code' => 100, 'msg' => '参数错误！' . $field . '，错误值：' . $value]);
        }

        return $value;
    }
}
