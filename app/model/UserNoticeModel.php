<?php

namespace app\model;

use think\Model;

class UserNoticeModel extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'pt_user_notice';
    // 模型数据表主键
    // 设置字段信息
    protected $schema = [
        'userid'        => 'int',              //用户id
        'position'      => 'string',           //位置
        'conent'        => 'string',           //内容
        'status'        => 'int',                //状态 1待审核 2审核通过 3审核被拒
        'update_time'   => 'datetime',        //状态更新时间
    ];
}
