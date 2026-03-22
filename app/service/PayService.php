<?php
// 自定义工具类 ConfigService.php
namespace app\service;

use app\model\CheckOrderModel;
use app\model\PayModeModel;
use think\facade\Config;
use Yansongda\Pay\Pay;
use think\facade\Log;
use app\model\PayRecordModel;
use app\model\UserModel;
use app\supplier\Check;

class PayService

{
    public function getConfig($modeid)
    {

        $payMode = PayModeModel::where('id', $modeid)->find();
        if (empty($payMode)) {
            return [];
        }
        if (empty($payMode->type)) {
            return [];
        }
        if (($payMode->type != 'wxpay') && ($payMode->type != 'alipay')) {
            return [];
        }
        if ($payMode->type == 'wxpay') {
            return [
                'wechat' => [
                    'default' => [
                        // 「必填」商户号，服务商模式下为服务商商户号
                        // 可在 https://pay.weixin.qq.com/ 账户中心->商户信息 查看
                        'mch_id' => $payMode->mchid,

                        // 「必填」v3 商户秘钥
                        // 即 API v3 密钥(32字节，形如md5值)，可在 账户中心->API安全 中设置
                        'mch_secret_key' => $payMode->mchkey,
                        // 「必填」商户私钥 字符串或路径
                        // 即 API证书 PRIVATE KEY，可在 账户中心->API安全->申请API证书 里获得
                        // 文件名形如：apiclient_key.pem
                        'mch_secret_cert' => root_path() . '/cert/' . $payMode->mchsecretpath,
                        // 「必填」商户公钥证书路径
                        // 即 API证书 CERTIFICATE，可在 账户中心->API安全->申请API证书 里获得
                        // 文件名形如：apiclient_cert.pem
                        'mch_public_cert_path' => root_path() . '/cert/' . $payMode->mchpublicpath,
                        // 「必填」微信回调url
                        // 不能有参数，如?号，空格等，否则会无法正确回调
                        'notify_url' => Config::get('website.api_domain') . '/notify/wxpay/' . $modeid,
                        // 「选填」公众号 的 app_id
                        // 可在 mp.weixin.qq.com 设置与开发->基本配置->开发者ID(AppID) 查看
                        'mp_app_id' => $payMode->appid,
                        // 「选填」默认为正常模式。可选为： MODE_NORMAL, MODE_SERVICE
                        'mode' => Pay::MODE_NORMAL,
                    ]
                ],

                'logger' => [
                    'enable' => false,
                    'file' => './logs/pay.log',
                    'level' => 'info', // 建议生产环境等级调整为 info，开发环境为 debug
                    'type' => 'single', // optional, 可选 daily.
                    'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
                ],
                'http' => [ // optional
                    'timeout' => 5.0,
                    'connect_timeout' => 5.0,
                ],
                '_force' => true
            ];
        } else if ($payMode->type == 'alipay') {
            return [
                'alipay' => [
                    'default' => [
                        // 「必填」支付宝分配的 app_id
                        'app_id' =>  $payMode->appid,
                        // 「必填」应用私钥 字符串或路径
                        // 在 https://open.alipay.com/develop/manage 《应用详情->开发设置->接口加签方式》中设置
                        'app_secret_cert' => $payMode->appsecret,
                        // 「必填」应用公钥证书 路径
                        // 设置应用私钥后，即可下载得到以下3个证书
                        'app_public_cert_path' => root_path() . '/cert/' . $payMode->apppublicpath,
                        // 「必填」支付宝公钥证书 路径
                        'alipay_public_cert_path' => root_path() . '/cert/' . $payMode->alipublicpath,
                        // 「必填」支付宝根证书 路径
                        'alipay_root_cert_path' => root_path() . '/cert/' . $payMode->alirootpath,
                        //'return_url' => 'https://yansongda.cn/alipay/return',
                        'notify_url' => Config::get('website.api_domain') . '/notify/alipay/' . $modeid,
                        // 「选填」默认为正常模式。可选为： MODE_NORMAL, MODE_SANDBOX, MODE_SERVICE
                        'mode' => Pay::MODE_NORMAL,
                    ]
                ],

                'logger' => [
                    'enable' => false,
                    'file' => './logs/pay.log',
                    'level' => 'info', // 建议生产环境等级调整为 info，开发环境为 debug
                    'type' => 'single', // optional, 可选 daily.
                    'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
                ],
                'http' => [ // optional
                    'timeout' => 5.0,
                    'connect_timeout' => 5.0,
                ],
                '_force' => true
            ];
        }
    }

