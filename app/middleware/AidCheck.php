<?php

namespace app\middleware;
use app\model\UserModel;
use app\model\UserWebModel;
use app\service\ConfigService;
use think\facade\Log;


class AidCheck
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 获取Header中的Authorization
        $aid = $request->header('aid');

        // 验证Authorization是否存在
        if (empty($aid)) {
            return json([
                'code' => 1001,
                'msg' => '不能找到商家'
            ], 401);
        }
        $userweb = UserWebModel::where('webid', $aid)->find();
        $userid = 0;
        if(empty($userweb)){
            $default_aid = ConfigService::get("default_aid");
            $userid = $default_aid;
        }else{
            $userid = $userweb->userid;
        }
        if(empty($userid)){
            return json([
                'code' => 1001,
                'msg' => '不能找到商家'
            ], 401);
        }

        $user = UserModel::where('id', $userid)->find();

        if (empty($user)) {
            return json([
                'code' => 1001,
                'msg' => '不能找到商家'
            ], 401);
        }
        if ($user->status != 1) {
            return json([
                'code' => 1001,
                'msg' => '账户禁用，请联系管理员'
            ], 401);
        }

        $request->userid = $user->id;
        return $next($request);
    }
}
