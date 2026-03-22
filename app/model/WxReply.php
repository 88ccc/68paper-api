<?php

namespace app\model;

use think\Model;
use think\facade\Log;

class WxReply extends Model
{
    // 模型数据表
    protected $table = 'pt_wxreply';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'int',             //id 自增益
        'trigger'=> 'string', //触发方式
        'match'  => 'string',//匹配方式
        'keyword'=> 'string',//关键字
        'event'=> 'string',//事件
        'eventkey'=> 'string',//事件key
        'reply'=> 'string',//回复方式
        'text'=> 'string',//文本回复
        'view'=> 'string',//图文回复
        'status_time' => 'datetime', //状态时间
    ];
}