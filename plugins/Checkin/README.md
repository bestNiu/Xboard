# 每日签到插件

为 XBoard 面板提供每日签到获取随机流量奖励的功能。

## 功能特性

- 每日签到获取随机流量奖励
- 可配置流量奖励范围（默认 1-5 GB）
- 可配置是否需要有效订阅才能签到
- 自动追加签到状态到用户订阅信息

## 安装方法

1. 将插件目录放置在 `plugins/Checkin/`
2. 在管理后台 -> 插件管理中安装并启用插件
3. 插件会自动运行数据库迁移，添加 `last_checkin_at` 字段

## API 接口

### 每日签到

**请求**
```
POST /api/v1/user/checkin
Authorization: Bearer {token}
```

**响应成功**
```json
{
    "status": "success",
    "data": {
        "traffic": 3221225472,
        "traffic_gb": 3,
        "message": "签到成功！获得 3 GB 流量"
    }
}
```

**响应失败（已签到）**
```json
{
    "status": "error",
    "message": "今日已签到，请明天再来"
}
```

### 获取签到状态

**请求**
```
GET /api/v1/user/checkin/status
Authorization: Bearer {token}
```

**响应**
```json
{
    "status": "success",
    "data": {
        "is_checked_in": false,
        "last_checkin_at": 1704067200,
        "min_traffic": 1,
        "max_traffic": 5
    }
}
```

## 配置选项

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| min_traffic | number | 1 | 签到获得的最小流量（GB） |
| max_traffic | number | 5 | 签到获得的最大流量（GB） |
| require_active_plan | boolean | false | 是否要求用户拥有有效订阅才能签到 |

## 更新日志

### v1.0.0
- 初始版本
- 支持每日签到
- 支持获取签到状态
- 支持配置流量范围

