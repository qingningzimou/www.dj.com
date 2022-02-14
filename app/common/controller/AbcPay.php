<?php
/**
 * Created by PhpStorm.
 * User: aiwes
 * Date: 2020/4/20
 * Time: 21:35
 */
use think\facade\Lang;
use think\facade\Cache;
require_once ( __DIR__.'/../../../ebusclient/RefundRequest.php');
require_once ( __DIR__.'/../../../ebusclient/QueryOrderRequest.php');
class AbcPay
{
    /**
     * 请求退费
     * @param $pay_time
     * @param $order_code
     * @param $pay_amount
     * @return array
     */
    public function actRefund($pay_time, $order_code, $pay_amount, $merchant_num)
    {
        try {
            $code = mt_rand(100,999);
            //1、生成退款请求对象
            $tRequest = new RefundRequest();
            $tRequest->request["OrderDate"] = (date('Y/m/d',strtotime($pay_time))); //订单日期（必要信息）
            $tRequest->request["OrderTime"] = (date('H:i:s',strtotime($pay_time))); //订单时间（必要信息）
            $tRequest->request["MerRefundAccountNo"] = (''); //商户退款账号
            $tRequest->request["MerRefundAccountName"] = (''); //商户退款名
            $tRequest->request["OrderNo"] = ($order_code); //原交易编号（必要信息）
            $tRequest->request["NewOrderNo"] = ($order_code.'_'.$code); //交易编号（必要信息）
            $tRequest->request["CurrencyCode"] = ('156'); //交易币种（必要信息）
            $tRequest->request["TrxAmount"] = $pay_amount; //退货金额 （必要信息）
            $tRequest->request["RefundType"] = (0); //退款类型
            $tRequest->request["MerchantRemarks"] = (''); //附言

            $data = [];
            $tResponse = $tRequest->extendPostRequest($merchant_num);
            if ($tResponse->isSuccess()) {
                $data['refund_status'] = 1;
                $data['refund_code'] = $tResponse->GetValue("NewOrderNo");
                $data['refund_amount'] = $tResponse->GetValue("TrxAmount");
            } else {
                $data['refund_status'] = 0;
                $data['refund_code'] = $order_code.'_'.$code;
                $data['msg'] = $tResponse->getErrorMessage();
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
        return $res;
    }
    /**
     * 退费查询
     * @param $refund_code
     * @return array
     */
    public function actRefundStatus($refund_code,$merchant_num)
    {
        try {
            //1、生成交易查询对象
            $payTypeID = ('Refund');
            $QueryType = "false";
            //2、传送请求
            $tRequest = new QueryOrderRequest();
            $tRequest->request["PayTypeID"] = $payTypeID; //设定交易类型
            $tRequest->request["OrderNo"] = ($refund_code); //设定订单编号 （必要信息）
            $tRequest->request["QueryDetail"] = $QueryType; //设定查询方式

            $data = [];
            $tResponse = $tRequest->extendPostRequest($merchant_num);
            if ($tResponse->isSuccess()) {
                $orderInfo = $tResponse->GetValue("Order");
                if ($orderInfo == null) {
                    throw new \Exception('查询结果为空');
                } else {
                    //1、还原经过base64编码的信息
                    $orderDetail = base64_decode($orderInfo);
                    $orderDetail = iconv("GB2312", "UTF-8", $orderDetail);
                    $detail = new Json($orderDetail);
                    $data['refund_type'] = $detail->GetValue("PayTypeID");
                    $data['refund_code'] = $detail->GetValue("OrderNo");
                    $data['refund_time'] = str_replace("/","-",$detail->GetValue("OrderDate")).' '.$detail->GetValue("OrderTime");
                    $data['refund_amount'] = $detail->GetValue("OrderAmount");
                    $data['refund_status'] = 1;
                }
            } else {
                throw new \Exception($tResponse->getErrorMessage());
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
        return $res;
    }
}