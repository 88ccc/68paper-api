<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('manage', function () {
    // 这里配置manage下的所有路由
    Route::rule(':action', 'Manage/:action');
})->middleware(\app\middleware\AuthCheck::class);

Route::group('console', function () {
    Route::rule(':action', 'Console/:action');
})->middleware(\app\middleware\AuthCheck::class);

Route::group('check', function () {
    Route::rule(':action', 'Check/:action');
})->middleware(\app\middleware\AidCheck::class);


Route::group('notify', function () {
    Route::rule('alipay/:modeid', "Notify/alipay");
    Route::rule('wxpay/:modeid', "Notify/wxpay");
});
