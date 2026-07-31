<?php
// 自定义工具类 ConfigService.php
namespace app\service;


use app\model\CheckModel;
use app\model\UserCheckModel;
use app\model\UserModel;
use app\model\CardModel;
use think\facade\Queue;
use app\tool\QueueJob;

class CardService
{
    function createCard(int $userid, string $product_id, int $piece, ?string $remark)
    {
        //检验产品是否存在
        $product = CheckModel::where("id", $product_id)->find();
        if (empty($product)) {
            return [
                'code' => 10007,
                'msg' => '产品不存在'
            ];
        }
        if ($product->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        if ($product->supplier_status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        $usercheck = UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->find();
        if (empty($usercheck)) {
            return [
                'code' => 10009,
                'msg' => '产品不存在'
            ];
        }
        if ($usercheck->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        // 检测piece是否合法
        if ($piece < 1 || $piece > 1000) {
            return [
                'code' => 10008,
                'msg' => '件数不合法'
            ];
        }
        //检测用户是否存在
        $user = UserModel::where("id", $userid)->find();
        if (empty($user)) {
            return [
                'code' => 10002,
                'msg' => '用户不存在'
            ];
        }
        //检测用户是否可用
        if ($user->status != 1) {
            return [
                'code' => 10003,
                'msg' => '用户状态异常'
            ];
        }
        $cardid1 = date('YmdHis');
        $cardid2 = getNonceStr(7);
        $cardid = "Y" . $cardid1 . $cardid2;
        CardModel::insert([
            'id' => $cardid,
            'userid' => $userid,
            'piece' => $piece,
            'used' => 0,
            'product_id' => $product_id,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s', time()),
            'update_time' => date('Y-m-d H:i:s', time()),
            'remark' => $remark,
        ]);

        return [
            'code' => 0,
            'msg' => '创建成功',
            'data' => [
                'id' => $cardid,
            ]
        ];
    }

    public function resetCardKey(int $userid)
    {
        if (empty($userid)) {
            return [
                'code' => 10002,
                'msg' => '用户不存在'
            ];
        }
        $cardkey = md5(uniqid("card", true));
        UserModel::where("id", $userid)->update(["cardkey" => $cardkey]);
        return [
            'code' => 0,
            'msg' => '重置成功',
            'data' => [
                'card_key' => $cardkey,
            ]
        ];
    }

    //消耗卡
    public function writeoffCards(int $userid, string $cards, string $orderid, string $product_id, int $piece)
    {
        $cardarr = explode(",", $cards);
        $count  = 0; //计数
        $amount = 0; //金额
        $product = CheckModel::where("id", $product_id)->find();
        if (empty($product)) {
            return [
                'code' => 10007,
                'msg' => '产品不存在'
            ];
        }
        if ($product->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        if ($product->supplier_status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        $usercheck = UserCheckModel::where(['userid' => $userid, 'product_id' => $product_id])->find();
        if (empty($usercheck)) {
            return [
                'code' => 10009,
                'msg' => '产品不存在'
            ];
        }
        if ($usercheck->status != 1) {
            return [
                'code' => 10008,
                'msg' => '产品不可用'
            ];
        }
        //cardarr 去重
        $cardarr = array_unique($cardarr);
        foreach ($cardarr as $cardid) {
            $card = CardModel::where("id", $cardid)->find();
            //判断状态
            if (empty($card)) {
                return [
                    'code' => 1,
                    'msg' => $cardid . ' 卡不存在,请检查卡号'
                ];
            }
            if ($card->status != 1) {
                $errmsg = $cardid . ' 卡状态异常';
                if ($card->status == 2) {
                    $errmsg = $cardid . ' 卡已经使用，不能重复使用，你可以直接下载报告';
                } else if ($card->status == 3) {
                    $errmsg = $cardid . ' 卡已经禁用，请联系商户客服';
                }
                return [
                    'code' => 1,
                    'msg' => $errmsg
                ];
            }
            //判断产品id
            if ($card->product_id != $product_id) {
                $product_name = "";
                $product = CheckModel::where("id", $card->product_id)->find();
                if (!empty($product)) {
                    $product_name = $product->name;
                }
                return [
                    'code' => 1,
                    'msg' => $cardid . ' 是 ' . $product_name . ' ,不能使用'
                ];
            }
            //检测卡必须是用一个用户
            if ($userid == 0) {
                $userid = $card->userid;
            } else {
                if ($userid != $card->userid) {
                    return [
                        'code' => 1,
                        'msg' => '检测卡不属于同一个商户'
                    ];
                }
            }
            //统计数量
            $count += $card->piece;
        }
        if ($count < $piece) {
            return [
                'code' => 1,
                'msg' => '需要' . $piece . ' 件，实际只有' . $count . ' 件'
            ];
        }
        $user = UserModel::where('id', $userid)->find();
        if (empty($user)) {
            return [
                'code' => 10002,
                'msg' => '用户不存在'
            ];
        }
        if ($user->status != 1) {
            return [
                'code' => 10003,
                'msg' => '用户状态异常'
            ];
        }
        $price = $product->price;
        $cost = bcmul($price, $piece, 0);
        if ($user->points < $cost) {
            return [
                'code' => 10009,
                'msg' => '用户余额不足'
            ];
        }
        $befor_points = $user->points;
        $dret = $user->decreasePoints($cost, 2, $orderid, "", 0, 1);
        if (!$dret) {
            return [
                'code' => 10009,
                'msg' => '扣款失败'
            ];
        }
        if (!empty($user->alarm_threshold)) {
            if ($befor_points > $user->alarm_threshold) {
                if (($befor_points - $cost) <= $user->alarm_threshold) {
                    $data = [
                        'job' => 'send_submsg',
                        'userid' => $user->id,
                        'event' => "points"
                    ];
                    Queue::push(QueueJob::class,  $data,  'default');
                }
            }
        }


        //开始核销
        $need = $piece;
        foreach ($cardarr as $cardid) {
            if ($need == 0) {
                break;
            }
            $card = CardModel::where("id", $cardid)->find();
            $used = 0;
            if ($need > $card->piece) {
                $used = $card->piece;
            } else {
                $used = $need;
            }
            $need -= $used;
            if ($used > 0) {
                CardModel::where('id', $cardid)->update(['used' => $used, 'status' => 2, 'order_id' => $orderid, 'update_time' => date('Y-m-d H:i:s', time())]);
            }
        }
        return [
            'code' => 0,
            'msg' => '核销成功',
            'data' => [
                'userid' => $userid,
                'unit_price' => $price,
                'total_price' => $cost,
            ]
        ];
    }
}
