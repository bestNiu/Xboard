<?php

namespace Plugin\Checkin\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\Plugin\PluginManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckinController extends Controller
{
    use ApiResponse;

    protected array $config;

    public function __construct()
    {
        $pluginManager = app(PluginManager::class);
        $plugins = $pluginManager->getEnabledPlugins();
        $this->config = isset($plugins['checkin']) ? $plugins['checkin']->getConfig() : [];
    }

    /**
     * 每日签到
     */
    public function checkin(Request $request)
    {
        $user = User::find($request->user()->id);
        if (!$user) {
            return $this->fail([400, __('The user does not exist')]);
        }

        // 检查是否需要有效订阅
        $requireActivePlan = $this->config['require_active_plan'] ?? false;
        if ($requireActivePlan && !$user->isActive()) {
            return $this->fail([400, '需要有效订阅才能签到']);
        }

        // 检查今日是否已签到
        $today = strtotime(date('Y-m-d'));
        if ($user->last_checkin_at && $user->last_checkin_at >= $today) {
            return $this->fail([400, '今日已签到，请明天再来']);
        }

        // 获取配置的流量范围
        $minTraffic = $this->config['min_traffic'] ?? 1;
        $maxTraffic = $this->config['max_traffic'] ?? 5;

        // 确保最小值不大于最大值
        if ($minTraffic > $maxTraffic) {
            $minTraffic = $maxTraffic;
        }

        // 随机流量 (GB 转换为字节)
        $trafficGB = rand($minTraffic, $maxTraffic);
        $trafficBytes = $trafficGB * 1024 * 1024 * 1024;

        // 更新用户流量和签到时间
        $user->transfer_enable = ($user->transfer_enable ?? 0) + $trafficBytes;
        $user->last_checkin_at = time();

        if (!$user->save()) {
            return $this->fail([500, '签到失败，请稍后重试']);
        }

        return $this->success([
            'traffic' => $trafficBytes,
            'traffic_gb' => $trafficGB,
            'message' => "签到成功！获得 {$trafficGB} GB 流量"
        ]);
    }

    /**
     * 获取签到状态
     */
    public function status(Request $request)
    {
        $user = User::find($request->user()->id);
        if (!$user) {
            return $this->fail([400, __('The user does not exist')]);
        }

        $today = strtotime(date('Y-m-d'));
        $isCheckedIn = $user->last_checkin_at && $user->last_checkin_at >= $today;

        return $this->success([
            'is_checked_in' => $isCheckedIn,
            'last_checkin_at' => $user->last_checkin_at,
            'min_traffic' => $this->config['min_traffic'] ?? 1,
            'max_traffic' => $this->config['max_traffic'] ?? 5
        ]);
    }
}

