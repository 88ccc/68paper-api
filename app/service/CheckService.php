<?php
namespace app\service;

use app\model\CheckModel;
use app\model\CheckOrderModel;
use app\model\UserCheckModel;
use think\facade\Log;
use think\facade\Queue;
use app\tool\QueueJob;

class CheckService
{
    public function updateStatusFromSupplier(array $data): bool
    {
        $order = CheckOrderModel::where('id', $data['id'])->find();
        if (empty($order)) {
            Log::error("订单不存在 id=" . $data['id']);
            return false;
        }
        if ($order->status > 5) {
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
            $product = UserCheckModel::where(['userid' => $order->userid, 'product_id' => $order->product_id])->find();
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
            $sell_piece = 1;
            //计算平台价格
            $p_price = $check->price;
            $p_piece = 1;
            if ($check->unit != 0) {
                //计算件数
                $tmp = $data['word_count'] + $check->unit - 1;
                $sell_piece = bcdiv($tmp, $check->unit, 0);
                $sell_price = $sell_piece * $product->price;
                $tmp = $data['word_count'] + $check->unit - 1;
                $p_piece = bcdiv($tmp, $check->unit, 0);
                $p_price = $p_piece * $check->price;
            }
            //计算推荐奖励
            $reward = $check->reward * $p_piece;

            //平台是否亏本
            if (($p_price - $reward) < $data['total_price']) {
                Log::error("供货价低于成本价 id=" . $data['id']);
                $order->status = 3;
                $order->remark = "供货价异常";
                $order->update_time = date('Y-m-d H:i:s');
                $order->save();
                return true;
            }
            //用户是否亏本
            if ($sell_price < $p_price) {
                Log::error("产品售价低于供货价 id=" . $data['id']);
                $order->status = 3;
                $order->remark = "售价异常";
                $order->update_time = date('Y-m-d H:i:s');
                $order->save();
                return true;
            }
            //计算利润
            $profit = $sell_price - $p_price;
            $pprofit = $p_price - $data['total_price'];
            $order->cost = $p_price;
            $order->unit_price = $product->price;
            $order->total_price = $sell_price;
            $order->pcost = $data['total_price'];
            $order->status = 2;
            $order->words = $data['word_count'];
            $order->piece =  $sell_piece;
            $order->ppiece = $p_piece;
            $order->profit = $profit;
            $order->pprofit = $pprofit;
            $order->tprofit = $reward;
            $order->update_time = date('Y-m-d H:i:s');
            $order->save();
            return true;
        } else if ($data['status'] == 3) {
            //解析失败
            if ($order->status != 1) {
                //不需要解析了
                return true;
            }
            $order->status = 3;
            $order->update_time = date('Y-m-d H:i:s');
            $order->save();
            return true;
        } else if ($data['status'] == 5) {
            //检测成功
            $data['report']; //报告下载地址
            CheckOrderModel::where('id', $data['id'])->update(['copy_percent' => $data['copy_percent'], "update_time" => date("Y-m-d H:i:s")]);
            $data = [
                'job' => 'down_report',
                'id' => $data['id'],
                'url' => $data['report']
            ];
            Queue::push(QueueJob::class,  $data,  'default');
            return true;
        } else if ($data["status"] == 6) {
            //检测失败
            CheckOrderModel::where('id', $data['id'])->update(['status' => 7, "update_time" => date("Y-m-d H:i:s")]);
            return true;
        }
        return false;
    }


