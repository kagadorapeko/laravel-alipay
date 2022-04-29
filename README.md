# Alipay SDK For Laravel

## 安装

通过 Composer 安装

```shell
$ composer require kagadorapeko/laravel-alipay
```

## 配置

在 `.env` 中添加参数

```dotenv
ALIPAY_APPID="APP ID"

ALIPAY_PUBLIC_CERT_PEM="
-----BEGIN PUBLIC KEY-----
支付宝公钥
-----END PUBLIC KEY-----
"

ALIPAY_MERCHANT_KEY_PEM="
-----BEGIN RSA PRIVATE KEY-----
商户私钥
-----END RSA PRIVATE KEY-----
"
```

## 使用

```php
// 价格：1元
$amount = 100;

// 订单号
$orderNo = '2022-03-22';

//回调链接
$callbackUrl = 'https://www.google.com/';

// 注入支付服务
$alipayService = app(\KagaDorapeko\Laravel\Alipay\AlipayService::class);

// 获取支付凭证
$payload = $alipayService->handleAppPayment($amount, $orderNo, $callbackUrl);

// 发起退款
$alipayService->handleRefundAll($amount, $orderNo, '重复支付');

// 注入请求
$request = app(\Illuminate\Http\Request::class);

// 支付回调验签并获取数据
if (!$response = $alipayService->handleNotifyPayment($request)) {
    throw new Exception('验签失败');
}

if ($response['trade_status'] !== 'TRADE_SUCCESS') {
    throw new Exception('交易尚未成功，同志仍需努力');
}
```