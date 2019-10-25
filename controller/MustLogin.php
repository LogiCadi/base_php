<?php
namespace app\base\controller;


class MustLogin extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->getUser();
    }
}
