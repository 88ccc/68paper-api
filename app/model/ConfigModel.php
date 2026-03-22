<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;
use think\facade\Config;

class ConfigModel extends Model
{
    // 模型数据表
    protected $table = 'pt_config';
    // 模型数据表主键
    protected $pk = 'key';
    protected $schema = [
        'key'          => 'string',             //键名
        'value'    => 'string', //值
        'title' => 'string', //配置名称
        'type'=>'string',//配置类型 text 字符串 number 数字 array 数组(保存时转为json保存)
        'updated_time' => 'datetime', //更新时间
    ];
}