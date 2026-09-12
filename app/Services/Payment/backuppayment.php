<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
use RuntimeException;

class PaymentGatewayService
{
    private string $baseUrl;

    private string $token;

    private string $merchantAccountId;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.bakong.url'),
            '/'
        );

        $this->token = (string) config(
            'services.bakong.token'
        );

        $this->merchantAccountId = (string) config(
            'services.bakong.merchant_account_id'
        );
    }

    public function generateQr(
        float $amount,
        string $currency = 'USD',
        ?string $billNumber = null
    ): array {
        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        if (! in_array($currency, ['USD', 'KHR'], true)) {
            throw new RuntimeException(
                'Unsupported currency.'
            );
        }

        $khqrCurrency = match ($currency) {
            'USD' => KHQRData::CURRENCY_USD,
            'KHR' => KHQRData::CURRENCY_KHR,
        };
        $expiresAt = now()->addMinutes(5);

        $expirationTimestamp = (string) $expiresAt->valueOf();

        $info = new IndividualInfo(
            bakongAccountID: $this->merchantAccountId,
            merchantName: 'SOPHEAK SHOP',
            merchantCity: 'PHNOM PENH',
            currency: $khqrCurrency,
            amount: $amount,
            expirationTimestamp: $expirationTimestamp,
        );

        if ($billNumber !== null) {
            $info->billNumber = $billNumber;
        }

        $result = BakongKHQR::generateIndividual($info);
        if (($result->status['code'] ?? null) !== 0) {
            throw new RuntimeException(
                $result->status['message']
                ?? 'Failed to generate KHQR.'
            );
        }

        $qr = $result->data['qr'] ?? null;

        $md5 = $result->data['md5'] ?? null;

        if (! $qr || ! $md5) {
            throw new RuntimeException(
                'KHQR generation returned incomplete data.'
            );
        }

        return [
            'qr' => $qr,
            'md5' => $md5,
            'bill_number' => $billNumber,
            'expired_at' => $expiresAt,
        ];
    }

    public function checkMd5(string $md5): array
    {
        if ($md5 === '') {
            throw new RuntimeException(
                'MD5 is required.'
            );
        }

        try {
            $response = Http::timeout(15)
                ->withOptions([
                    'version' => CURL_HTTP_VERSION_1_1,
                ])
                ->withToken($this->token)
                ->acceptJson()
                ->post(
                    $this->baseUrl.'/v1/check_transaction_by_md5',
                    [
                        'md5' => $md5,
                    ]
                );

            return $this->handleBakongResponse($response);

        } catch (ConnectionException $e) {
            logger()->error(
                'BAKONG CONNECTION ERROR',
                [
                    'message' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Bakong verification service is temporarily unavailable.'
            );
        }
    }

    public function checkAccount(): array
    {
        try {
            $response = Http::timeout(15)
                ->withOptions([
                    'version' => CURL_HTTP_VERSION_1_1,
                ])
                ->withToken($this->token)
                ->acceptJson()
                ->post(
                    $this->baseUrl.'/v1/check_bakong_account',
                    [
                        'accountId' => $this->merchantAccountId,
                    ]
                );

            return $this->handleBakongResponse($response);

        } catch (ConnectionException $e) {
            logger()->error(
                'BAKONG ACCOUNT CHECK CONNECTION ERROR',
                [
                    'message' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Bakong service is temporarily unavailable.'
            );
        }
    }

    private function handleBakongResponse(
        Response $response
    ): array {
        if ($response->status() === 401) {
            throw new RuntimeException(
                'Bakong authentication failed.'
            );
        }
        if ($response->status() === 403) {
            throw new RuntimeException(
                'Bakong API access forbidden.'
            );
        }
        if ($response->status() === 429) {
            throw new RuntimeException(
                'Bakong API rate limit exceeded.'
            );
        }
        if ($response->serverError()) {
            throw new RuntimeException(
                'Bakong API server error.'
            );
        }
        if ($response->failed()) {
            throw new RuntimeException(
                'Bakong API request failed.'
            );
        }

        return $response->json();
    }
}
