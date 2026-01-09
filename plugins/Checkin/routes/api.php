<?php

use Illuminate\Support\Facades\Route;
use Plugin\Checkin\Controllers\CheckinController;

/*
|--------------------------------------------------------------------------
| Checkin Plugin API Routes
|--------------------------------------------------------------------------
|
| 签到插件的 API 路由
|
*/

Route::group([
    'prefix' => 'api/v1/user',
    'middleware' => ['api', 'user']
], function () {
    // 每日签到
    Route::post('/checkin', [CheckinController::class, 'checkin']);
    // 获取签到状态
    Route::get('/checkin/status', [CheckinController::class, 'status']);
});