    /**
     * 生成基础随机字符串（字母+数字）
     * @param int $length 字符串长度
     * @return string 随机字符串
     */
    function generateRandomString($length = 10)
    {
        // 定义字符集：大小写字母 + 数字
        $chars = '3456789ABCDEFGHKLMNPQRSTUVWXY';
        $charLength = strlen($chars);
        $randomString = '';

        // 验证长度有效性
        if ($length <= 0) {
            return $randomString;
        }

        // 循环生成指定长度的字符串
        for ($i = 0; $i < $length; $i++) {
            // 从字符集中随机取一个字符（mt_rand性能更好）
            $randomString .= $chars[mt_rand(0, $charLength - 1)];
        }

        return $randomString;
    }

    /**
     * 微信浏览器内调用微信支付
     */
    public function wxMPpay($orderid, $price, $subject, $modeid, $openid)
    {
        $out_trade_no = 'WM' . time() . $this->generateRandomString(5);
        //金额单位分
        $price100 = bcmul($price, 100, 0);
        $order = [
            'out_trade_no' => $out_trade_no,
            'description' => $subject,
            'amount' => [
                'total' => intval($price100),
            ],
            'payer' => [
                'openid' => $openid,
            ]
        ];
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }
        try {
            $result = Pay::wechat($config)->mp($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }

        //记录订单
        $order = [
            'id' => $out_trade_no,
            'orderid' => $orderid,
            'method' => 'wechat',
            'modeid' => $modeid,
            'type' => 'jsapi',
            'subject' => $subject,
            'price' => $price,
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        PayRecordModel::insert($order);
        return [
            'code' => 0,
            'msg' => '微信下单成功！',
            'data' => [
                'payid' => $out_trade_no,
                'appId' => $result->appId,
                'timeStamp' => $result->timeStamp,
                'nonceStr' => $result->nonceStr,
                'package' => $result->package,
                'signType' => $result->signType,
                'paySign' => $result->paySign
            ]
        ];
    }

    public function wxScanpay($orderid, $price, $subject, $modeid)
    {
        $out_trade_no = 'WS' . time() . $this->generateRandomString(5);
        //金额单位分
        $price100 = bcmul($price, 100, 0);

        $order = [
            'out_trade_no' => $out_trade_no,
            'description' => $subject,
            'amount' => [
                'total' => intval($price100),
            ],
        ];
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }
        try {
            $result = Pay::wechat($config)->scan($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }

        $qr = $result->code_url;
        if (empty($qr)) {
            Log::error('微信扫码下单失败！');
            Log::write($result->toArray());
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }
        //记录订单
        $order = [
            'id' => $out_trade_no,
            'orderid' => $orderid,
            'method' => 'wechat',
            'modeid' => $modeid,
            'type' => 'native',
            'subject' => $subject,
            'price' => $price,
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        PayRecordModel::insert($order);
        return [
            'code' => 0,
            'msg' => '微信下单成功！',
            'out_trade_no' => $out_trade_no,
            'qr' => $qr,

        ];
    }

    public function aliRefund($payid, $amount, $modeid, $type)
    {
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '支付宝退款失败！'
            ];
        }
        $order = [
            'out_trade_no' => $payid,
            'refund_amount' => $amount,
            '_action' => $type,

        ];
        try {
            $result = Pay::alipay($config)->refund($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '支付宝退款失败！'
            ];
        }
        $rarr = $result->toArray();
        //Log::write($rarr);
        if (isset($rarr['code'])) {
            if ($rarr['code'] == "10000") {
                //退款成功
                return [
                    'code' => 0,
                    'msg' => "退款成功"
                ];
            }
        }
        Log::error("退款失败 " . $payid);
        Log::write($rarr);
        return [
            'code' => 1,
            'msg' => "退款失败"
        ];
    }

