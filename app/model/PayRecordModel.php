<?php

namespace app\model;

use think\Model;

class PayRecordModel extends Model
{
    // 模型数据表
    protected $table = 'pt_pay_record';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'        => 'string',              //键值 out_trade_no
        'orderid'      => 'string',              //用户id
        'method'       => 'string',           //支付方式:wechat,alipay
        'modeid' => 'int',        //支付模型ID
        'type'    => 'string',                //支付类型:scan,jsapi,h5
        'subject' => 'string', //商品名称
        'price'  => 'float',                   //价格
        'status' => 'int',            //状态:0创建,1成功,2失败,3已经退款
        'create_time'   => 'datetime',                      //创建时间
        'update_time'   => 'datetime',                      //状态更新时间
        'tips' => 'string',                 //提示
    ];
}
