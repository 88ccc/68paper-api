<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;
use think\facade\Config;

class ShopModel extends Model
{
     protected $table = 'pt_shop';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'string',             
        'name'    => 'string', //店铺名称
        'status'  => 'int', //状态：1=正常，2=禁用
        'file_name'=>'string',//附件的文件名
        'file_path'=>'string',//附件的路径
        'create_time'  => 'datetime',         //创建时间
        'update_time'  => 'datetime',         //更新时间
    ];
}