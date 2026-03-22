<?php
// 自定义工具类 ConfigService.php
namespace app\service;

use think\facade\Cache;
use app\model\ConfigModel;
use think\facade\Log;

class ConfigService
{
    // 获取单个配置
    public static function get($key)
    {
        // 从缓存读取
        $value = Cache::get($key);
        if (empty($value)) {
            $config = ConfigModel::field('key, value,type')->where('key', $key)->find();
            if (empty($config)) {
                return null;
            }
            $value = null;
            if ($config->type == 'array') {
                $value = json_decode($config->value, true);
            } else if ($config->type == 'int') {
                $value = intval($config->value);
            } else if ($config->type == 'float') {
                $value = floatval($config->value);
            } else {
                $value = $config->value;
            }
            // 写入缓存（设置较长有效期，如7天，修改时主动更新）
            Cache::set($key, $value, 60 * 60 * 24 * 7);
        }
        return $value;
    }

    public static function clear($key){
        Cache::delete($key);
        ConfigModel::where('key', $key)->delete();
    }

    public static function set($key, $value, $title = null)
    {

        $config = ConfigModel::where('key', $key)->find();
        if (empty($config)) {
            $config = new ConfigModel;
            $config->key = $key;
        }
        if (is_array($value)) {
            $config->type = 'array';
            $config->value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else if (is_int($value)) {
            $config->type = 'int';
            $config->value = '' . $value;
        } else if (is_float($value)) {
            $config->type = 'float';
            $config->value = '' . $value;
        } else {
            $config->type = 'string';
            $config->value = $value;
        }
        if (!empty($title)) {
            $config->title = $title;
        } else {
            $config->title = '';
        }
        try {
            $config->save();
        } catch (\Exception $e) {
            Log::error($e);
            return false;
        }
        try {
            Cache::set($key, $value, 60 * 60 * 24 * 7);
        } catch (\Exception $e) {
            Log::error($e);
            return false;
        }
        return true;
    }
}
