<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/18
 * Time: 22:30
 */
namespace app\api\controller;

use app\common\controller\Basic;
use think\facade\Request;
use think\captcha\facade\Captcha;

class EntryCaptcha extends Basic
{
    public function index()
    {
        $id = '';
        if (Request::has('id','get')){
            $id = Request::param('id');
        }
        return Captcha::create(null,$id);
    }
}
