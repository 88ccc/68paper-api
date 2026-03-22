<?php

namespace app\model;

use think\Model;
use think\facade\Log;

class ShopProductModel extends Model
{
    // 模型数据表
    protected $table = 'pt_shop_product';

    protected $schema = [
        'shopid'          => 'string',//商铺id
        'productid'=> 'string', //产品id
        'unit'  => 'int',//计费单位,0按次计费,其他按字数计费
        'price'=> 'int',//单价(分)
        'status'=> 'int',//状态：1=正常，2=禁用
        'create_time' => 'datetime', //创建时间
        'update_time' => 'datetime', //更新时间
        'tips'=>'string',//提示
    ];
}