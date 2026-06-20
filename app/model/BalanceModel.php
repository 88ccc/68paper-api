<?php

namespace app\model;

use think\Model;
use think\facade\Log;

class BalanceModel extends Model
{
    // 模型数据表
    protected $table = 'pt_balance';
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


    /**
     * 统计指定时间范围内按变更类型汇总金额
     * @param string $startDate 开始日期（格式：2025-01-01）
     * @param string $endDate 结束日期（格式：2025-12-31）
     * @return \think\Collection 统计结果集合
     */
    public static function statBalanceByType($userid, $startDate, $endDate)
    {
        // 1. 简单日期格式验证（避免传入非法日期）
        if (!strtotime($startDate) || !strtotime($endDate)) {
           Log::error('日期格式错误');
           return [];
        }

        // 2. 日期格式补全：Y-m-d -> Y-m-d H:i:s（避免查询遗漏当天数据）
        $startDateTime = date('Y-m-d 00:00:00', strtotime($startDate));
        $endDateTime = date('Y-m-d 23:59:59', strtotime($endDate));

        // 3. 构造查询：时间筛选 -> 分组 -> 求和（这部分逻辑保持不变）
        return self::where('user_id', $userid)->whereBetween('create_time', [$startDateTime, $endDateTime])
            ->group('change_type') // 按变更类型分组
            ->field([ // 要查询的字段：类型编号 + 金额总和（别名total_amount，单位：分）
                'change_type',
                'sum(change_amount) as total_amount'
            ])
            ->select(); // 返回查询结果集合（可直接转数组使用）
    }
}
