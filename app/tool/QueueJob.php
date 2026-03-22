<?php

namespace app\tool;

use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\ShopModel;
use app\service\StorageService;
use think\queue\Job;
use think\facade\Log;
use think\facade\Queue;

class QueueJob
{

    public function fire(Job $job, $data)
    {

        if ($job->attempts() > 3) {
            //通过这个方法可以检查这个任务已经重试了几次了
            $job->delete();
        }
        //Log::write($data);
        if (isset($data['job']) && !empty($data['job'])) {
            if (strcmp($data['job'], 'down_report') == 0) {
                if (isset($data['id']) && !empty($data['id'])) {
                    $ret = 0;
                    if (isset($data['url']) && !empty($data['url'])) {
                        $ret = $this->down_report($data['id'], $data['url']);
                    } else {
                        Log::error("run fire down_report not find url");
                    }
                    if ($ret == 0) {
                        $job->delete();
                    }
                } else {
                    Log::error("run fire down_report not find id");
                }
            }
        } else {
            Log::error("run fire not find data job 10000");
        }
    }

    public function parse_file($orderid): int
    {
        return 0;
    }

    public function start_check($orderid)
    {

        return 0;
    }

    public function down_report($orderid, $url)
    {
        //先锁定订单
        $ret  = 0;
        $lock = app()->getRootPath() . 'public/lock/order_lock.txt';
        if (!file_exists($lock)) {
            Log::error("订单锁不存在");
            return 1;
        }
        $fp = fopen($lock, 'r');
        if (flock($fp, LOCK_EX)) {
            $order = CheckOrderModel::where('id', $orderid)->find();
            if (empty($order)) {
                Log::error("下载报告，订单不存在 id:" . $orderid);
                flock($fp, LOCK_UN);
                fclose($fp);
                return 1;
            }
            if (in_array($order->status, [7, 8, 9])) {
                Log::error("下载报告，订单状态为789不需要下载 id:" . $orderid);
                flock($fp, LOCK_UN);
                fclose($fp);
                return 1;
            }
            if ($order->lock != 1) {
                //订单被锁住了
                $time = date("Y-m-d H:i:s", strtotime("-5 minute"));
                if (!empty($order->lock_time) && strcmp($order->lock_time, $time) > 0) {
                    //锁住不到5分钟
                    Log::error("下载报告，订单锁状态不为1 id:" . $orderid);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    return 1;
                }
            }
            CheckOrderModel::where('id', $orderid)->update(['lock' => 2, 'lock_time' => date('Y-m-d H:i:s', time())]);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        $file_t = ""; //文件的后缀名
        $o_base_name = ""; //文件的名称
        $down_file = ""; //下载的文件
        $reportPath = runtime_path() .  '/report/' . date('Ymd/');
        if (!file_exists($reportPath)) {
            $ret = mkdir($reportPath, 0777, true);
            if ($ret === false) {
                Log::error("down_report 创建文件夹失败 " . $reportPath);
                CheckOrderModel::where('id', $orderid)->update(['lock' => 1, 'status' => 7, 'remark' => '系统错误10001', 'update_time' => date('Y-m-d H:i:s', time())]);
                return 0;
            }
        }
        
        if (empty($down_file)) {
            //需要下载文件
            $purl = parse_url($url, PHP_URL_PATH);
            $file_t = pathinfo($purl, PATHINFO_EXTENSION);
            $o_base_name = pathinfo($purl, PATHINFO_BASENAME);
            if (empty($file_t)) {
                //链接中不一定有文件名称
                $file_t = "zip";
            }
            $down_file = $reportPath . $orderid . '.' . $file_t;
            if (file_exists($down_file)) {
                Log::debug("down_report 报告已经存在 删除后重新下载" . $down_file);
                unlink($down_file);
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 5242880);  // 缓存大小5M
            $df = fopen($down_file, 'wb');
            if ($df === false) {
                Log::error("down_report 打开文件失败 " . $down_file);
                CheckOrderModel::where('id', $orderid)->update(['lock' => 1, 'status' => 7, 'remark' => '系统错误10003', 'update_time' => date('Y-m-d H:i:s', time())]);
                return 0;
            }
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $str) use ($df) {
                $len = strlen($str);
                fwrite($df, $str);
                return $len;
            });
            $output = curl_exec($ch);
            if ($output === false) {
                Log::error("下载文件失败，orderid:" . $orderid . ",url=" . $url);
            }
            fclose($df);
            curl_close($ch);
        }
        if (strcmp($file_t, 'zip') != 0) {
            //如果不是zip,就要打包成zip
            $a = $reportPath . $orderid . '.zip';
            if (file_exists($a)) {
                unlink($a);
            }
            $zip_tmp = new \ZipArchive();
            if ($zip_tmp->open($a, \ZipArchive::CREATE) === TRUE) {
                $zip_tmp->addFile($down_file, $o_base_name); //添加新的文件
                $zip_tmp->close();
                $file_t = "zip";
            } else {
                Log::error("打包文件失败 orderid = " . $orderid);
                CheckOrderModel::where('id', $orderid)->update(['lock' => 1, 'status' => 7, 'remark' => '系统错误10003', 'update_time' => date('Y-m-d H:i:s', time())]);
                return 0;
            }
            $down_file = $a;
        }
        //判断是否要增加文件
        $order = CheckOrderModel::where('id', $orderid)->find();
        $shop = ShopModel::where('id', $order->shopid)->find();
        if (!empty($shop)) {
            if (!empty($shop->file_path)) {
                if (file_exists($shop->file_path)) {
                    if (!empty($shop->file_name)) {
                        $zip_tmp = new \ZipArchive();
                        if ($zip_tmp->open($down_file, \ZipArchive::CREATE) === TRUE) {
                            $zip_tmp->addFile($shop->file_path, $shop->file_name); //添加新的文件
                            $zip_tmp->close();
                        }
                    }
                }
            }
        }
        //需要验证zip文件是否OK
        $zip = new \ZipArchive;
        if ($zip->open($down_file) === TRUE) {
            $zip->close();
        } else {
            Log::error("zip包验证出错" . $down_file);
            CheckOrderModel::where('id', $orderid)->update(['lock' => 1]);
            return 0;
        }
        //需要存储
        $check = CheckModel::where('id', $order->product_id)->find();
        $save_file_name = $check->name . "_" . $order->payid;
        $report_url = (new StorageService())->save($down_file, $save_file_name);
        if (!empty($report_url)) {
            CheckOrderModel::where('id', $orderid)->update(['lock' => 1, 'status' => 8, 'report_url' => $report_url, 'update_time' => date('Y-m-d H:i:s', time())]);
        }
        return 0;
    }
}
