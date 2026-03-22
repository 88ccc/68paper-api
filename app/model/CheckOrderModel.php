<?php

namespace app\model;

use think\Model;
use Firebase\JWT\JWT;
use think\facade\Log;

class CheckOrderModel extends Model
{
    // 模型数据表
    protected $table = 'pt_check_order';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'string',
        'shopid' => 'string',
        'original' => 'string', //提交文件
        'product_id' => 'string', //产品ID
        'title' => 'string', //标题
        'author' => 'string', //作者
        'end_date' => 'string', //发表日期
        'cost' => 'int', //成本 单位分
        'unit_price' => 'int', //单价 单位分
        'total_price' => 'int', //总价 单位分
        'words' => 'int', //字数
        'piece' => 'int', //件数
        'profit' => 'int', //利润
        'lock' => 'int', //订单锁,1=正常,2=被锁住
        'copy_percent' => 'string', //复制比
        'file_key'=>'string', //文件key
        'report_url'=>'string', //报告下载地址
        'payid'=>'string',//支付ID
        'status' => 'int', //状态 1=创建，2=解析成功，3=解析失败, 4=用户支付成功，5=供货成功，6=供货失败，7=检测失败 8=检测成功 9=已经退款,10已经删除
        'remark' => 'string', //备注
        'lock_time' => 'datetime', //锁时间
        'update_time' => 'datetime', //更新时间
        'create_time' => 'datetime', //创建时间
    ];
}