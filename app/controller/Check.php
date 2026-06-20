<?php

namespace app\controller;

use app\BaseController;
use app\model\UserModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\PayModeModel;
use app\model\PayRecordModel;
use app\model\UserCheckModel;
use app\model\UserWebModel;
use app\service\CheckService;
use app\service\PayService;
use app\supplier\Check as SupplierCheck;


class Check extends BaseController
{
    public function product_info()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();

        $products = UserCheckModel::where('userid', $userid)->select();
        $retdata = [];
        foreach ($products as $check) {
            if ($check->status != 1) {
                //不可用
                continue;
            }
            $product = CheckModel::where('id', $check->product_id)->find();
            if ($product->status != 1) {
                //不可用
                continue;
            }
            if ($product->supplier_status != 1) {
                //不可用
                continue;
            }
            $retdata[] = [
                'id' => $check['product_id'],
                'name' => $product->name,
                'unit' => $check['unit'],
                'price' => $check['price'],
                'status' => $check['status'],
                'config' => $product->config,
            ];
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $retdata
        ]);
    }


    public function get_upload_param()
    {
        $product_id = $this->request->post("product_id");
        $userid = $this->request->userid;
        if (empty($product_id)) {
            return json([
                'code' => 1,
                'msg' => 'product_id必须填写'
            ]);
        }


        $product = CheckModel::where("id", $product_id)->find();
        if (empty($product)) {
            return [
                'code' => 10007,
                'msg' => '产品不存在'
            ];
        }
        if ($product->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        if ($product->supplier_status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        $usercheck = UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->find();
        if (empty($usercheck)) {
            return [
                'code' => 10009,
                'msg' => '产品不存在'
            ];
        }
        if ($usercheck->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        $domain = $this->request->domain();
        $notify = $domain . "/notify/checkOrderStatus";
        $data = (new SupplierCheck())->getUploadParam($product_id, $notify);

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    public function create_check_order()
    {
        $orderid = $this->request->post("orderid");
        $userid = $this->request->userid;
        $productid = $this->request->post("product_id");
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => 'orderid必须填写'
            ]);
        } else {
            $orderid = trim($orderid);
        }

        if (empty($productid)) {
            return json([
                'code' => 1,
                'msg' => 'productid必须填写'
            ]);
        } else {
            $productid = trim($productid);
        }
        CheckOrderModel::insert([
            'id' => $orderid,
            'userid' => $userid,
            'product_id' => $productid,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }

    private function getCheckOrder(string $orderid)
    {
        $order = CheckOrderModel::where(['id' => $orderid])->withoutField(['original', 'cost', 'pcost', 'ppiece', 'profit', 'pprofit', 'tprofit', 'lock', 'file_key', 'lock_time'])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => '订单不存在'
            ]);
        }
        $data = $order->toArray();
        if ($data['status'] != 8) {
            $data['report_url'] = "";
        }
        return [
            'code' => 0,
            'msg' => '',
            'data' => $data
        ];
    }

    public function get_order_status()
    {
        $orderid = $this->request->post("orderid");
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => 'orderid必须填写'
            ]);
        } else {
            $orderid = trim($orderid);
        }

        $ret = $this->getCheckOrder($orderid);

        return json($ret);
    }

    public function report_info()
    {
        $data = $this->request->post();
        $orderid = "";
        $title = "";
        $author = "";
        $endTime = "";
        $ret = (new CheckService())->validateParameters($data);
        if ($ret['code'] != 0) {
            return json($ret);
        }
        $updata = $ret['data'];
        if (empty($data['order_id'])) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        } else {
            $orderid = trim($data['order_id']);
        }
        $order = CheckOrderModel::where(['id' => $orderid])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => '订单不存在'
            ]);
        }
        if ($order->status > 3) {
            return json([
                'code' => 1,
                'msg' => '订单已处理'
            ]);
        }
        CheckOrderModel::where(['id' => $orderid])->update($updata);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }

    public function get_status_by_payid()
    {
        $payid = $this->request->post('payid');
        if (empty($payid)) {
            return json([
                'code' => 1,
                'msg' => "payid必须填写"
            ]);
        } else {
            $payid = trim($payid);
        }
        $payRecord = PayRecordModel::where(['id' => $payid])->find();
        if (empty($payRecord)) {
            return json([
                'code' => 1,
                'msg' => '支付记录不存在'
            ]);
        }
        $ret = $this->getCheckOrder($payRecord->orderid);
        return json($ret);
    }

    public function delete_report()
    {
        $orderid = $this->request->post('orderid');
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        } else {
            $orderid = trim($orderid);
        }
        $order = CheckOrderModel::where(['id' => $orderid])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => '订单不存在'
            ]);
        }
        if ($order->status != 8) {
            return json([
                'code' => 1,
                'msg' => '订单无法删除报告'
            ]);
        }
        CheckOrderModel::where(['id' => $orderid])->update(['status' => 10, 'report_url'=>"", 'update_time' => date('Y-m-d H:i:s')]);
        return json([
            'code' => 0,
            'msg' => ""
        ]);
    }
    //自助退款
    public function self_refund()
    {
        $orderid = $this->request->post('orderid');
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        } else {
            $orderid = trim($orderid);
        }
        $order = CheckOrderModel::where(['id' => $orderid])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => '订单不存在'
            ]);
        }
        if ($order->status != 7) {
            return json([
                'code' => 1,
                'msg' => '该订单不能自助退款，请联系客服'
            ]);
        }
        $ret = (new PayService())->refund($order->payid);
        if ($ret['code'] == 0) {
            return json([
                'code' => 0,
                'msg' => "退款成功"
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => "退款失败请联系客服"
            ]);
        }
    }

    public function get_customer()
    {
        $userid = $this->request->userid;
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '请先设置 检测链接 再来设置客服'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $userweb
        ]);
    }
}
