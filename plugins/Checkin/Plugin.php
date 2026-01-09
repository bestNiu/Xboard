<?php

namespace Plugin\Checkin;

use App\Services\Plugin\AbstractPlugin;
use App\Services\Plugin\HookManager;
use App\Utils\Helper;

class Plugin extends AbstractPlugin
{
    /**
     * 插件启动时调用
     */
    public function boot(): void
    {
        // 在用户订阅信息中添加签到状态
        $this->filter('user.subscribe.response', [$this, 'appendCheckinStatus'], 10);
    }

    /**
     * 在用户订阅响应中追加签到状态
     */
    public function appendCheckinStatus($user): mixed
    {
        if (!$user) {
            return $user;
        }

        $today = strtotime(date('Y-m-d'));
        $lastCheckinAt = $user['last_checkin_at'] ?? null;
        
        $user['checkin'] = [
            'is_checked_in' => $lastCheckinAt && $lastCheckinAt >= $today,
            'last_checkin_at' => $lastCheckinAt,
            'min_traffic' => $this->getConfig('min_traffic', 1),
            'max_traffic' => $this->getConfig('max_traffic', 5)
        ];

        return $user;
    }

    /**
     * 插件安装时调用
     */
    public function install(): void
    {
        // 插件安装时的初始化逻辑
    }

    /**
     * 插件卸载时调用
     */
    public function cleanup(): void
    {
        // 插件卸载时的清理逻辑
    }
}

