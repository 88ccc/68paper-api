<?php

namespace app\model;

use think\Model;

class CardModel extends Model
{
    // 模型数据表
    protected $table = 'pt_card';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'string',
        'userid' => 'int',
        'piece' => 'int', //件数
        'used' => 'int', //已经使用
        'product_id' => 'string', //产品ID
        'order_id' => 'string', //订单ID
        'uuid' => 'string', //调用方ID
        'status' => 'int', //状态：1=创建，2=已使用，3=禁用
        'remark' => 'string', //备注
        'update_time' => 'datetime', //更新时间
        'create_time' => 'datetime', //创建时间
    ];
}
