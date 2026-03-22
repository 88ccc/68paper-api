<?php

namespace app\model;

use app\tool\EmailTool;
use think\Model;
use Yansongda\Pay\Pay;

class PaySetModel extends Model
{
    // 模型数据表
    protected $table = 'pt_payset';
    // 模型数据表主键
    protected $pk = 'id';
    protected $schema = [
        'id'          => 'int',
        'scene'    => 'string', //使用场景:pc,h5,wxh5
        'type' => 'string', //支付方式:wxpay,alipay
        'modeid' => 'int', //支付模板的id
        'status'  => 'int', //状态:0禁用,1启用
        'prefer'  => 'int', //是否默认支付:0否,1是
        'update_time'  => 'datetime',         //更新时间

    ];

    static function  deleteModelId($modeid){
        PaySetModel::where('modeid',$modeid)->update(['modeid'=>0,'status'=>0,'prefer'=>0,'update_time'=>date('Y-m-d H:i:s')]);
    }
}