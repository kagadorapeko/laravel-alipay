<?php

namespace KagaDorapeko\Laravel\Alipay;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

class AlipayService
{
    protected array $config;

    protected string $endPoint = 'https://openapi.alipay.com/gateway.do';

    public function __construct()
    {
        $this->refreshConfig();
    }

    public function refreshConfig(): void
    {
        $this->config = config('alipay');
    }

    public function handleAppPayment(int $amount, string $orderNo, string $callbackUrl): array
    {
        $appPaymentParams = $this->getAppPaymentParams('alipay.trade.app.pay', [
            'total_amount' => (string)($amount / 100),
            'out_trade_no' => $orderNo,
            'subject' => $orderNo,
        ]);

        $appPaymentParams['notify_url'] = $callbackUrl;

        $appPaymentParams['sign'] = $this->getAppPaymentSign($appPaymentParams);

        return $appPaymentParams;
    }

    /**
     * @throws Exception
     */
    public function handleRefundAll(int $amount, string $orderNo, string $reason = ''): array
    {
        $appPaymentParams = $this->getAppPaymentParams('alipay.trade.refund', [
            'refund_amount' => (string)($amount / 100),
            'refund_reason' => $reason,
            'out_trade_no' => $orderNo,
        ]);

        $appPaymentParams['sign'] = $this->getAppPaymentSign($appPaymentParams);

        $request = Http::retry(1)->withHeaders([
            'Content-Type' => 'application/json; charset=utf-8',
        ]);

        $queryStr = http_build_query($appPaymentParams);

        $response = $request->post("$this->endPoint?" . $queryStr);

        if (!$response->ok() or !is_array($responseData = $response->json())) {
            throw new Exception($response->body());
        }

        if (!is_string($sign = $responseData['sign'] ?? null)){
            throw new Exception($response->body());
        }

        if (!is_array($refund = $responseData['alipay_trade_refund_response'] ?? null)){
            throw new Exception($response->body());
        }

        if (($refund['code'] ?? 0) != 10000) {
            throw new Exception($response->body());
        }

        $verified = openssl_verify(
            json_encode($refund), base64_decode($sign),
            $this->getPlatformPublicKey(), OPENSSL_ALGO_SHA256
        );

        if ($verified === 1) return $refund;

        throw new Exception($response->body());
    }

    public function handleNotifyPayment(Request $request): array|null
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
            if (!str_starts_with($value, '@')) {
                $value = "$key=$value";
            }
            else {
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
            if (!str_starts_with($value, '@')) {
                $value = "$key=$value";
            }
            else {
                unset($data[$key]);
            }
        }

        if (!empty($data)) {
            ksort($data);
            return implode('&', $data);
        }

        return null;
    }

    protected function getAppPaymentParams(string $method, array $bizContent): array
    {
        return [
            'method' => $method,
            'app_id' => $this->config['appid'],
            'timestamp' => date('Y-m-d H:i:s'),
            'format' => 'json',
            'version' => '1.0',
            'alipay_sdk' => 'alipay-easysdk-php-2.2.0',
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2',
            'app_cert_sn' => null,
            'alipay_root_cert_sn' => null,
            'biz_content' => json_encode($bizContent),
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
