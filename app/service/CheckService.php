<?php
// 自定义工具类 ConfigService.php
namespace app\service;

use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\ShopProductModel;
use think\facade\Log;
use think\facade\Queue;
use app\tool\QueueJob;

class CheckService
{
    public function updateStatusFromSupplier($data)
    {
        $order = CheckOrderModel::where('id', $data['id'])->find();
        if (empty($order)) {
            Log::error("订单不存在 id=" . $data['id']);
            return false;
        }
        if($order->status > 5){
            //不需要更新状态
            return true;
        }
        if ($data['status'] == 2) {
            //解析成功
            if ($order->status != 1) {
                //不需要解析了
                return true;
            }

            $check = CheckModel::where('id', $order->product_id)->find();
            if (empty($check)) {
                //产品不存在
                Log::error("产品不存在 id=" . $data['id']);
                Log::write($data);
                return true;
            }
            $product = ShopProductModel::where(['shopid' => $order->shopid, 'productid' => $order->product_id])->find();
            if (empty($product)) {
                //产品不存在
                Log::error("产品不存在 id=" . $data['id']);
                Log::write($data);
                return true;
            }
            if ($data['unit_price'] != $check->cost) {
                Log::debug("产品成本不一致");
            }
            //计算售价
            $sell_price = $product->price;
            $piece = 1;
            if ($product->unit != 0) {
                //计算件数
                $piece = ceil($data['word_count'] / $product->unit);
                $sell_price = $product->price * $piece;
            }
            //是否亏本
            if ($sell_price < $data['total_price']) {
                Log::error("产品售价低于总价 id=" . $data['id']);
                return true;
            }
            $order->cost = $data['total_price'];
            $order->unit_price = $product->price;
            $order->total_price = $sell_price;
            $order->status = 2;
            $order->words = $data['word_count'];
            $order->piece = $piece;
            $order->profit = $sell_price - $data['total_price'];
            $order->create_time = date('Y-m-d H:i:s');
            $order->save();
            return true;
        }else if($data['status'] == 3){
            //解析失败
            if ($order->status != 1) {
                //不需要解析了
                return true;
            }
            $order->status = 3;
            $order->create_time = date('Y-m-d H:i:s');
            $order->save();
            return true;
        }else if($data['status'] == 5){
            //检测成功
            $data['report'];//报告下载地址
            CheckOrderModel::where('id', $data['id'])->update(['copy_percent' => $data['copy_percent'],"update_time"=>date("Y-m-d H:i:s")]);
            $data = [
            'job' => 'down_report',
            'id' => $data['id'],
            'url' => $data['report']
        ];
        Log::write("down start");
        Queue::push(QueueJob::class,  $data,  'default');
        return true;
        }else if($data["status"] == 6){
            //检测失败
            CheckOrderModel::where('id', $data['id'])->update(['status' => 7,"update_time"=>date("Y-m-d H:i:s")]);
            return true;
        }
    }
}
