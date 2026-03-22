<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;
use think\facade\Config;

class ArticleModel extends Model
{
    // 模型数据表
    protected $table = 'pt_article';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'int',             //id 自增益
        'title'    => 'string', //标题
        'content' => 'string', //内容
        'updated_time' => 'datetime', //更新时间
      
    ];
}