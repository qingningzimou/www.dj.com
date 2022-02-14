<?php
namespace app\controller;

use app\BaseController;
use think\captcha\facade\Captcha;

class Index extends BaseController
{
    public function index()
    {
        header("Location: /index");
    }

    public function test()
    {
//        echo phpinfo();
//        echo 'ddd';
        return Captcha::create();
    }
}
