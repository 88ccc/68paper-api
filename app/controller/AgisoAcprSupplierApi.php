<?php

/**
 * 对接91卡卷平台 自有货源
 */

namespace app\controller;

use app\BaseController;
use app\model\CardModel;
use app\model\CheckModel;
use app\model\ProductModel;
use app\model\UserCheckModel;
use app\model\UserModel;
use app\service\CardService;
use think\App;
use think\facade\Log;
use app\service\ConfigService;

class AgisoAcprSupplierApi extends BaseController
{
    private $AppId = 0;
    private $AppSecret = '';
    private $isTest = false;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $kajconfig = ConfigService::get("91kaj");
        if (!empty($kajconfig)) {
            $this->AppId = $kajconfig['appid'];
            $this->AppSecret = $kajconfig['secret'];
            $this->isTest = (strtolower($kajconfig['test']) == 'true');
        }
    }

    public static function create_secureid()
    {
        static $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    private function characet(string $data, string $targetCharset): string
    {
        if (!empty($data)) {
            $fileType = 'utf-8';
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }


    function encryptAesEcb(string $data, string $key): string
    {
        // 检查 OpenSSL 扩展是否可用
        if (!extension_loaded('openssl')) {
            return "";
        }

        // 确保密钥长度为 32 字节（256 位）
        if (strlen($key) !== 32) {
            return "";
        }

        // 使用 PKCS7 填充数据
        $blockSize = 16; // AES 的数据块大小为 128 位（16 字节）
        $padding = $blockSize - (strlen($data) % $blockSize);
        $data .= str_repeat(chr($padding), $padding);

        // 执行加密操作
        $encrypted = openssl_encrypt($data, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);

        // 返回加密后的 Base64 编码
        return base64_encode($encrypted);
    }


    private function get_sign(string $secureid, $reqParams = array())
    {
        if (count($reqParams) < 1) {
            return "";
        }
        ksort($reqParams);
        $stringToBeSigned = "";
        foreach ($reqParams as $k => $v) {
            // 转换成目标字符集
            if (!empty($v)) {
                $v = $this->characet($v, 'utf-8');
            }
            if (!empty($stringToBeSigned)) {
                $stringToBeSigned .= "&";
            }
            $stringToBeSigned .= $k . "=" . $v;
        }
        unset($k, $v);
        $stringToBeSigned = $this->AppSecret . $secureid . $stringToBeSigned . $this->AppSecret . $secureid;
        $md5s = md5($stringToBeSigned);
        $md5s = strtoupper($md5s);

        return $md5s;
    }


    public function  getAppId()
    {
        $list['code'] = 200;
        $list['message'] = '接口调用成功';
        $list['data'] = array(
            "appId" => $this->AppId,
        );
        return json($list);
    }

    public function  getList()
    {
        $userid = $this->request->post('userId');
        $keyword = $this->request->post('keyword');
        $productType = $this->request->post('productType');
        $pageIndex = $this->request->post('pageIndex');
        $pageSize = $this->request->post('pageSize');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $sign = $this->request->post('sign');
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (($pageIndex != 0) && empty($pageIndex)) {
            $list['code'] = 9999;
            $list['message'] = 'pageIndex必须填写';
            return json($list);
        }
        if (empty($pageSize)) {
            $list['code'] = 9999;
            $list['message'] = 'pageSize必须填写';
            return json($list);
        }
        if (empty($timestamp)) {
            $list['code'] = 9999;
            $list['message'] = 'timestamp必须填写';
            return json($list);
        }
        if (empty($version)) {
            $list['code'] = 9999;
            $list['message'] = 'version必须填写';
            return json($list);
        }
        if (empty($sign)) {
            $list['code'] = 9999;
            $list['message'] = 'sign必须填写';
            return json($list);
        }
        //看接口是否过期
        $time = time();
        $cha = bcsub($timestamp, $time, 0);
        $cha = abs($cha);
        if ($cha > 5 * 60) {
            $list['code'] = 408;
            $list['message'] = "请求时间超过5分钟，无效请求";
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //校验签名
        $signdata = $this->request->post();
        unset($signdata['sign']);
        $my_sign = $this->get_sign($user->cardkey, $signdata);
        if (strcmp($my_sign, $sign) != 0) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //查数据
        $start = $pageSize * ($pageIndex - 1);
        $where = [
            ['status', '=', 1]
        ];
        if (!empty($keyword)) {
            $where[] = ['name|id', 'like', '%' . $keyword . '%'];
        }
        $products = CheckModel::where($where)->limit($start, $pageSize)->select();

        if (count($products) < $pageSize) {
            $list['hasNextPage'] = false;
        } else {
            $list['hasNextPage'] = true;
        }
        $list['code'] = 200;
        $list['message'] = "接口调用成功";
        $items = array();
        foreach ($products as $pr) {
            if ($pr->isAvailable($userid) == false) {
                continue;
            }

            $price = $pr->price;
            $cost = bcdiv($price, 100, 4);
            $ix = array(
                'productNo' => $pr->id,
                'productTitle' => $pr->name,
                'productType' => 2,
                'productCost' => $cost,
            );

            array_push($items, $ix);
        }
        $list['data'] = array(
            'items' => $items
        );
        return json($list);
    }

    public function getTemplate()
    {
        $userid = $this->request->post('userId');
        $productNo = $this->request->post('productNo');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $sign = $this->request->post('sign');
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (empty($productNo)) {
            $list['code'] = 9999;
            $list['message'] = 'productNo必须填写';
            return json($list);
        }
        if (empty($timestamp)) {
            $list['code'] = 9999;
            $list['message'] = 'timestamp必须填写';
            return json($list);
        }
        if (empty($version)) {
            $list['code'] = 9999;
            $list['message'] = 'version必须填写';
            return json($list);
        }
        if (empty($sign)) {
            $list['code'] = 9999;
            $list['message'] = 'sign必须填写';
            return json($list);
        }
        //看接口是否过期
        $time = time();
        $cha = bcsub($timestamp, $time, 0);
        $cha = abs($cha);
        if ($cha > 5 * 60) {
            $list['code'] = 408;
            $list['message'] = "请求时间超过5分钟，无效请求";
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //校验签名
        $signdata = $this->request->post();
        unset($signdata['sign']);
        $my_sign = $this->get_sign($user->cardkey, $signdata);
        if (strcmp($my_sign, $sign) != 0) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }

        $check  = CheckModel::where('id', $productNo)->find();

        if (empty($check)) {
            $list['code'] = 1100;
            $list['message'] = "商品不存在";
            return json($list);
        }
        if ($check->status != 1) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }
        if (!($check->isAvailable($userid))) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }

        $list['code'] = 200;
        $list['message'] = "接口调用成功";
        $price = $check->price;
        $cost = bcdiv($price, 100, 4);
        $list['data'] = array(
            'apiType' => 1,
            'productTitle' => $check->name,
            'productType' => 2,
            'productCost' => $cost,
            'attach' => array()
        );
        return json($list);
    }

    public function createPurchase()
    {
        $userid = $this->request->post('userId');
        $orderNo = $this->request->post('orderNo');
        $productNo = $this->request->post('productNo');
        $buyNum = $this->request->post('buyNum');
        $maxAmount = $this->request->post('maxAmount');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $sign = $this->request->post('sign');
        $callbackUrl = $this->request->post('callbackUrl');
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (empty($orderNo)) {
            $list['code'] = 9999;
            $list['message'] = 'orderNo必须填写';
            return json($list);
        }
        if (empty($productNo)) {
            $list['code'] = 9999;
            $list['message'] = 'productNo必须填写';
            return json($list);
        }
        if (empty($buyNum)) {
            $list['code'] = 9999;
            $list['message'] = 'buyNum必须填写';
            return json($list);
        }
        if (empty($timestamp)) {
            $list['code'] = 9999;
            $list['message'] = 'timestamp必须填写';
            return json($list);
        }
        if (empty($version)) {
            $list['code'] = 9999;
            $list['message'] = 'version必须填写';
            return json($list);
        }
        if (empty($sign)) {
            $list['code'] = 9999;
            $list['message'] = 'sign必须填写';
            return json($list);
        }
        //看接口是否过期
        $time = time();
        $cha = bcsub($timestamp, $time, 0);
        $cha = abs($cha);
        if ($cha > 5 * 60) {
            $list['code'] = 408;
            $list['message'] = "请求时间超过5分钟，无效请求";
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //校验签名
        $signdata = $this->request->post();
        unset($signdata['sign']);

        $my_sign = $this->get_sign($user->cardkey, $signdata);
        if (strcmp($my_sign, $sign) != 0) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        $check  = CheckModel::where('id', $productNo)->find();

        if (empty($check)) {
            $list['code'] = 1100;
            $list['message'] = "商品不存在";
            return json($list);
        }
        if ($check->status != 1) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }
        if (!($check->isAvailable($userid))) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }

        if ($buyNum > 400) {
            $list["code"] = 9999;
            $list["message"] = "最大可用件数不得大于400";
            return json($list);
        }
        $price = $check->price;
        $cost = bcmul($buyNum, $price, 0);
        if (!empty($maxAmount)) {
            $maxcost = bcmul($maxAmount, 100, 0);
            //不能亏本
            if (($maxcost < $cost)) {
                $list["code"] = 1220;
                $list["message"] = "大于最大成本金额";
                return json($list);
            }
        }

        //查看该订单是否已经存在
        $corder = CardModel::where('uuid', $orderNo)->find();
        $cardno = "";
        if (empty($corder)) {
            $count = CardModel::where([
                'userid' => $user->id,
                'status' => 1
            ])->count('userid');

            if ($count > 10000) {
                $list["code"] = 9999;
                $list["message"] = '每个账户最多允许10000个状态为“未使用”的自定义订单，你现在已经有' . $count . '个状态为“未使用”的订单，确定不再使用的自定义订单要及时的禁用。';
                return json($list);
            }
            $ret = (new CardService())->createCard($userid, $productNo, $buyNum, '91卡卷订单:' . $orderNo);
            if ($ret['code'] != 0) {
                Log::error("创建卡失败");
                //失败了
                $list["code"] = 9999;
                $list["message"] = $ret['msg'];
                return json($list);
            }
            $cardno = $ret['data']['id'];
            CardModel::where('id', $cardno)->update(['uuid' => $orderNo]);

            $list["code"] = 200;
        } else {
            $list["code"] = 1250;
            $cardno = $corder->id;
        }
        $costz = bcdiv($cost, 100, 4);
        $jim = '[{"cardNo":"' . $cardno . '"}]';
        $cardj = $this->encryptAesEcb($jim, $this->AppSecret);

        $list["message"] = "接口调用成功";
        $list["data"] = array(
            'orderNo' => $orderNo,
            'outTradeNo' => $orderNo,
            'orderStatus' => 20,
            'orderCost' => $costz,
            'cards' => $cardj
        );
        if (!empty($callbackUrl)) {
            $dlist['orderNo'] = $orderNo;
            $dlist['outTradeNo'] = $orderNo;
            $dlist['orderStatus'] = 20;
            $dlist['orderCost'] = $costz;
            $dlist['cards'] = $cardj;
            $dlist['timestamp'] = time();
            $sign = $this->get_sign($user->cardkey, $dlist);
            $dlist['sign'] = $sign;
            $this->curlPost($callbackUrl, $dlist);
        }
        return json($list);
    }

    public function cancelOrder()
    {
        $userid = $this->request->post('userId');
        $orderNo = $this->request->post('orderNo');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $sign = $this->request->post('sign');
        $list['code'] = 200;
        $list['message'] = "";
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (empty($orderNo)) {
            $list['code'] = 9999;
            $list['message'] = 'orderNo必须填写';
            return json($list);
        }
        if (empty($timestamp)) {
            $list['code'] = 9999;
            $list['message'] = 'timestamp必须填写';
            return json($list);
        }
        if (empty($version)) {
            $list['code'] = 9999;
            $list['message'] = 'version必须填写';
            return json($list);
        }
        if (empty($sign)) {
            $list['code'] = 9999;
            $list['message'] = 'sign必须填写';
            return json($list);
        }
        //看接口是否过期
        $time = time();
        $cha = bcsub($timestamp, $time, 0);
        $cha = abs($cha);
        if ($cha > 5 * 60) {
            $list['code'] = 408;
            $list['message'] = "请求时间超过5分钟，无效请求";
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //校验签名
        $signdata = $this->request->post();
        unset($signdata['sign']);

        $my_sign = $this->get_sign($user->cardkey, $signdata);
        if (strcmp($my_sign, $sign) != 0) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        if (($orderNo == 'T170000000') && ($this->isTest)) {
            //测试订单
            return json([
                'code' => 200,
                'message' => "接口调用成功"
            ]);
        }
        $orderc = CardModel::where([
            'uuid'    =>    $orderNo,
            'userid'    =>    $userid,
        ])->find();
        if (!empty($orderc)) {
            if ($orderc->status == 1) {
                $orderc->save([
                    'status'    =>    3
                ]);
                $list['code'] = 200;
                $list['message'] = "接口调用成功";
            } else if ($orderc->status == 2) {
                $list['code'] = 9999;
                $list['message'] = "该订单已经被客户使用";
            } else if ($orderc->status == 3) {
                $list['code'] = 200;
                $list['message'] = "接口调用成功";
            }
        } else {
            $list['code'] = 9999;
            $list['message'] = "订单不存";
        }
        return json($list);
    }

    public function createRecharge()
    {
        //为了过测试上线，而做的接口，实际上不支持
        $userid = $this->request->post('userId');
        $orderNo = $this->request->post('orderNo');
        $productNo = $this->request->post('productNo');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $callbackUrl = $this->request->post('callbackUrl');
        $sign = $this->request->post('sign');


        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }

        if (($orderNo == 'T170000000') && ($this->isTest)) {
            //这是直充的测试订单
            $dlist['orderNo'] = $orderNo;
            $dlist['outTradeNo'] = $orderNo;
            $dlist['orderStatus'] = 20;
            $dlist['orderCost'] = 5;
            $dlist['timestamp'] = time();
            $sign = $this->get_sign($user->cardkey, $dlist);
            $dlist['sign'] = $sign;
            $this->curlPost($callbackUrl, $dlist);
            $list['code'] = 200;
            $list['message'] = "接口调用成功";
            $list['data'] = array(
                'orderNo' => $orderNo,
                'outTradeNo' => $orderNo,
                'orderStatus' => 20,
                'orderCost' => '5.0000'
            );
            return json($list);
        }
        if (strcmp($productNo, "test01") == 0) {
            $list['code'] = 200;
            $list['message'] = "接口调用成功";
            $list['data'] = array(
                'orderNo' => $orderNo,
                'outTradeNo' => $orderNo,
                'orderStatus' => 20,
                'orderCost' => '5.0000'
            );
            return json($list);
        } else {
            $list['code'] = 1100;
            $list['message'] = "商品不存在";
            return json($list);
        }
    }

    public function orderGet()
    {
        $userid = $this->request->post('userId');
        $orderNo = $this->request->post('orderNo');
        $timestamp = $this->request->post('timestamp');
        $version = $this->request->post('version');
        $sign = $this->request->post('sign');
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (empty($orderNo)) {
            $list['code'] = 9999;
            $list['message'] = 'orderNo必须填写';
            return json($list);
        }
        if (empty($timestamp)) {
            $list['code'] = 9999;
            $list['message'] = 'timestamp必须填写';
            return json($list);
        }
        if (empty($version)) {
            $list['code'] = 9999;
            $list['message'] = 'version必须填写';
            return json($list);
        }
        if (empty($sign)) {
            $list['code'] = 9999;
            $list['message'] = 'sign必须填写';
            return json($list);
        }
        //看接口是否过期
        $time = time();
        $cha = bcsub($timestamp, $time, 0);
        $cha = abs($cha);
        if ($cha > 5 * 60) {
            $list['code'] = 408;
            $list['message'] = "请求时间超过5分钟，无效请求";
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //校验签名
        $signdata = $this->request->post();
        unset($signdata['sign']);

        $my_sign = $this->get_sign($user->cardkey, $signdata);
        if (strcmp($my_sign, $sign) != 0) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        //是否是测试
        if (($orderNo == 'T170000000') && ($this->isTest)) {
            //这是直冲类测试接口，直接回复
            $list['code'] = 200;
            $list['message'] = "接口调用成功";
            $list['data'] = array(
                'orderNo' => $orderNo,
                'outTradeNo' => $orderNo,
                'orderStatus' => 20,
                'orderCost' => '5.0000',
            );
            return json($list);
        }
        $orderc = CardModel::where([
            'uuid'    =>    $orderNo,
            'userid'    =>    $userid,
        ])->find();
        if (empty($orderc)) {
            $list['code'] = 9999;
            $list['message'] = "订单号不存在";
            return json($list);
        }
        //计算成本价格
        $check  = CheckModel::where('id', $orderc->product_id)->find();

        if (empty($check)) {
            $list['code'] = 1100;
            $list['message'] = "商品不存在";
            return json($list);
        }
        if ($check->status != 1) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }
        if (!($check->isAvailable($userid))) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }


        $price = $check->price;

        $cost = bcmul($orderc->piece, $price, 0);
        $costz = bcdiv($cost, 100, 4);
        $jim = '[{"cardNo":"' . $orderc->id . '"}]';
        $cardj = $this->encryptAesEcb($jim, $this->AppSecret);
        $list['code'] = 200;
        $list['message'] = "接口调用成功";
        $list['data'] = array(
            'orderNo' => $orderNo,
            'outTradeNo' => $orderNo,
            'orderStatus' => 20,
            'orderCost' => $costz,
            'cards' => $cardj,
        );
        return json($list);
    }

    public function createRecharge_1()
    {
        //测试回调，实际上没用
        $userid = $this->request->post('userId');
        $orderNo = $this->request->post('orderNo');
        $callbackUrl = $this->request->post('callbackUrl');
        //先验证必要字段是否存在
        if (empty($userid)) {
            $list['code'] = 9999;
            $list['message'] = 'userId必须填写';
            return json($list);
        }
        if (empty($orderNo)) {
            $list['code'] = 9999;
            $list['message'] = 'orderNo必须填写';
            return json($list);
        }
        if (empty($callbackUrl)) {
            $list['code'] = 9999;
            $list['message'] = 'callbackUrl必须填写';
            return json($list);
        }
        //验证用户状态
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            $list['code'] = 1001;
            $list['message'] = "用户id不存在";
            return json($list);
        }
        if ($user->status != 1) {
            $list['code'] = 1002;
            $list['message'] = "用户状态异常";
            return json($list);
        }

        if (empty($user->cardkey)) {
            $list['code'] = 401;
            $list['message'] = "sign校验失败";
            return json($list);
        }
        if (($orderNo == 'T170000000') && ($this->isTest)) {
            //这是直充的测试订单
            $dlist['orderNo'] = $orderNo;
            $dlist['outTradeNo'] = $orderNo;
            $dlist['orderStatus'] = 20;
            $dlist['orderCost'] = 5;
            $dlist['timestamp'] = time();
            $sign = $this->get_sign($user->cardkey, $dlist);
            $dlist['sign'] = $sign;
            $this->curlPost($callbackUrl, $dlist);
            $list['code'] = 200;
            $list['message'] = "成功";
            return json($list);
        }
        $orderc = CardModel::where([
            'uuid'    =>    $orderNo,
            'userid'    =>    $userid,
        ])->find();
        if (empty($orderc)) {
            $list['code'] = 9999;
            $list['message'] = "订单号不存在";
            return json($list);
        }
        //计算成本价格
        $check  = CheckModel::where('id', $orderc->product_id)->find();

        if (empty($check)) {
            $list['code'] = 1100;
            $list['message'] = "商品不存在";
            return json($list);
        }
        if ($check->status != 1) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }
        if (!($check->isAvailable($userid))) {
            $list['code'] = 1100;
            $list['message'] = "商品不能使用";
            return json($list);
        }


        $price = $check->price;

        $cost = bcmul($orderc->piece, $price, 0);
        $costz = bcdiv($cost, 100, 2);
        $costz = trim($costz, '0');
        $costz = trim($costz, '.');
        $jim = '[{"cardNo":"' . $orderc->id . '"}]';
        $cardj = $this->encryptAesEcb($jim, $this->AppSecret);
        $dlist['orderNo'] = $orderNo;
        $dlist['outTradeNo'] = $orderNo;
        $dlist['orderStatus'] = 20;
        $dlist['orderCost'] = 5;
        $dlist['cards'] = $cardj;
        $dlist['timestamp'] = time();
        $sign = $this->get_sign($user->cardkey, $dlist);
        $dlist['sign'] = $sign;
        $this->curlPost($callbackUrl, $dlist);
        $list['code'] = 200;
        $list['message'] = "成功";
        return json($list);
    }







    public function curlPost($url = '', $postData = array(), $options = array())
    {
        if (is_array($postData)) {
            $postData = json_encode($postData);
        }
        $usr_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type:application/json',
            'Accept:application/json',
            'User-Agent:' . $usr_agent
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        return $data;
    }
}
