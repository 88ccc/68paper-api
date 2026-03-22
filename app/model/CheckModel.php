<?php

namespace app\model;

use think\Model;


/**
 * {"max_words":"160000","min_words":"200","file_types":"txt,doc,docx","file_max":"30","title_max":"100","author_max":"50"}
 */

class CheckModel extends Model
{

    // 模型数据表
    protected $table = 'pt_check';
    // 模型数据表主键
    protected $pk = 'id';
    // 设置json类型字段
    protected $json = ['config'];
    protected $schema = [
        'id'          => 'string',
        'name' => 'string', //产品名称
        'cost' => 'int', //成本价格单位分
        'unit'  => 'int', //计费单位,0按次计费,其他按字数计费
        'mini_price'  => 'int', //限制最低售价单位分
        'supplier_status'  => 'int', //供货方状态：1=正常，2=禁用，3=已删除
        'status'  => 'int', //状态：1=正常，2=禁用
        'config' => 'json', //配置信息JSON格式
        'create_time'  => 'datetime',         //创建时间
        'update_time'  => 'datetime',         //更新时间
    ];
}