<?php

namespace app\controller;

use app\BaseController;
use app\model\CardModel;
use app\model\UserModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\PayModeModel;
use app\model\PayRecordModel;
use app\model\ProductTipsModel;
use app\model\UserCheckModel;
use app\model\UserNoticeModel;
use app\model\UserWebModel;
use app\service\CardService;
use app\service\CheckService;
use app\service\PayService;
use app\service\StorageService;
use app\service\ConfigService;
use app\supplier\Check as SupplierCheck;
use think\facade\Log;
use think\facade\Config;

class Check extends BaseController
{
    public function product_info()
    {
        $userid = $this->request->userid;
        $user = UserModel::where('id', $userid)->find();

        $products = CheckModel::select();
        $data = [];
        foreach ($products as $product) {
            $up = UserCheckModel::where(['userid' => $userid, 'product_id' => $product->id])->find();
            if (empty($up)) {
                $up = new UserCheckModel();
                $up->userid = $userid;
                $up->product_id = $product->id;
                $up->unit = $product->unit;
                $up->price = max($product->mini_price, $product->low_price, $product->price);
                if ($product->supplier_status == 1 || $product->status == 1) {
                    $up->status = 1;
                } else {
                    $up->status = 2;
                }
                $up->save();
            }
            if ($product->status != 1) {
                //不可用
                continue;
            }
            if ($product->supplier_status != 1) {
                //不可用
                continue;
            }
            $item = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $up->price,
                'unit' => $product->unit,
                'status' => $up->status,
                'config' => $product->config,
            ];
            $data[] = $item;
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $data
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
        $user = UserModel::where("id", $userid)->find();
        $tid = 0;
        if (!empty($user)) {
            $tid = $user->tid;
        }
        CheckOrderModel::insert([
            'id' => $orderid,
            'userid' => $userid,
            'tid' => $tid,
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
        } else {
            $time = date("Y-m-d H:i:s", strtotime("-6 day"));
            if (strcmp($data['update_time'], $time) > 0) {
                //尝试获取报告
                $yorder = CheckOrderModel::where(['id' => $orderid])->find();
                $downurl = (new StorageService())->getFileUrl($yorder->file_key);
                if (!empty($downurl)) {
                    $data['report_url'] = $downurl;
                    CheckOrderModel::where('id', $orderid)->update(['report_url' => $downurl, 'update_time' => date('Y-m-d H:i:s', time())]);
                }
            }
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
        $orderid = "";
        $payRecord = PayRecordModel::where(['id' => $payid])->find();
        if (!empty($payRecord)) {
            $orderid = $payRecord->orderid;
        }
        if (empty($orderid)) {
            $card = CardModel::where(['id' => $payid])->find();
            if (!empty($card)) {
                $orderid = $card->order_id;
            }
        }
        if (empty($orderid)) {
            $order = CheckOrderModel::where(['payid' => $payid])->find();
            if (!empty($order)) {
                $orderid = $order->id;
            }
        }
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => "没有找到支付记录，请检查订单号"
            ]);
        }
        $ret = $this->getCheckOrder($orderid);
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
        CheckOrderModel::where(['id' => $orderid])->update(['status' => 10, 'report_url' => "", 'update_time' => date('Y-m-d H:i:s')]);
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

