<?php

namespace app\model;


use think\Model;

class WithdrawModel extends Model
{
    // 模型数据表
    protected $table = 'pt_withdraw';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'string',//提现id
        'userid'          => 'int',
        'name'    => 'string', //真实姓名
        'money'    => 'int', //提现金
        'account_type'	=> 'string', //账户类型
        'account'	=> 'string', //账户
        'do_time'	=> 'datetime', //处理时间
        'amount'  => 'int', //实际到账
        'charge'  => 'int', //手续费
        'status'  => 'int',//状态 1 等待处理 2 处理完成 3 处理失败
        'create_time'  => 'datetime', //创建时间
        'remark'=>'string',//备注

    ];
}