    //校验参数
    public function validateParameters(array $data): array
    {
        $updata = [];
        $param = [];

        if (empty($data['order_id'])) {
            return [
                'code' => 10001,
                'msg' => '订单号不能为空'
            ];
        } else {
            $orderid = trim($data['order_id']);
        }
        $order = CheckOrderModel::where(['id' => $orderid])->find();
        if (empty($order)) {
            return [
                'code' => 10010,
                'msg' => '订单不存在'
            ];
        }
        if (empty($data['title'])) {
            return [
                'code' => 10001,
                'msg' => '标题不能为空'
            ];
        } else {
            $updata['title'] = trim($data['title']);
        }
        if (empty($data['author'])) {
            return [
                'code' => 10001,
                'msg' => '作者不能为空'
            ];
        } else {
            $updata['author'] = trim($data['author']);
        }
        if (($order->product_id == "wanfangzc") || ($order->product_id == "cqvipzc")) {
            if (empty($data['end_date'])) {
                return [
                    'code' => 10001,
                    'msg' => '发表日期不能为空'
                ];
            } else {
                $updata['end_date'] = trim($data['end_date']);
            }
        }

        if (str_starts_with($order->product_id, 'cqvip')) {
            if (preg_match('/[\x{4e00}-\x{9fa5}a-zA-Z]/u', $updata['title']) == 0) {
                return [
                    'code' => 10001,
                    'msg' => '标题必须包含汉字或者字母'
                ];
            }
            if (preg_match('/[\x{4e00}-\x{9fa5}a-zA-Z]/u', $updata['author']) == 0) {
                return [
                    'code' => 10001,
                    'msg' => '作者必须包含汉字或者字母'
                ];
            }
            // $pattern = '/[\\/:*?"<>|]/';
            // if (preg_match($pattern, $updata['title']) == 1) {
            //     return [
            //         'code' => 10001,
            //         'msg' => '标题不得包含\/:*?"<>|'
            //     ];
            // }
            // if (preg_match($pattern, $updata['author']) == 1) {
            //     return [
            //         'code' => 10001,
            //         'msg' => '作者不得包含\/:*?"<>|'
            //     ];
            // }
        }
        if (str_starts_with($order->product_id, 'turnitin')) {
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $updata['author']) === 1) {
                return [
                    'code' => 10001,
                    'msg' => '作者不得包含中文'
                ];
            }
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $updata['title']) === 1) {
                return [
                    'code' => 10001,
                    'msg' => '标题不得包含中文'
                ];
            }
        }


        if ($order->product_id == "cqvipzpdxs") {
            //维普智评大学生版
            if (!empty($data['school_id'])) {
                $param["school_id"] = trim($data['school_id']);
            }
        }

        if ($order->product_id == "cqvipzpyjs") {
            if (!empty($data['school_id'])) {
                $param["school_id"] = trim($data['school_id']);
            } else {
                return [
                    'code' => 10001,
                    'msg' => '必须填写学校'
                ];
            }

            if (!empty($data['class_code'])) {
                $param["class_code"] = trim($data['class_code']);
            } else {
                return [
                    'code' => 10001,
                    'msg' => '必须填写学科'
                ];
            }
        }
        if ($order->product_id == "cqvipzpqk") {
            if (!empty($data['class_type'])) {
                $param["class_type"] = trim($data['class_type']);
            } else {
                return [
                    'code' => 10001,
                    'msg' => '必须填写类型'
                ];
            }
        }

        if ($order->product_id == "zjcaigc") {
            if (!empty($data['ai_plat'])) {
                $param["ai_plat"] = trim($data['ai_plat']);
            }
        }

        //检查长度
        $check = CheckModel::where(['id' => $order->product_id])->find();
        if (empty($check)) {
            return [
                'code' => 1,
                'msg' => '产品不存在'
            ];
        }
        if ($check->config['title_max'] < mb_strlen($updata['title'])) {
            return [
                'code' => 10001,
                'msg' => '标题长度超出限制' . $check->config['title_max']
            ];
        }
        if ($check->config['author_max'] < mb_strlen($updata['author'])) {
            return [
                'code' => 10001,
                'msg' => '作者长度超出限制' . $check->config['author_max']
            ];
        }
        //检测文章字数
        if ($order->words > 0) {
            if ($check->config['max_words'] < $order->words) {
                return [
                    'code' => 10001,
                    'msg' => "文章字数(" . $order->words . ")超出限制(" . $check->config['max_words'] . ")"
                ];
            }
            if ($check->config['min_words'] > $order->words) {
                return [
                    'code' => 10001,
                    'msg' => "文章字数(" . $order->words . ")低于限制(" . $check->config['min_words'] . ")"
                ];
            }
        }
        $updata['update_time'] = date('Y-m-d H:i:s', time());
        if (!empty($param)) {
            $updata['param'] = $param;
        }
        return [
            'code' => 0,
            'msg' => '',
            'status' => $order->status,
            'data' => $updata,
        ];
    }
}