    public function get_join_url()
    {
        $userid = $this->request->userid;
        $join_url = "";
        $userweb = UserWebModel::where('userid', $userid)->find();
        if (empty($userweb)) {
            return json([
                'code' => 1,
                'msg' => '没有设置'
            ]);
        }
        if ($userweb->show_jc == 1) {
            $join_url = Config::get('website.admin_domain') . "/su/" . $userid . ".html";
        }
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'join_url' => $join_url
            ]
        ]);
    }

    public function get_product_notice()
    {
        $list['code'] = 0;
        $list['msg'] = 'success';
        $list['data'] = [];
        $userid = $this->request->userid;
        $product_id = $this->request->get('productid');
        if (empty($product_id)) {
            return json([
                'code' => 1,
                'msg' => 'product_id不能为空'
            ]);
        }

        $tips = ProductTipsModel::where(['product_id' => $product_id])->find();
        if (!empty($tips)) {
            $list['data']['tips'] = $tips;
        }
        $userNoticeEnable = false;
        $funConfig = ConfigService::get("function");
        if (!empty($funConfig)) {
            $userNoticeEnable = strtolower($funConfig['notice']) == 'true';
        }
        $list['data']['notice'] = "";
        if ($userNoticeEnable) {
            $notice = UserNoticeModel::where(['userid' => $userid, 'position' => $product_id, "status" => 2])->find();
            if (!empty($notice)) {
                $list['data']['notice'] = $notice;
            }
        }

        return json($list);
    }

    public function get_pay_type()
    {
        $userid = $this->request->userid;
        $user = UserModel::where("id", $userid)->find();
        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $user->pay_type
        ]);
    }

    public function pay_by_card()
    {
        $extensionsEnable = false;
        $funConfig = ConfigService::get("function");
        if (!empty($funConfig)) {
            $extensionsEnable = strtolower($funConfig['extensions']) == 'true';
        }
        if (!$extensionsEnable) {
            return json([
                'code' => 10001,
                'msg' => '没有启用扩展功能'
            ]);
        }
        $userid = intval($this->request->userid);
        $cards = $this->request->post('cards');
        $orderid = $this->request->post('orderid');
        $order = CheckOrderModel::where(['id' => $orderid])->find();

        if (empty($cards)) {
            return json([
                'code' => 10001,
                'msg' => '卡号不能为空'
            ]);
        } else {
            $cards = trim($cards);
            $cards = strtoupper($cards);
        }

        if (empty($order)) {
            return json([
                'code' => 10010,
                'msg' => '订单不存在'
            ]);
        }
        if ($order->status >= 4) {
            return json([
                'code' => 10099,
                'msg' => "重复支付"
            ]);
        }

        if ($order->piece <= 0) {
            return json([
                'code' => 10099,
                'msg' => '没有解析成功'
            ]);
        }
        if ($order->userid != $userid) {
            return json([
                'code' => 10099,
                'msg' => '已经支付，或者不能用检测卡支付'
            ]);
        }
        $ret = (new CardService())->writeoffCards($userid, $cards, $orderid, $order->product_id, $order->piece);
        if ($ret['code'] != 0) {
            return json($ret);
        }
        $user = UserModel::where("id", $userid)->find();

        //付款成功
        $now = date('Y-m-d H:i:s');
        $spayid = $cards;
        CheckOrderModel::where("id", $orderid)->update(['status' => 4, 'payid' => $cards, 'spayid' => $spayid, 'pay_time' => $now, 'update_time' => $now]);
        if (!empty($user->tid)) {
            $tuser = UserModel::where("id", $user->tid)->find();
            if (!empty($tuser)) {
                $tuser->increaseBalance($order->tprofit, 1, $order->id, '邀请奖励(销售:' . $order->userid . ')');
                UserModel::where("id", $order->tid)->inc('money', $order->tprofit)->update();
                UserModel::where("id", $order->userid)->inc('tmoney', $order->tprofit)->update();
            }
        }
        $check_data = [
            "title" => $order->title,
            "author" => $order->author
        ];
        if (!empty($order->end_date)) {
            $check_data['end_date'] = $order->end_date;
        }
        if (!empty($order->param)) {
            $check_data = array_merge($check_data, $order->param);
        }
        $ret =  (new SupplierCheck())->payOrder($order->id, $check_data);
        if ($ret['code'] == 0) {
            CheckOrderModel::where("id", $orderid)->update(['status' => 5, 'update_time' => date('Y-m-d H:i:s')]);
            return [
                'code' => 0,
                'msg' => ''
            ];
        } else {
            Log::error($orderid . " 订单付款失败-" . $ret['msg']);
        }
        return [
            'code' => 0,
            'msg' => '',
        ];
    }
}
