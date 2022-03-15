<?php

namespace KagaDorapeko\Laravel\Alipay;

use Illuminate\Http\Request;
use OpenSSLAsymmetricKey;

class AlipayService
{
    protected array $config;

    public function __construct()
    {
        $this->refreshConfig();
    }

    public function refreshConfig()
    {
        $this->config = config('alipay');
    }

    public function handleAppPayment(int $amount, string $orderNo, string $callbackUrl): array
    {
        $appPaymentParams = $this->getAppPaymentParams([
            'alipay_app_id' => $this->config['appid'],
            'callback_url' => $callbackUrl,
            'amount' => $amount / 100,
            'order_no' => $orderNo,
        ]);

        return array_merge($appPaymentParams, [
            'sign' => $this->getAppPaymentSign($appPaymentParams),
        ]);
    }

    public function handelNotifyPayment(Request $request): array|null
    {
        $data = $request->post();

        if (!is_array($data) or empty($data)) return null;

        if (empty($data['sign']) or !is_string($data['sign'])) return null;

        if (empty($verifyContent = $this->getVerifyContent($data))) return null;

        $verified = openssl_verify(
            $verifyContent, base64_decode($data['sign']),
            $this->getPlatformPublicKey(), OPENSSL_ALGO_SHA256
        );

        if ($verified === 1) return $data;

        return null;
    }

    protected function getSignContent(array $params): string
    {
        $params = array_filter($params);

        foreach ($params as $key => &$value) {
            if (!str_starts_with($value, "@")) {
                $value = "$key=$value";
            } else {
                unset($params[$key]);
            }
        }

        ksort($params);

        return implode('&', $params);
    }

    public function getVerifyContent(array $data): string|null
    {
        unset($data['sign']);
        unset($data['sign_type']);

        // 不剔除空值
        // $data = array_filter($data);

        foreach ($data as $key => &$value) {
            if (!str_starts_with($value, "@")) {
                $value = "$key=$value";
            } else {
                unset($data[$key]);
            }
        }

        if (!empty($data)) {
            ksort($data);
            return implode('&', $data);
        }

        return null;
    }

    protected function getAppPaymentParams(array $params): array
    {
        return [
            "method" => "alipay.trade.app.pay",
            "app_id" => $params['alipay_app_id'],
            "timestamp" => date("Y-m-d H:i:s"),
            "format" => "json",
            "version" => "1.0",
            "alipay_sdk" => 'alipay-easysdk-php-2.2.0',
            "charset" => "UTF-8",
            "sign_type" => 'RSA2',
            "app_cert_sn" => null,
            "alipay_root_cert_sn" => null,
            'biz_content' => json_encode([
                "subject" => $params['order_no'],
                "out_trade_no" => $params['order_no'],
                "total_amount" => (string)$params['amount'],
            ]),
            'notify_url' => $params['callback_url'],
        ];
    }

    protected function getAppPaymentSign(array $paymentParams): string
    {
        $signContent = $this->getSignContent($paymentParams);
        $merchantPrivateKey = $this->getMerchantPrivateKey();

        openssl_sign($signContent, $paymentSign, $merchantPrivateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($paymentSign);
    }

    protected function getMerchantPrivateKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_private($this->config['merchant_key_pem']);
    }

    protected function getPlatformPublicKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_get_public($this->config['public_cert_pem']);
    }
}
