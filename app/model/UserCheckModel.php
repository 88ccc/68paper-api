<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;
use think\facade\Config;

class UserCheckModel extends Model
{
    // 模型数据表
    protected $table = 'pt_user_check';
    // 模型数据表主键
   
    protected $schema = [
        'userid'          => 'int',             //id 自增益
        'product_id'    => 'string', //产品ID
        'unit' => 'int', //计费单位
        'price' => 'int',//售价
        'status'=>'int',//状态 1正常 2禁用
    ];
}