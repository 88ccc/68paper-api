<?php

namespace app\controller;

use app\BaseController;
use app\model\AdminModel;
use app\model\ArticleModel;
use app\model\AttachModel;
use app\model\BalanceModel;
use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\PayRecordModel;
use app\model\PaySetModel;
use app\model\UserModel;
use app\service\ConfigService;
use app\service\WxPublicService;
use think\facade\Log;
use app\model\SmsModel;
use app\model\EmailModel;
use app\model\UserWebModel;
use app\model\WithdrawModel;
use app\service\CheckService;
use app\supplier\Check;
use app\service\PayService;
use app\service\StorageService;
use think\facade\Cache;
use think\facade\Config;

class Index extends BaseController
{
    public function index()
    {
        return '欢迎使用 V2.0.0';
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
                'avatar' => $user->avatar
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

    public function getSaleWebConfig()
    {
        $config = ConfigService::get("sale_web");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置销售网站信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    public function getInviteConfig()
    {

        $config = ConfigService::get("invite");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置邀请信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    public function getExtensionsConfig()
    {

        $config = ConfigService::get("extensions");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置邀请信息'
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
        $config1 = ConfigService::get("website");
        if (!empty($config1)) {
            $list['data']['website'] = $config1;
        }
        $config3 = ConfigService::get("custom");
        if (!empty($config3)) {
            $list['data']['custom'] = $config3;
        }

        $config2 = ConfigService::get("loginRegister");
        if (!empty($config2)) {
            $list['data']['loginRegister'] = $config2;
        }

        //是否启用了短信
        $sms = ConfigService::get("sms");
        if (!empty($sms)) {
            $list['data']['sms'] = true;
        } else {
            $list['data']['sms'] = false;
        }
        //是否启用了邮件
        $email = ConfigService::get("email");
        if (!empty($email)) {
            $list['data']['email'] = true;
        } else {
            $list['data']['email'] = false;
        }

        //是否启用了公众号
        $config4 = ConfigService::get("wxpublic");
        if (!empty($config4)) {
            $list['data']['wechat'] = true;
        } else {
            $list['data']['wechat'] = false;
        }
        $config5 = ConfigService::get("sale_web");
        if (!empty($config5)) {
            $list['data']['saleWeb'] = $config5;
        }
        $config6 = ConfigService::get("invite");
        if (!empty($config6)) {
            $list['data']['invite'] = $config6;
        }
        $adminUrl = Config::get('website.admin_domain');
        if (!empty($adminUrl)) {
            $list['data']['adminUrl'] = $adminUrl;
        }
        $config7 = ConfigService::get("function");
        if (!empty($config7)) {
            $list['data']['function'] = $config7;
        }
        $list['data']['ecommerce'] = false;
        $config8 = ConfigService::get("91kaj");
        if (!empty($config8)) {
            $list['data']['ecommerce'] = strtolower($config8['test']) != 'true';
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

    public function downAttachFile()
    {
        $shopid = $this->request->get("userid");
        $attach = AttachModel::where('id', $shopid)->find();
        if (empty($attach)) {
            return json([
                'code' => 1,
                'msg' => '附件不存在'
            ]);
        }
        $file_path =  $attach->file_path;
        if (empty($attach->file_name)) {
            return json([
                'code' => 1,
                'msg' => '文件不存在'
            ]);
        }
        if ($attach->file_status != 1 && $attach->file_status != 2) {
            return json([
                'code' => 1,
                'msg' => '文件不可用'
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
            Header("Content-Disposition: attachment;filename=" . $attach->file_name);
            ob_clean();     // 重点！！！
            flush();        // 重点！！！！可以清除文件中多余的路径名以及解决乱码的问题：
            //输出文件内容
            //读取文件内容并直接输出到浏览器
            echo fread($file1, filesize($file_path));
            fclose($file1);
            return;
        }
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
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
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
        if ($order->status != 2) {
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
        $subject = $check->name . "(销售:" . $order->userid . ")";
        $ret = (new PayService())->getQRcode($data['modeid'], 1, $order->userid, $type, $amount, $data['orderid'], $subject);
        return json($ret);
    }

    public function getH5Pay()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
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
        if ($order->status != 2) {
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
        $subject = $check->name . "(销售:" . $order->userid . ")";
        $ret = [];
        $return_url = "";
        if (!empty($data['returnUrl'])) {
            $return_url = $data['returnUrl'];
        }
        if ($type == 1) {
            $ip = $this->request->ip();
            $ret = (new PayService())->wxH5pay($data['orderid'], 1, $order->userid, $amount, $subject, $data['modeid'], $ip);
        } else if ($type == 2) {
            $ret = (new PayService())->aliH5pay($data['orderid'], 1, $order->userid, $amount, $subject, $data['modeid'], $return_url);
        }

        return json($ret);
    }

    //微信内部，jsap支付
    public function getMPpay()
    {
        if (Config::get('website.is_test')) {
            return json([
                'code' => 1,
                'msg' => '测试环境不支持'
            ]);
        }
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
        if ($order->status != 2) {
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
        $subject = $check->name . "(销售:" . $order->userid . ")";
        $ret = [];
        $ret = (new PayService())->wxMPpay($data['orderid'], 1, $order->userid, $amount, $subject, $data['modeid'], $data['openid']);
        return json($ret);
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

    public function getLoginRegisterConfig()
    {
        //登录注册配置
        $config = ConfigService::get("loginRegister");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置登录注册信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'data' => $config
            ]);
        }
    }

    //发送短信验证吗
    public function sendSmsCode()
    {
        $phone = $this->request->param('phone');
        $userid = $this->request->param('userid', '0');
        if (empty($phone)) {
            return json([
                'code' => 1,
                'msg' => '请输入手机号'
            ]);
        }
        $sms = new SmsModel();
        $result = $sms->sendCode($phone, $userid);
        return json($result);
    }
    //发送邮箱验证码
    public function sendEmailCode()
    {
        $email = $this->request->param('email');
        $userid = $this->request->param('userid', '0');
        $isReg_input = $this->request->param('isReg', 'true');
        //判断
        $isReg = false;
        if (is_bool($isReg_input)) {
            $isReg = $isReg_input;
        } else {
            if (is_string($isReg_input)) {
                $isReg = strtolower($isReg_input) == 'true';
            }
        }
        if (empty($email)) {
            return json([
                'code' => 1,
                'msg' => '请输入邮箱'
            ]);
        }
        $sms = new EmailModel();
        $result = $sms->sendCode($email, $userid, $isReg);
        return json($result);
    }

    public function register()
    {
        // 用户帐号 可能是手机号也可能是邮箱
        $account = $this->request->param('account');
        $password = $this->request->param('password');
        $code = $this->request->param('code');
        $tid = $this->request->param('tid', '0');
        $openid = $this->request->param('openid', '');
        if (empty($account)) {
            return json([
                'code' => 1,
                'msg' => '请输入手机号或邮箱'
            ]);
        }
        if (empty($password)) {
            return json([
                'code' => 1,
                'msg' => '请输入密码'
            ]);
        }
        if (empty($code)) {
            return json([
                'code' => 1,
                'msg' => '请输入验证码'
            ]);
        }
        $phone = "";
        $email = "";

        //判断是手机号还是邮箱
        if (is_numeric($account)) {
            $phone = $account;
            $sms = new SmsModel();
            $result = $sms->verifyCode($account, $code);
            if ($result['code'] != 0) {
                return json($result);
            }
        } else {
            $email = $account;
            $emailM = new EmailModel();
            $result = $emailM->verifyCode($account, $code);
            if ($result['code'] != 0) {
                return json($result);
            }
        }
        $ret = UserModel::add($phone, $email, $password, intval($tid), $openid);
        return json($ret);
    }
    //用户登录
    public function login()
    {
        $account = $this->request->param('account');
        $password = $this->request->param('password');
        $remember = $this->request->param('remember', false);
        if (empty($account)) {
            return json([
                'code' => 1,
                'msg' => '请输入手机号或邮箱'
            ]);
        }
        if (empty($password)) {
            return json([
                'code' => 1,
                'msg' => '请输入密码'
            ]);
        }
        // 判断是手机号还是邮箱
        if (is_numeric($account)) {
            $user = UserModel::where('mobile', $account)->find();
        } else {
            $user = UserModel::where('email', $account)->find();
        }
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
        if ($user->status != 1) {
            return json([
                'code' => 1,
                'msg' => '用户被禁用'
            ]);
        }
        $expireTime = 24; //小时
        if ($remember) {
            $expireTime = 168; //周
        }
        $token = $user->getAuth($user->id, $expireTime);
        $domain = "";
        $userweb =  UserWebModel::where('userid', $user->id)->find();
        if (!empty($userweb)) {
            $domain = $userweb->webid;
        }
        return json([
            'code' => 0,
            'msg' => '登录成功',
            'data' => [
                'id' => $user->id,
                'token' => $token['jwt'],
                'name' => $user->name,
                'phone' => $user->mobile,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'domain' => $domain,
                'pay_type' => $user->pay_type
            ]

        ]);
    }

    //重置密码
    public function resetPassword()
    {
        $account = $this->request->param('account');
        $code = $this->request->param('code');
        $password = $this->request->param('password');
        if (empty($account)) {
            return json([
                'code' => 1,
                'msg' => '请输入手机号或邮箱'
            ]);
        }
        if (empty($code)) {
            return json([
                'code' => 1,
                'msg' => '请输入验证码'
            ]);
        }
        if (empty($password)) {
            return json([
                'code' => 1,
                'msg' => '请输入密码'
            ]);
        }
        //判断是手机号还是邮箱
        //判断是手机号还是邮箱
        if (is_numeric($account)) {
            $phone = $account;
            $sms = new SmsModel();
            $result = $sms->verifyCode($account, $code);
            if ($result['code'] != 0) {
                return json($result);
            }
            $user = UserModel::where('mobile', $phone)->find();
        } else {
            $email = $account;
            $emailM = new EmailModel();
            $result = $emailM->verifyCode($account, $code);
            if ($result['code'] != 0) {
                return json($result);
            }
            $user = UserModel::where('email', $email)->find();
        }
        if (empty($user)) {
            return json([
                'code' => 1,
                'msg' => '用户不存在'
            ]);
        }
        $user->pass = md5($password);
        try {
            $result = $user->save();
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => '修改密码失败'
            ]);
        }
        if ($result) {
            return json([
                'code' => 0,
                'msg' => '修改密码成功'
            ]);
        } else {
            return json([
                'code' => 1,
                'msg' => '修改密码失败'
            ]);
        }
    }

    public function down_attachment()
    {
        $userid = $this->request->get('id');
        if (empty($userid)) {
            return json([
                'code' => 1,
                'msg' => '请输入用户id'
            ]);
        }
        $attach = AttachModel::where('userid', $userid)->find();
        if (empty($attach)) {
            return json([
                'code' => 1,
                'msg' => '文件不存在'
            ]);
        }
        if ($attach->file_status == 0 || $attach->file_status == 3 || $attach->file_status == 4) {
            $list['code'] = 1;
            $list['msg'] = '状态不正确，文件不存在';
            return json($list);
        }
        if (empty($attach->file_name)) {
            $list['code'] = 1;
            $list['msg'] = '状态不正确，文件名称为空';
            return json($list);
        }

        $file_name = $attach->file_name;


        $file_path =  $attach->file_path;

        if (!file_exists($file_path)) {
            $list['code'] = 1;
            $list['msg'] = '文件不存在' . $file_path;
            return json($list);
        } else {
            // 打开文件
            $file1 = fopen($file_path, "r");
            // 输入文件标签
            Header("Content-type: application/octet-stream");
            Header("Accept-Ranges: bytes");
            Header("Accept-Length:" . filesize($file_path));
            Header("Content-Disposition: attachment;filename=" . $file_name);
            ob_clean();     // 重点！！！
            flush();        // 重点！！！！可以清除文件中多余的路径名以及解决乱码的问题：
            //输出文件内容
            //读取文件内容并直接输出到浏览器
            echo fread($file1, filesize($file_path));
            fclose($file1);
            return;
        }
    }

    public function getJsapDomain()
    {
        return json([
            'code' => 0,
            'msg' => '',
            'data' => [
                'url' => Config::get('website.admin_domain')
            ]
        ]);
    }

    public function getOrderInfo()
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
        $order = CheckOrderModel::where(['id' => $orderid])->withoutField(['original', 'cost', 'pcost', 'ppiece', 'profit', 'pprofit', 'tprofit', 'lock', 'file_key', 'lock_time'])->find();
        if (empty($order)) {
            return json([
                'code' => 1,
                'msg' => '订单不存在'
            ]);
        }
        return json([
            'code' => 0,
            'msg' => '',
            'data' => $order
        ]);
    }

    public function getCheckIdAndName()
    {

        $count = CheckModel::count();
        $products = CheckModel::field('id,name')->select();
        $list['code'] = 0;
        $list["count"] = $count;
        $list["data"] = $products;
        return json($list);
    }

    public function getNotice()
    {
        $notice = ArticleModel::where('id', 'notice')->find();
        if (empty($notice)) {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => ''
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $notice->content
        ]);
    }
    public function getPrivacyPolicy()
    {
        $articl = ArticleModel::where('id', 'privacyPolicy')->find();
        if (empty($articl)) {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => ''
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $articl->content
        ]);
    }

    public function getUserAgreement()
    {
        $articl = ArticleModel::where('id', 'userAgreement')->find();
        if (empty($articl)) {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => ''
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '',
            'data' => $articl->content
        ]);
    }

    public function getWithdrawConfig()
    {
        $config = ConfigService::get("withdraw");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置提现信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }
    public function getWebsiteConfig()
    {
        //网站配置
        $config = ConfigService::get("website");
        if (empty($config)) {
            return json([
                'code' => 10000,
                'msg' => '请先配置网站信息'
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '',
                'data' => $config
            ]);
        }
    }
    public function orderSync()
    {
        //处理供货失败的订单
        $flag =  Cache::get('syncorder');
        if (!empty($flag)) {
            //三分钟内处理过
            return;
        }
        Cache::set('syncorder', "1", 180);
        $time = date("Y-m-d H:i:s", strtotime("-5 minute"));
        $orders = CheckOrderModel::where([
            ['status', 'in', [4, 6]]
        ])->whereTime('update_time', '<', $time)->order('create_time', 'asc')->limit(0, 50)->select();
        foreach ($orders as $order) {
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
            $ret =  (new Check())->payOrder($order->id, $check_data);
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
        ])->whereTime('update_time', '<', $time)->order('create_time', 'asc')->limit(0, 50)->select();
        foreach ($orders as $order) {
            $ret = (new Check())->getOrderStatus($order->id);
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

        //长期支付失败
        $time = date("Y-m-d H:i:s", strtotime("-40 minute"));
        $orders = CheckOrderModel::where([
           ['status', 'in', [4, 6]]
        ])->whereTime('pay_time', '<', $time)->order('create_time', 'asc')->limit(0, 50)->select();
        foreach ($orders as $order) {
            CheckOrderModel::where("id", $order->id)->update(['status' => 7, "remark" => "供货失败", 'update_time' => date('Y-m-d H:i:s')]);
        }
        echo "Success";
        exit;
    }

    public function clearReport()
    {
        $check = 1;
        $amount = 1;
        $config = ConfigService::get("cache_set");
        if (!empty($config)) {
            if (isset($config['check'])) {
                $temp = intval($config['check']);
                if ($temp > $check) {
                    $check = $temp;
                }
            }
            if (isset($config['amount'])) {
                $temp = intval($config['amount']);
                if ($temp > $amount) {
                    $amount = $temp;
                }
            }
        }
        //删除2年前的记录
        $time = date("Y-m-d H:i:s", strtotime("-" . $check . " year"));
        CheckOrderModel::whereTime('update_time', '<', $time)->delete();
        PayRecordModel::whereTime('create_time', '<', $time)->delete();
        EmailModel::whereTime('update_time', '<', $time)->delete();
        SmsModel::whereTime('update_time', '<', $time)->delete();
        //删除5年前的记录
        $time = date("Y-m-d H:i:s", strtotime("-" . $amount . " year"));
        BalanceModel::whereTime('create_time', '<', $time)->delete();
        WithdrawModel::whereTime('create_time', '<', $time)->delete();

        //删除7天前的报告
        (new StorageService())->clean_report();
        echo "Success";
        exit;
    }
}
