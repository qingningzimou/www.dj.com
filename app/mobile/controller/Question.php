<?php

namespace app\mobile\controller;

use app\common\controller\MobileEducation;
use app\mobile\model\user\Question as model;
use think\facade\Db;
use think\response\Json;

class Question extends MobileEducation
{

    /**
     * 根据问题，获取相关问题列表
     * @param string text
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {

                $list = Db::name('Question')->where('deleted',0)->field([
                    'id',
                    'content',
                    'title'
                ])
                    ->order('create_time','DESC')
                    ->select();
                $res = [
                    'code' => 1,
                    'data' => $list
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 获取问题详情
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                $data = $this->request->only([
                    'id'
                ]);
                $info = model::find($data['id']);
                $res = [
                    'code' => 1,
                    'data' => $info
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage()
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
}