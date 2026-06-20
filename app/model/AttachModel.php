<?php

namespace app\model;

use think\Model;

class AttachModel extends Model
{
    // 模型数据表
    protected $table = 'pt_attach';
    // 模型数据表主键
    protected $pk = 'userid';
    protected $schema = [
        'userid'          => 'int',             //用户id
        'file_name'    => 'string', //文件名称
        'file_path' => 'string', //文件路径
        'file_time' => 'datetime',//文件时间
        'file_status' => 'int',//文件状态 0=未上传,1=已上传代审核,2=审核通过,3=审核失败,4=永久禁用
        'update_time' => 'datetime', //更新时间
      
    ];
}