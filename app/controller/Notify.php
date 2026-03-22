<?php

namespace app\controller;

use app\BaseController;
use app\service\CheckService;
use think\facade\Log;
use app\service\PayService;
use Yansongda\Pay\Pay;

class Notify extends BaseController
{
    public function checkOrderStatus()
    {
        $data = $this->request->post();
        Log::write("checkOrderStatus");
        Log::write($data);
        $ret = (new CheckService())->updateStatusFromSupplier($data['data']);
        if ($ret) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "ok";
            exit;
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo "error";
            exit;
        }
    }

    public function wxpay($modeid)
    {
        $payService = new PayService();
        $config = $payService->getConfig($modeid);
        if (empty($config)) {
            return "支付配置不存在";
        }
        $pay = Pay::wechat($config);
        $result = $pay->callback();
        $ret = $result->toArray();
        if (empty($ret)) {
            Log::error("无法获取支付结果");
            Log::error($result);
            return $pay->success();
        }
        if (empty($ret['resource'])) {
            Log::error("无法获取支付结果1");
            Log::error($ret);
            return $pay->success();
        }
        if (empty($ret['resource']['ciphertext'])) {
            Log::error("无法获取支付结果2");
            Log::error($ret);
            return $pay->success();
        }
        $data = $ret['resource']['ciphertext'];
        if ($data['trade_state'] != 'SUCCESS') {
            Log::error("支付失败");
            Log::error($ret);
            return $pay->success();
        }
        if (empty($data['out_trade_no'])) {
            Log::error("无法获取支付结果3");
            Log::error($ret);
            return $pay->success();
        }
        //支付金额
        $out_trade_no = $data['out_trade_no'];
        $payService->paySucess($out_trade_no);
        return $pay->success();
    }

    public function alipay($modeid)
    {
        $payService = new PayService();
        $config = $payService->getConfig($modeid);
        if (empty($config)) {
            return "支付配置不存在";
        }
        $pay = Pay::alipay($config);
        $result = $pay->callback();
        $ret = $result->toArray();
        if (empty($ret)) {
            Log::error("无法获取支付结果");
            Log::error($result);
            return $pay->success();
        }
        if ($ret['trade_status'] != 'TRADE_SUCCESS') {
            Log::error("支付失败");
            Log::error($ret);
            return $pay->success();
        }
        if (empty($ret['out_trade_no'])) {
            Log::error("无法获取支付结果3");
            Log::error($ret);
            return $pay->success();
        }
        $out_trade_no = $ret['out_trade_no'];
        $payService->paySucess($out_trade_no);
        return $pay->success();
    }
}
