<?php

namespace app\model;

use think\Model;
use think\facade\Log;

class PointsModel extends Model
{
    // 模型数据表
    protected $table = 'pt_points';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'string',
        'user_id'    => 'int', //用户id
        'before_balance' => 'int', //变更前余额
        'change_amount' => 'int', //变更金额 可能是负数 可能是正数
        'after_balance' => 'int', //变更后余额
        'change_type' => 'int', //变更类型 1=充值，2=消费，3=退款，4=赠送，5=提现，6=其他
        'business_no' => 'string', //关联业务单号（如订单号、充值单号、提现单号等）
        'operator_id' => 'int', //操作人ID（0=系统自动操作）
        'operator_type' => 'int', //操作人类型：1=系统，2=管理员，3=用户自身,
        'remark' => 'string', //备注
        'create_time' => 'datetime', //创建时间
    ];


    
}
