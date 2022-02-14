<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/21
 * Time: 8:37
 */
namespace app\api\controller\system;

use app\common\controller\Education;
use app\common\model\SysSupplementCategory as model;
use Overtrue\Pinyin\Pinyin;
use think\facade\Lang;
use think\response\Json;
use think\facade\Cache;
use think\facade\Db;

class Supplement extends Education
{
    /**
     * 获取费用类目列表
     * @return Json
     */
    public function getList(): Json
    {
        if ($this->request->isPost()) {
            try {
                $where = [];
                $where[] = ['deleted','=', 0];
                $where[] = ['disabled','=', 0];

                $list = (new model())->where($where)->hidden(['deleted'])->select()->toArray();
                $data['data'] = $list;
                $res_data = $this->getResources($this->userInfo, $this->request->controller());
                $data['resources'] = $res_data;
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }
    /**
     * 获取费用类目详细信息
     * @param id 费用类目ID
     * @return Json
     */
    public function getInfo(): Json
    {
        if ($this->request->isPost()) {
            try {
                //  如果数据不合法，则返回
                if (!$this->result['id']) {
                    throw new \Exception('补充资料类目ID错误');
                }
                $data = (new model())->where('id', $this->result['id'])->hidden(['deleted'])->find();
                if (empty($data)) {
                    throw new \Exception(Lang::get('info_no_found'));
                }
                $res = [
                    'code' => 1,
                    'data' => $data
                ];
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 新增
     * @return Json
     */
    public function actAdd(): Json
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'supplement_name',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }
                //  如果数据不合法，则返回
                if ($data['supplement_name'] == '') {
                    throw new \Exception('请填写补充资料类目名称');
                }
                $pinyin = new Pinyin();
                $data['supplement_code'] = $pinyin->abbr($data['supplement_name']);
                $data['remark'] = htmlspecialchars($data['remark']);

                if(mb_strlen($data['supplement_name'], 'UTF-8') > 60){
                    throw new \Exception('补充资料类目名称太长！');
                }
                if(mb_strlen($data['remark'], 'UTF-8') > 100){
                    throw new \Exception('备注太长！');
                }

                unset($data['hash']);

                $cost_id = (new model())->insertGetId($data);
                if(!$cost_id) {
                    throw new \Exception('新增补充资料类目失败');
                }
                if (Cache::get('update_cache')) {
                    $supplementList = Db::name('SysSupplementCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('supplement', $supplementList);
                }
                //  操作成功
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('res_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 编辑
     * @param id        费用类目ID
     * @param cost_name  费用名称
     * @param remark    备注说明
     * @param hash      表单hash
     * @return Json
     */
    public function actEdit()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'supplement_name',
                    'remark',
                    'hash'
                ]);
                //  验证表单hash
                $checkHash = parent::checkHash($data['hash'],$this->result['user_id']);
                if($checkHash['code'] == 0)
                {
                    throw new \Exception($checkHash['msg']);
                }

                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('补充资料类目ID错误');
                }
                if ($data['supplement_name'] == '') {
                    throw new \Exception('请填写补充资料类目名称');
                }
                if(mb_strlen($data['supplement_name'], 'UTF-8') > 60){
                    throw new \Exception('补充资料类目名称太长！');
                }
                if(mb_strlen($data['remark'], 'UTF-8') > 100){
                    throw new \Exception('备注太长！');
                }

                $pinyin = new Pinyin();
                $data['supplement_code'] = $pinyin->abbr($data['supplement_name']);
                $data['remark'] = htmlspecialchars($data['remark']);
                unset($data['hash']);

                (new model())->editData($data);

                if (Cache::get('update_cache')) {
                    $supplementList = Db::name('SysSupplementCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('supplement', $supplementList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('update_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }

    /**
     * 删除
     * @param id    费用类目ID
     * @return Json
     */
    public function actDelete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->request->only([
                    'id',
                    'deleted' => 1
                ]);

                //  如果数据不合法，则返回
                if ($data['id'] <= 0) {
                    throw new \Exception('补充资料类目ID错误');
                }

                (new model())->deleteData($data);

                if (Cache::get('update_cache')) {
                    $supplementList = Db::name('SysSupplementCategory')->where('disabled',0)->where('deleted',0)->select()->toArray();
                    Cache::set('supplement', $supplementList);
                }
                $res = [
                    'code' => 1,
                    'msg' => Lang::get('delete_success')
                ];
                Db::commit();
            } catch (\Exception $exception) {
                $res = [
                    'code' => 0,
                    'msg' => $exception->getMessage() ?: Lang::get('system_error')
                ];
                Db::rollback();
            }
            return parent::ajaxReturn($res);
        }
    }


}