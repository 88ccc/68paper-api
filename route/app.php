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
    // 例如：
    Route::rule('adminInfo', 'Manage/adminInfo');
    Route::rule('getStorageConfig', 'Manage/getStorageConfig');
    Route::rule('setStorageConfig', 'Manage/setStorageConfig');
    Route::rule('setCustomConfig', 'Manage/setCustomConfig');
    Route::rule('setPrivacyPolicy', 'Manage/setPrivacyPolicy');
    Route::rule('setUserAgreement', 'Manage/setUserAgreement');
    Route::rule('clearWxPublicConfig', 'Manage/clearWxPublicConfig');
    Route::rule('getWxPublicConfig', 'Manage/getWxPublicConfig');
    Route::rule('setWxPublicConfig', 'Manage/setWxPublicConfig');
    Route::rule('setPayMode', 'Manage/setPayMode');
    Route::rule('getPayMode', 'Manage/getAllPayMode');
    Route::rule('deletePayMode', 'Manage/deletePayMode');
    Route::rule('getPaySet', 'Manage/getPaySet');
    Route::rule('setPaySet', 'Manage/setPaySet');
    Route::rule('getAdminData', 'Manage/getAdminData');
    Route::rule('editAdmin', 'Manage/editAdmin');
    Route::rule('setCheckKeyConfig', 'Manage/setCheckKeyConfig');
    Route::rule('getCheckKeyConfig', 'Manage/getCheckKeyConfig');
    Route::rule('clearCheckKeyConfig', 'Manage/clearCheckKeyConfig');
    Route::rule('syncCheckSystem', 'Manage/syncCheckSystem');
    Route::rule('getCheckData', 'Manage/getCheckData');
    Route::rule('desableCheckProduct', 'Manage/desableCheckProduct');
    Route::rule('enableCheckProduct', 'Manage/enableCheckProduct');
    Route::rule('deleteCheckProduct', 'Manage/deleteCheckProduct');
    Route::rule('getShopData', 'Manage/getShopData');
    Route::rule('addShop', 'Manage/addShop');
    Route::rule('deleteShop', 'Manage/deleteShop');
    Route::rule('desableShop', 'Manage/desableShop');
    Route::rule('enableShop', 'Manage/enableShop');
    Route::rule('getShopInfo', 'Manage/getShopInfo');
    Route::rule('uploadShopFile','Manage/uploadShopFile');
    Route::rule('deleteShopFile','Manage/deleteShopFile');
    Route::rule('syncShopProduct','Manage/syncShopProduct');
    Route::rule('getShopProductData','Manage/getShopProductData');
    Route::rule('editShopProduct','Manage/editShopProduct');
    Route::rule('getCheckOrderData','Manage/getCheckOrderData');
    Route::rule('orderRefund','/Manage/orderRefund');
    Route::rule('homeData','Manage/homeData');
})->middleware(\app\middleware\AuthCheck::class);

Route::group('notify', function () {
    Route::rule('alipay/:modeid',"Notify/alipay");
    Route::rule('wxpay/:modeid',"Notify/wxpay");
});
