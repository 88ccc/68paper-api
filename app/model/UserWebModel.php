<?php

namespace app\model;

use app\tool\EmailTool;
use think\Model;
use Yansongda\Pay\Pay;

class UserWebModel extends Model
{
    // 模型数据表
    protected $table = 'pt_web_set';
    // 模型数据表主键
    protected $pk = 'userid';
    protected $schema = [
        'userid'          => 'int',
        'webid'    => 'string',
        'show_jc' => 'int', //是否显示加盟
        'qq'  => 'string', //QQ
        'wechat'  => 'string', //微信
        'phone'  => 'string', //电话
        'pay_type' => 'string', //支付方式
    ];
}
