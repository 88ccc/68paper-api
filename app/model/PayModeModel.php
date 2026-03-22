<?php

namespace app\model;

use app\tool\EmailTool;
use think\Model;


class PayModeModel extends Model
{
    // 模型数据表
    protected $table = 'pt_paymode';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'int',
        'name'    => 'string', //模型名称
        'type' => 'string', //支付方式 wxpay alipay
        'entype' => 'string', //加密方式
        'appid'  => 'string', //应用id
        'mchid'  => 'string', //微信商户id
        'mchkey'  => 'string', //微信APIV3秘钥
        'mchsecretpath' => 'string', //微信商户私钥路径
        'mchpublicpath' => 'string', //微信商户公钥路径
        'appsecret' => 'string', //支付宝应用私钥
        'apppublicpath' => 'string', //支付宝应用公钥证书路径
        'alipublicpath' => 'string', //支付宝公钥证书路径
        'alirootpath' => 'string', //支付宝根证书路径
        'create_time'  => 'datetime',         //创建时间
        'update_time'  => 'datetime',         //更新时间

    ];
}
