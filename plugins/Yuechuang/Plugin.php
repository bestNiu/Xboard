<?php

namespace Plugin\Yuechuang;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use Curl\Curl;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['Yuechuang'] = [
                    'name' => $this->getConfig('display_name', '悦创支付'),
                    'icon' => $this->getConfig('icon', '💳'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'yuechuang_url' => [
                'label' => '网关地址',
                'type' => 'string',
                'required' => true,
                'description' => '支付网关地址，如：https://api.pay.yuechuangkj.cn'
            ],
            'yuechuang_memberid' => [
                'label' => '商户号',
                'type' => 'string',
                'required' => true,
                'description' => '平台分配的商户号'
            ],
            'yuechuang_apikey' => [
                'label' => '商户APIKEY',
                'type' => 'string',
                'required' => true,
                'description' => '商户API密钥'
            ],
            'yuechuang_bankcode' => [
                'label' => '银行编码',
                'type' => 'string',
                'required' => true,
                'description' => '支付通道编码：933=微信扫码,934=微信H5,935=支付宝扫码,961=支付宝H5,992=微信通用扫码,993=支付宝直付通H5'
            ]
        ];
    }

    public function pay($order): array
    {
        $tradeNo = $this->generateOrderId($order['trade_no']);
        
        $params = [
            'pay_memberid' => $this->getConfig('yuechuang_memberid'),
            'pay_orderid' => $tradeNo,
            'pay_amount' => sprintf('%.2f', $order['total_amount'] / 100),
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => $this->getConfig('yuechuang_bankcode'),
            'pay_notifyurl' => $order['notify_url'],
            'pay_callbackurl' => $order['return_url'],
        ];

        // 生成签名
        $params['pay_md5sign'] = $this->generateSign($params);
        
        // 添加非签名参数
        $params['pay_attach'] = $order['trade_no']; // 原始订单号作为附加字段
        $params['pay_productname'] = '订阅服务';

        // 构建请求URL
        $gatewayUrl = rtrim($this->getConfig('yuechuang_url'), '/') . '/Pay_Index.html';
        
        // 记录请求日志便于调试
        \Log::info('Yuechuang Payment Request', [
            'url' => $gatewayUrl,
            'params' => $params
        ]);

        $curl = new Curl();
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $curl->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        $curl->setOpt(CURLOPT_FOLLOWLOCATION, 1);
        $curl->setOpt(CURLOPT_TIMEOUT, 30);
        // 使用 http_build_query 编码参数，确保正确的 POST 格式
        $curl->post($gatewayUrl, http_build_query($params));
        
        $rawResponse = $curl->rawResponse;
        $result = $curl->response;
        
        // 记录响应日志
        \Log::info('Yuechuang Payment Response', [
            'http_code' => $curl->httpStatusCode,
            'raw_response' => $rawResponse,
            'error' => $curl->error ? $curl->errorMessage : null
        ]);

        if (!$rawResponse) {
            throw new ApiException('网络请求失败: 无响应');
        }

        if ($curl->error) {
            throw new ApiException('请求错误: ' . $curl->errorMessage);
        }

        $curl->close();

        // 处理返回结果
        if (is_string($result)) {
            $result = json_decode($result, true);
        } elseif (is_object($result)) {
            $result = json_decode(json_encode($result), true);
        }

        // 如果JSON解析失败，尝试直接解析原始响应
        if (!$result && $rawResponse) {
            $result = json_decode($rawResponse, true);
        }

        if (!$result) {
            throw new ApiException('返回数据解析失败: ' . substr($rawResponse, 0, 200));
        }

        // 首先检查是否有错误
        // 错误格式1: {"status": 0, "msg": "xxx"} 或 {"status": "error", "msg": "xxx"}
        if (isset($result['status'])) {
            $status = $result['status'];
            // status 为 0 或 "error" 表示失败
            if ($status === 0 || $status === '0' || $status === 'error') {
                $errorMsg = $result['msg'] ?? $result['message'] ?? '未知错误';
                throw new ApiException($errorMsg);
            }
        }

        // H5支付返回 h5_url
        if (isset($result['h5_url']) && !empty($result['h5_url'])) {
            // 清理URL中的空格、换行符等
            $h5Url = preg_replace('/\s+/', '', trim($result['h5_url']));
            return [
                'type' => 1, // 跳转支付
                'data' => $h5Url
            ];
        }

        // 扫码支付返回 scan_url
        if (isset($result['scan_url']) && !empty($result['scan_url'])) {
            // 清理URL中的空格、换行符等
            $scanUrl = preg_replace('/\s+/', '', trim($result['scan_url']));
            return [
                'type' => 0, // 二维码支付
                'data' => $scanUrl
            ];
        }

        // 其他可能的错误格式
        if (isset($result['code']) && $result['code'] != 0 && $result['code'] != 200) {
            throw new ApiException($result['msg'] ?? $result['message'] ?? json_encode($result));
        }

        throw new ApiException('接口返回异常: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function notify($params): array|bool
    {
        // 验证签名字段
        $signFields = [
            'memberid',
            'orderid',
            'amount',
            'transaction_id',
            'datetime',
            'returncode',
        ];

        $signData = [];
        foreach ($signFields as $field) {
            if (isset($params[$field])) {
                $signData[$field] = $params[$field];
            }
        }

        // 生成验签
        $sign = $params['sign'] ?? '';
        $calculatedSign = $this->generateNotifySign($signData);

        if (strtoupper($sign) !== $calculatedSign) {
            return false;
        }

        // 验证交易状态 "00" 为成功
        if (($params['returncode'] ?? '') !== '00') {
            return false;
        }

        // 从 attach 中获取原始订单号，如果没有则从 orderid 中提取
        $tradeNo = $params['attach'] ?? $this->extractOriginalOrderId($params['orderid'] ?? '');

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $params['transaction_id'] ?? ''
        ];
    }

    /**
     * 生成请求签名
     */
    private function generateSign(array $params): string
    {
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $val) {
            if ($val !== '' && $val !== null) {
                $signStr .= $key . '=' . $val . '&';
            }
        }
        $signStr .= 'key=' . $this->getConfig('yuechuang_apikey');
        
        return strtoupper(md5($signStr));
    }

    /**
     * 生成回调验签
     */
    private function generateNotifySign(array $params): string
    {
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $val) {
            if ($val !== '' && $val !== null) {
                $signStr .= $key . '=' . $val . '&';
            }
        }
        $signStr .= 'key=' . $this->getConfig('yuechuang_apikey');
        
        return strtoupper(md5($signStr));
    }

    /**
     * 生成符合要求的订单号（20位字符）
     * 
     * 订单号组成：
     * - 8位日期 (YmdHis 的 Ymd 部分)
     * - 4位微秒 (确保同一秒内的请求有差异)
     * - 4位随机数 (额外保障唯一性)
     * - 4位 trade_no 的 MD5 (关联原始订单)
     * 
     * 总长度：8 + 4 + 4 + 4 = 20位
     */
    private function generateOrderId(string $tradeNo): string
    {
        // 获取日期 YmdHis (14位) 但只取前8位日期部分
        $date = date('Ymd'); // 8位
        
        // 获取微秒时间戳的后4位，确保同一秒内请求的差异
        $microtime = substr(str_replace('.', '', microtime(true)), -6, 4); // 4位
        
        // 生成4位随机数，额外保障唯一性
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // 4位
        
        // 取 trade_no MD5 的前4位，用于关联原始订单
        $hash = substr(md5($tradeNo . microtime(true)), 0, 4); // 4位
        
        // 总长度：8 + 4 + 4 + 4 = 20位
        return $date . $microtime . $random . $hash;
    }

    /**
     * 从支付网关订单号中提取原始订单号
     */
    private function extractOriginalOrderId(string $orderId): string
    {
        // 如果无法从 attach 获取，返回原始订单号
        return $orderId;
    }
}

