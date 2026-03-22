<?php

namespace app\controller;

use app\BaseController;
use app\model\AdminModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\PayRecordModel;
use app\model\PaySetModel;
use app\model\ShopModel;
use app\service\ConfigService;
use app\service\WxPublicService;
use think\facade\Log;
use app\model\ShopProductModel;
use app\service\CheckService;
use app\supplier\Check;
use app\service\PayService;
use app\service\StorageService;
use think\facade\Cache;

class Index extends BaseController
{
    public function index()
    {
        return '欢迎使用';
    }


    public function adminLogin()
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');
        $remember = $this->request->param('remember', false);
        if (empty($username) || empty($password)) {
            return json([
                'code' => 1,
                'msg' => '参数错误'
            ]);
        } else {
            $password = trim($password);
            $username = trim($username);
        }
        $user = AdminModel::where('name', $username)->find();
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        if ($user->pass != md5($password)) {
            return json([
                'code' => 1,
                'msg' => '密码错误'
            ]);
        }
        $expireTime = 24; //小时
        if ($remember) {
            $expireTime = 168; //周
        }
        $token = $user->getAuth($user->id, $expireTime);
        return json([
            'code' => 0,
            'msg' => '登录成功',
            'data' => [
                'id' => $user->id,
                'token' => $token['jwt'],
                'name' => $user->name,
                'avatar' => $user->getAvatar($this->request->domain())
            ]

        ]);
    }

    public function getCustomConfig()
    {
        //客服配置
        $config = ConfigService::get("custom");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置自定义信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    //获取所有配置
    public function getAllConfig()
    {
        $list['code'] = 0;
        $list['msg'] = '';
        $config3 = ConfigService::get("custom");
        if (!empty($config3)) {
            $list['data']['custom'] = $config3;
        }

        //是否启用了公众号
        $config4 = ConfigService::get("wxpublic");
        if (!empty($config4)) {
            $list['data']['wechat'] = true;
        } else {
            $list['data']['wechat'] = false;
        }
        return json($list);
    }


    //获取微信授权跳转链接
    public function getWechatAuthUrl()
    {
        $url = $this->request->param('url');
        if (empty($url)) {
            return json([
                'code' => 1,
                'msg' => '请传入回调地址'
            ]);
        }
        $wechat = new WxPublicService();
        if (!$wechat->isInit()) {
            return json([
                'code' => 1,
                'msg' => '微信配置错误'
            ]);
        }
        $redirectUrl = $wechat->authorizeUrl($url);
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'url' => $redirectUrl
            ]
        ]);
    }
    //获取微信openid
    public function getWechatAuthUserInfo()
    {
        $code = $this->request->param('code');
        if (empty($code)) {
            return json([
                'code' => 1,
                'msg' => '请传入code'
            ]);
        }
        $wechat = new WxPublicService();
        if (!$wechat->isInit()) {
            return json([
                'code' => 1,
                'msg' => '微信配置错误'
            ]);
        }
        $ret = $wechat->getAccessOpenID($code);
        $openid = "";
        if ($ret['code'] != 0) {
            return json($ret);
        } else {
            $openid = $ret['data']['openid'];
        }

        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => [
                'openid' => $openid
            ]
        ]);
    }

    //获取支付配置
    public function getPayConfig()
    {
        $result = PaySetModel::where('status', 1)->select();
        if (empty($result)) {
            return json([
                'code' => 1,
                'msg' => '支付配置不存在'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '获取成功',
            'data' => $result
        ]);
    }

    public function downShopFile()
    {
        $shopid = $this->request->get("shopid");
        $show = ShopModel::where('id', $shopid)->find();
        if (empty($show)) {
            return json([
                'code' => 1,
                'msg' => '店铺不存在'
            ]);
        }
        $file_path =  $show->file_path;
        if (empty($show->file_name)) {
            return json([
                'code' => 1,
                'msg' => '文件不存在'
            ]);
        }
        if (!file_exists($file_path)) {
            $list['code'] = 1;
            $list['msg'] = '文件不存在';
            return json($list);
        } else {
            // 打开文件
            $file1 = fopen($file_path, "r");
            // 输入文件标签
            Header("Content-type: application/octet-stream");
            Header("Accept-Ranges: bytes");
            Header("Accept-Length:" . filesize($file_path));
            Header("Content-Disposition: attachment;filename=" . $show->file_name);
            ob_clean();     // 重点！！！
            flush();        // 重点！！！！可以清除文件中多余的路径名以及解决乱码的问题：
            //输出文件内容
            //读取文件内容并直接输出到浏览器
            echo fread($file1, filesize($file_path));
            fclose($file1);
            return;
        }
    }

    public function getAllProduct()
    {
        $shopid = $this->request->get("shopid");
        if (empty($shopid)) {
            return json([
                'code' => 1,
                'msg' => 'shopid必须填写'
            ]);
        }
        $shop = ShopModel::where("id", $shopid)->find();
        if (empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '该店铺不存在'
            ]);
        }
        if ($shop->status != 1) {
            return json([
                'code' => 1,
                'msg' => '该店铺被禁用'
            ]);
        }
        $data = [];
        $products = ShopProductModel::where("shopid", $shopid)->select();
        foreach ($products as $product) {
            $check = CheckModel::where("id", $product->productid)->find();
            $status = 2;
            if (($check->supplier_status == 1) && ($check->status == 1) && ($product->status == 1)) {
                $status = 1;
            }
            $data[] = [
                "id" => $product->productid,
                "name" => $check->name,
                'price' => $product->price,
                'unit' => $product->unit,
                'status' => $status,
                'config' => $check->config
            ];
        }

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    public function getUploadParam()
    {
        $checkid = $this->request->post("product_id");
        $shopid = $this->request->post("shopid");
        if (empty($checkid)) {
            return json([
                'code' => 1,
                'msg' => 'product_id必须填写'
            ]);
        }
        if (empty($shopid)) {
            return json([
                'code' => 1,
                'msg' => 'shopid必须填写'
            ]);
        }
        //检查店铺信息
        $shop = ShopModel::where("id", $shopid)->find();
        if (empty($shop)) {
            return json([
                'code' => 1,
                'msg' => '该店铺不存在'
            ]);
        }
        if ($shop->status != 1) {
            return json([
                'code' => 1,
                'msg' => '该店铺被禁用'
            ]);
        }

        $check = CheckModel::where("id", $checkid)->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => '该货源不存在'
            ]);
        }
        if (($check->supplier_status != 1) || ($check->status != 1)) {
            return json([
                'code' => 1,
                'msg' => '该商品不可用'
            ]);
        }
        $product = ShopProductModel::where(['shopid' => $shopid, 'productid' => $checkid])->find();
        if (empty($product)) {
            return json([
                'code' => 1,
                'msg' => '该商品不存在'
            ]);
        }
        if ($product->status != 1) {
            return json([
                'code' => 1,
                'msg' => '该商品不可用'
            ]);
        }
        $domain = $this->request->domain();
        $notify = $domain . "/notify/checkOrderStatus";
        $data = (new Check())->getUploadParam($checkid, $notify);

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    public function createCheckOrder()
    {
        $orderid = $this->request->post("orderid");
        $shopid = $this->request->post("shopid");
        $productid = $this->request->post("product_id");
        if (empty($orderid)) {
            return json([
                'code' => 1,
                'msg' => 'orderid必须填写'
            ]);
        } else {
            $orderid = trim($orderid);
        }
        if (empty($shopid)) {
            return json([
                'code' => 1,
                'msg' => 'shopid必须填写'
            ]);
        } else {
            $shopid = trim($shopid);
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
            'shopid' => $shopid,
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

    private function getCheckOrder($orderid)
    {
        $order = CheckOrderModel::where(['id' => $orderid])->withoutField(['original', 'cost', 'profit', 'lock', 'file_key', 'lock_time'])->find();
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

    public function getCheckOrderStatus()
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


    public function getPaySet()
    {
        $list["code"] = 0;
        $list["msg"] = "";
        $users = PaySetModel::select();
        $list["data"] = $users;
        return json($list);
    }

    public function getPayQRcode()
    {
        $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }

        if (!isset($data['orderid'])) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        }

        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);

       
        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }
        $order = CheckOrderModel::where(['id' => $data['orderid']])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => "订单不存在"
            ]);
        }
        if($order->status != 2){
            return json([
                'code' => 99,
                'msg' => "该订单不需要支付"
            ]);
        }
        $price100 = bcmul($amount, 100, 0);
        if ($price100 != $order->total_price) {
            return json([
                'code' => 1,
                'msg' => "订单价格不正确"
            ]);
        }
        $check = CheckModel::where("id", $order->product_id)->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => "产品不存在"
            ]);
        }
        if (($check->supplier_status != 1) || ($check->status != 1)) {
            return json([
                'code' => 1,
                'msg' => "产品不能使用"
            ]);
        }
        $subject = $check->name;
        $ret = (new PayService())->getQRcode($data['modeid'], $type, $amount, $data['orderid'], $subject);
        return json($ret);
    }

    public function getH5Pay()
    {
         $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }

        if (!isset($data['orderid'])) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        }

        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);

       
        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }
        $order = CheckOrderModel::where(['id' => $data['orderid']])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => "订单不存在"
            ]);
        }
        if($order->status != 2){
            return json([
                'code' => 99,
                'msg' => "该订单不需要支付"
            ]);
        }
        $price100 = bcmul($amount, 100, 0);
        if ($price100 != $order->total_price) {
            return json([
                'code' => 1,
                'msg' => "订单价格不正确"
            ]);
        }
        $check = CheckModel::where("id", $order->product_id)->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => "产品不存在"
            ]);
        }
        if (($check->supplier_status != 1) || ($check->status != 1)) {
            return json([
                'code' => 1,
                'msg' => "产品不能使用"
            ]);
        }
        $subject = $check->name;
        $ret = [];
        $return_url = "";
        if(!empty($data['returnUrl'])){
            $return_url = $data['returnUrl'];
        }
        if ($type == 1) {
            $ip = $this->request->ip();
            $ret = (new PayService())->wxH5pay($data['orderid'], $amount, $subject, $data['modeid'], $ip);
        } else if ($type == 2) {
            $ret = (new PayService())->aliH5pay($data['orderid'], $amount, $subject, $data['modeid'],$return_url);
        }

        return json($ret);
    }

    //微信内部，jsap支付
    public function getMPpay()
    {
        $data = $this->request->post();
        if (!isset($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '类型不能为空'
            ]);
        }

        if (!isset($data['orderid'])) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        }

        //金额
        if (!isset($data['amount'])) {
            return json([
                'code' => 10005,
                'msg' => '金额不能为空'
            ]);
        }
        if ($data['amount'] <= 0) {
            return json([
                'code' => 10006,
                'msg' => '金额错误'
            ]);
        }
        if (!isset($data['modeid'])) {
            return json([
                'code' => 10009,
                'msg' => '模版id不能为空'
            ]);
        }
        //判断金额是否为数字，紧紧支持两位小数
        if (!is_numeric($data['amount'])) {
            return json([
                'code' => 10007,
                'msg' => '金额错误'
            ]);
        }
        //点的位置
        $pos = strpos($data['amount'], '.');
        if ($pos === false) {
            $pos = strlen($data['amount']);
        }
        //判断小数点后两位
        if (strlen($data['amount']) - $pos - 1 > 2) {
            $len = strlen($data['amount']);
            return json([
                'code' => 10008,
                'msg' => '仅仅支持两位小数' . $len . "-" . $pos,
            ]);
        }
        $amount = floatval($data['amount']);

       
        if ($data['type'] == 'wechat') {
            $type = 1; //微信支付
        } else if (($data['type'] == 'alipay')) {
            $type = 2; //支付宝支付
        } else {
            return json([
                'code' => 10010,
                'msg' => '不支持的支付方式'
            ]);
        }
        if ($amount <= 0) {
            return json([
                'code' => 10011,
                'msg' => '金额错误'
            ]);
        }
        $order = CheckOrderModel::where(['id' => $data['orderid']])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => "订单不存在"
            ]);
        }
        if($order->status != 2){
            return json([
                'code' => 99,
                'msg' => "该订单不需要支付"
            ]);
        }
        $price100 = bcmul($amount, 100, 0);
        if ($price100 != $order->total_price) {
            return json([
                'code' => 1,
                'msg' => "订单价格不正确"
            ]);
        }
        $check = CheckModel::where("id", $order->product_id)->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => "产品不存在"
            ]);
        }
        if (($check->supplier_status != 1) || ($check->status != 1)) {
            return json([
                'code' => 1,
                'msg' => "产品不能使用"
            ]);
        }
         if (empty($data['openid'])) {
            return json([
                'code' => 1,
                'msg' => '缺少参数openid'
            ]);
        }
        $subject = $check->name;
        $ret = [];
        $ret = (new PayService())->wxMPpay($data['orderid'], $amount, $subject, $data['modeid'], $data['openid']);
        return json($ret);
    }

    public function reportInfo()
    {
        $data = $this->request->post();
        $orderid = "";
        $title = "";
        $author = "";
        $endTime = "";
        if (empty($data['orderid'])) {
            return json([
                'code' => 1,
                'msg' => '订单号不能为空'
            ]);
        } else {
            $orderid = trim($data['orderid']);
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
        if (empty($data['title'])) {
            return json([
                'code' => 1,
                'msg' => '标题不能为空'
            ]);
        } else {
            $title = trim($data['title']);
        }
        if (empty($data['author'])) {
            return json([
                'code' => 1,
                'msg' => '作者不能为空'
            ]);
        } else {
            $author = trim($data['author']);
        }
        if ($order->product_id == "wanfangzc") {
            if (empty($data['endTime'])) {
                return json([
                    'code' => 1,
                    'msg' => '发表日期不能为空'
                ]);
            } else {
                $endTime = trim($data['endTime']);
            }
        }
        //检查长度
        $check = CheckModel::where(['id' => $order->product_id])->find();
        if (empty($check)) {
            return json([
                'code' => 1,
                'msg' => '产品不存在'
            ]);
        }
        if ($check->config['title_max'] < mb_strlen($title)) {
            return json([
                'code' => 1,
                'msg' => '标题长度超出限制' . $check->config['title_max']
            ]);
        }
        if ($check->config['author_max'] < mb_strlen($author)) {
            return json([
                'code' => 1,
                'msg' => '作者长度超出限制' . $check->config['author_max']
            ]);
        }
        //检测文章字数
        if ($order->words > 0) {
            if ($check->config['max_words'] < $order->words) {
                return json([
                    'code' => 1,
                    'msg' => "文章字数(" . $order->words . ")超出限制(" . $check->config['max_words'] . ")"
                ]);
            }
            if ($check->config['min_words'] > $order->words) {
                return json([
                    'code' => 1,
                    'msg' => "文章字数(" . $order->words . ")低于限制(" . $check->config['min_words'] . ")"
                ]);
            }
        }
        CheckOrderModel::where(['id' => $orderid])->update(['title' => $title, 'author' => $author, 'end_date' => $endTime]);
        return json([
            'code' => 0,
            'msg' => '',
        ]);
    }

    public function payquery()
    {
        $pid = $this->request->post('payid');
        $payrecord = PayRecordModel::where('id', $pid)->find();
        if (empty($payrecord)) {
            return json([
                'code' => 1,
                'msg' => '支付记录不存在'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'status' => $payrecord->status,
            ]
        ]);
    }

    public function getCheckOrderByPayId()
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

    public function deleteReport()
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
        CheckOrderModel::where(['id' => $orderid])->update(['status' => 10, 'update_time' => date('Y-m-d H:i:s')]);
        return json([
            'code' => 0,
            'msg' => ""
        ]);
    }

    //自助退款
    public function selfRefund()
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
            CheckOrderModel::where(["id" => $order->id])->update(["status" => 9, "update_time" => date('Y-m-d H:i:s')]);
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

    public function orderSync()
    {
        //处理供货失败的订单
        $flag =  Cache::get('syncorder');
        if (!empty($flag)) {
            //四分钟内处理过
            return;
        }
        Cache::set('syncorder', "1", 240);
        $time = date("Y-m-d H:i:s", strtotime("-5 minute"));
        $orders = CheckOrderModel::where([
            ['status', '=', 4]
        ])->whereTime('update_time', '<', $time)->order('create_time', 'asc')->limit(0, 10)->select();
        foreach ($orders as $order) {
            $ret =  (new Check())->payOrder($order->id, $order->title, $order->author, $order->end_date);
            if ($ret['code'] == 0) {
                CheckOrderModel::where("id", $order->id)->update(['status' => 5, 'update_time' => date('Y-m-d H:i:s')]);
            } else {
                Log::error($order->id . " 订单付款失败-" . $ret['msg']);
            }
        }
        //处理10分钟没有结果的订单
        $time = date("Y-m-d H:i:s", strtotime("-10 minute"));
        $orders = CheckOrderModel::where([
            ['status', '=', 5]
        ])->whereTime('update_time', '<', $time)->order('create_time', 'asc')->limit(0, 10)->select();
        foreach ($orders as $order) {
            $ret = (new Check())->getOrderStatus($order->id);
            Log::write($ret);
            if ($ret['code'] == 0) {
                (new CheckService())->updateStatusFromSupplier($ret['data']);
            } else {
                Log::error($order->id . " 同步订单信息失败-" . $ret['msg']);
            }
        }
        //7天未付款的订单，删除
        $time = date("Y-m-d H:i:s", strtotime("-7 day"));
        CheckOrderModel::where([
            ['status', 'IN', [1, 2, 3]]
        ])->whereTime('create_time', '<', $time)->delete();

        PayRecordModel::where(["status" => 0])->whereTime('create_time', '<', $time)->delete();

        //超过1小时没有支付成功的订单应该按照失败处理
        $time = date("Y-m-d H:i:s", strtotime("-60 minute"));
        $orders = CheckOrderModel::where([
            ['status', '=', 4]
        ])->whereTime('update_time', '<', $time)->order('create_time', 'asc')->limit(0, 10)->select();
        foreach ($orders as $order) {
            CheckOrderModel::where("id", $order->id)->update(['status' => 7, "remark" => "供货失败", 'update_time' => date('Y-m-d H:i:s')]);
        }
        echo "Success";
        exit;
    }

    public function clearReport()
    {
        //删除2年前的记录
        $time = date("Y-m-d H:i:s", strtotime("-2 year"));
        CheckOrderModel::whereTime('create_time', '<', $time)->delete();
        PayRecordModel::whereTime('create_time', '<', $time)->delete();
        //删除7天前的报告
        (new StorageService())->clean_report();
        echo "Success";
        exit;
    }
}
