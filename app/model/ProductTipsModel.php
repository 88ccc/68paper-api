<?php

namespace app\model;

use think\Model;
use think\facade\Log;

class ProductTipsModel extends Model
{
    // 模型数据表
    protected string $table = 'pt_product_tips';
    // 模型数据表主键
    protected string $pk = 'product_id';
    protected $schema = [
        'product_id'          => 'string',             //产品id
        'level'=> 'int', //级别:1提示 2警告 3错误
        'content'  => 'string',//内容
        'update_time' => 'datetime', //修改时间
    ];
}