    public function aliScanpay($orderid, $price, $subject, $modeid)
    {
        $out_trade_no = 'AS' . time() . $this->generateRandomString(5);

        $order = [
            'out_trade_no' => $out_trade_no,
            'total_amount' => $price,
            'subject' => $subject,
        ];
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '支付宝下单失败！'
            ];
        }

        try {
            $result = Pay::alipay($config)->scan($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '支付宝下单失败！'
            ];
        }
        //Log::write($result->toArray());



        $qr = $result->qr_code;
        if (empty($qr)) {
            Log::error('支付宝扫码下单失败！');
            Log::write($result->toArray());
            return [
                'code' => 1,
                'msg' => '支付宝下单失败！'
            ];
        }
        //记录订单
        $order = [
            'id' => $out_trade_no,
            'orderid' => $orderid,
            'method' => 'alipay',
            'modeid' => $modeid,
            'type' => 'scan',
            'subject' => $subject,
            'price' => $price,
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        PayRecordModel::insert($order);
        return [
            'code' => 0,
            'msg' => '支付宝下单成功！',
            'out_trade_no' => $out_trade_no,
            'qr' => $qr,

        ];
    }

    //支付宝H5支付
    public function aliH5pay($orderid, $price, $subject, $modeid, $return_url)
    {
        $out_trade_no = 'AH' . time() . $this->generateRandomString(5);
        $order = [
            'out_trade_no' => $out_trade_no,
            'total_amount' => $price,
            'subject' => $subject,
        ];
        if (!empty($return_url)) {
            if (str_ends_with($return_url, '=')) {
                $return_url = $return_url . $out_trade_no;
            }
            $order['return_url'] = $return_url;
        }
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '支付宝下单失败！'
            ];
        }

        try {
            $result = Pay::alipay($config)->h5($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '支付宝下单失败！'
            ];
        }
        Log::write($result);
        $payForm = $result->getBody()->getContents();
        //记录订单
        $order = [
            'id' => $out_trade_no,
            'orderid' => $orderid,
            'method' => 'alipay',
            'modeid' => $modeid,
            'type' => 'h5',
            'subject' => $subject,
            'price' => $price,
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        PayRecordModel::insert($order);

        return [
            'code' => 0,
            'msg' => '',
            'data' => [
                'payid' => $out_trade_no,
                'pay_form' => $payForm // 支付宝支付表单HTML
            ]
        ];
    }

    public function wxRefund($payid, $amount, $modeid, $type)
    {
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '微信退款失败！'
            ];
        }
        $money = bcmul($amount, 100, 0);
        $order = [
            'out_trade_no' => $payid,
            'out_refund_no' => uniqid(),
            'amount' => [
                'refund' => intval($money),
                'total' => intval($money),
                'currency' => 'CNY',
            ],
            '_action' => $type,
        ];
        try {
            $result = Pay::wechat($config)->refund($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '微信退款失败！'
            ];
        }
        $rarr = $result->toArray();
        //Log::write($rarr);
        if (isset($rarr['code'])) {
            Log::error("退款失败 " . $payid);
            Log::write($rarr);
            return [
                'code' => 1,
                'msg' => '微信退款失败',
            ];
        }
        if (isset($rarr['status'])) {
            if ($rarr['status'] == "SUCCESS") {
                Log::debug("退款成功 " . $payid);
            }
        }
        return [
            'code' => 0,
            'msg' => "退款成功"
        ];
    }

    //微信h5支付
    public function wxH5pay($orderid, $price, $subject, $modeid, $ip)
    {
        $out_trade_no = 'WH' . time() . $this->generateRandomString(5);
        //金额单位分
        $price100 = bcmul($price, 100, 0);


        $order = [
            'out_trade_no' => $out_trade_no,
            'description' => $subject,
            'amount' => [
                'total' => intval($price100),
            ],
            'scene_info' => [
                'payer_client_ip' => $ip,
                'h5_info' => [
                    'type' => 'Wap',
                ]
            ]
        ];
        $config = $this->getConfig($modeid);
        if (empty($config)) {
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }
        try {
            $result = Pay::wechat($config)->h5($order);
        } catch (\Yansongda\Artful\Exception\InvalidResponseException $e) {
            $rep = $e->response;
            if ($rep instanceof \Yansongda\Supports\Collection) {
                $result = $rep;
            }
        } catch (\Exception $e) {
            Log::write($e);
            Log::error($e->getMessage());
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }

        $h5_url = $result->h5_url;
        if (empty($qr)) {
            Log::error('微信扫码下单失败！');
            Log::write($result->toArray());
            return [
                'code' => 1,
                'msg' => '微信下单失败！'
            ];
        }

        //记录订单
        $order = [
            'id' => $out_trade_no,
            'orderid' => $orderid,
            'method' => 'wechat',
            'modeid' => $modeid,
            'type' => 'h5',
            'subject' => $subject,
            'price' => $price,
            'status' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];
        PayRecordModel::insert($order);
        return [
            'code' => 0,
            'msg' => '微信下单成功！',
            'data' => [
                'payid' => $out_trade_no,
                'h5_url' => $h5_url
            ]
        ];
    }

    /**

     * 获取二维码

     * @param int $modeid 支付模版id

     * @param int $type 支付类型 1微信 2支付宝

     * @param float $amount 金额 单位元

     * @param string $orderid 订单号

     * @param string $subject 订单标题

     * @return array

     */
    public function  getQRcode(int $modeid, int $type, float $amount, string $orderid, string $subject)
    {
        if ($type == 1) {
            $ret = $this->wxScanpay($orderid, $amount, $subject, $modeid);
        } else if ($type == 2) {
            $ret = $this->aliScanpay($orderid, $amount, $subject, $modeid);
        } else {
            return [
                'code' => 1,
                'msg' => '支付类型错误！'
            ];
        }
        if ($ret['code'] != 0) {
            return $ret;
        }
        return [
            'code' => 0,
            'msg' => '',
            'data' => [
                'payid' => $ret['out_trade_no'],
                'qr' => $ret['qr'],
            ]

        ];
    }

    public function paySucess($payid)
    {
        $payorder = PayRecordModel::where('id', $payid)->find();
        if (empty($payorder)) {
            Log::error('付款成功，订单不存在！id:' . $payid);
            return [
                'code' => 1,
                'msg' => '订单不存在！'
            ];
        }
        if ($payorder->status != 0) {
            Log::error("该订单已支付已经处理完成！");
            return [
                'code' => 1,
                'msg' => '该订单已支付已经处理完成！'
            ];
        }
        $checkOrder = CheckOrderModel::where("id", $payorder->orderid)->find();
        if (empty($checkOrder)) {
            Log::error("用户支付成功，该订单不存在！payid=" . $payid);
            return [
                'code' => 1,
                'msg' => '该订单不存在！'
            ];
        }
        CheckOrderModel::where("id", $payorder->orderid)->update(['status' => 4, 'payid' => $payid, 'update_time' => date('Y-m-d H:i:s')]);
        PayRecordModel::where('id', $payid)->update(['status' => 1, 'update_time' => date('Y-m-d H:i:s')]);
        //支付订单
        $ret =  (new Check())->payOrder($checkOrder->id, $checkOrder->title, $checkOrder->author, $checkOrder->end_date);
        if ($ret['code'] == 0) {
            CheckOrderModel::where("id", $payorder->orderid)->update(['status' => 5, 'update_time' => date('Y-m-d H:i:s')]);
        } else {
            Log::error($payorder->orderid . " 订单付款失败-" . $ret['msg']);
        }
    }

    public function refund($payid)
    {
        $payRecord = PayRecordModel::where("id", $payid)->find();
        if (empty($payRecord)) {
            return [
                'code' => 1,
                'msg' => "支付记录不存在",
            ];
        }
        if ($payRecord->method == "alipay") {
            return $this->aliRefund($payRecord->id, $payRecord->price, $payRecord->modeid, $payRecord->type);
        } else if ($payRecord->method == "wechat") {
            return $this->wxRefund($payRecord->id, $payRecord->price, $payRecord->modeid, $payRecord->type);
        }
    }
}
