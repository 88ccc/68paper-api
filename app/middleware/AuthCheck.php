<?php

namespace app\middleware;

use app\model\AdminModel;
use app\model\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Log;

class AuthCheck
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
        $authorization = $request->header('authorization');
        $userid = $request->header('userid');

        $baseUrl = $request->baseUrl();
        //Log::write('auth check', 'baseUrl:'.$baseUrl);

        // 验证Authorization是否存在
        if (empty($authorization) || empty($userid)) {
            Log::write('auth check', 'no authorization');
            return json([
                'code' => 401,
                'msg' => '登录已经过期，请重新登录'
            ], 401);
        }
        $isAdmin = false;
        if (str_starts_with($baseUrl, "/manage")) {
            $isAdmin = true;
        }
        if ($isAdmin) {
            $isAdmin = true;
            $user = AdminModel::where('id', $userid)->find();
        } else {
            $user = UserModel::where('id', $userid)->find();
        }
        if (empty($user)) {
            Log::write('auth check', 'user not found');
            return json([
                'code' => 401,
                'msg' => '登录已经过期，请重新登录'
            ], 401);
        }
        if ($user->status != 1) {
            Log::write('auth check', 'user status not ok');
            return json([
                'code' => 401,
                'msg' => '账户禁用，请联系管理员'
            ], 401);
        }
        try {
            $jwt = JWT::decode($authorization, new Key($user->pass, 'HS256'));
        } catch (\Exception $e) {
            Log::write('auth check', 'jwt decode error');
            return json([
                'code' => 401,
                'msg' => '登录已经过期，请重新登录'
            ], 401);
        }

        if ($jwt->data->id != $userid) {
            Log::write('auth check', 'jwt data id not ok');
            return json([
                'code' => 401,
                'msg' => '登录已经过期，请重新登录'
            ], 401);
        }

        //验证是否过期
        if ($jwt->exp < time()) {
            Log::write('auth check', 'jwt expire');
            return json([
                'code' => 401,
                'msg' => '登录已经过期，请重新登录'
            ], 401);
        }
        $request->userid = $userid;
        return $next($request);
    }
}
