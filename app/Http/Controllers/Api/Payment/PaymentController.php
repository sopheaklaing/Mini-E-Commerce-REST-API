<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentGatewayService $paymentGatewayService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Create Payment
    |--------------------------------------------------------------------------
    */

    public function create(
        Request $request,
        Order $order
    ): JsonResponse {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed to pay for this order.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'This order cannot be paid.',
            ], 422);
        }

        if ((float) $order->total <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order total must be greater than zero.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Reuse Existing Pending Payment
        |--------------------------------------------------------------------------
        */

        $existingPayment = Payment::where(
            'order_id',
            $order->id
        )
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPayment) {
            return response()->json([
                'status' => 'success',
                'message' => 'Pending payment already exists.',
                'data' => $existingPayment,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Payment
        |--------------------------------------------------------------------------
        */

        $payment = Payment::create([
            'order_id' => $order->id,
            'bill_number' => 'ORDER-'.$order->id.'-'.time(),
            'amount' => (float) $order->total,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment created successfully.',
            'data' => $payment,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate QR
    |--------------------------------------------------------------------------
    */

    public function generateQr(
        Request $request,
        Payment $payment
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if (
            $payment->order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed to access this payment.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Already Paid
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'This payment has already been paid.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Expired
        |--------------------------------------------------------------------------
        */

        if (
            $payment->expired_at !== null
            && now()->greaterThan($payment->expired_at)
        ) {
            $payment->update([
                'status' => 'expired',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'This payment has expired.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate KHQR
        |--------------------------------------------------------------------------
        */

        try {
            $qrData = $this->paymentGatewayService->generateQr(
                amount: (float) $payment->amount,
                currency: $payment->currency,
                billNumber: $payment->bill_number
            );

            $payment->update([
                'qr_code' => $qrData['qr'],
                'md5' => $qrData['md5'],
                'expired_at' => $qrData['expired_at'],
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'KHQR generated successfully.',
                'data' => $payment->fresh(),
            ]);
        } catch (RuntimeException $e) {
            Log::error(
                'KHQR GENERATION ERROR',
                [
                    'payment_id' => $payment->id,
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm / Check Payment Status
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | This endpoint is NOT automatically called.
    |
    | Frontend calls this endpoint only when the user clicks
    | "Confirm Payment".
    |
    */

    public function status(
        Request $request,
        Payment $payment
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if (
            $payment->order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed to access this payment.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Already Paid
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return response()->json([
                'status' => 'paid',
                'message' => 'Payment has already been completed.',
                'data' => $payment->fresh(),
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Expired
        |--------------------------------------------------------------------------
        */

        if (
            $payment->expired_at !== null
            && now()->greaterThan($payment->expired_at)
        ) {
            $payment->update([
                'status' => 'expired',
            ]);

            return response()->json([
                'status' => 'expired',
                'message' => 'Payment QR has expired.',
                'data' => $payment->fresh(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | MD5 Not Generated
        |--------------------------------------------------------------------------
        */

        if (! $payment->md5) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Payment QR has not been generated yet.',
                'data' => $payment->fresh(),
            ], 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Bakong
        |--------------------------------------------------------------------------
        */

        try {
            $result = $this->paymentGatewayService->checkMd5(
                $payment->md5
            );

            Log::info(
                'BAKONG CHECK MD5 RESULT',
                [
                    'payment_id' => $payment->id,
                    'result' => $result,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Bakong Service Unavailable
            |--------------------------------------------------------------------------
            */

            if (
                ($result['responseCode'] ?? null) === 1
                && ($result['errorCode'] ?? null) === 15
            ) {
                return response()->json([
                    'status' => 'verification_unavailable',
                    'message' => $result['responseMessage']
                        ?? 'Bakong verification service is currently unavailable.',
                    'data' => $payment->fresh(),
                ], 503);
            }

            /*
            |--------------------------------------------------------------------------
            | Bakong Request Limit
            |--------------------------------------------------------------------------
            */

            if (
                ($result['responseCode'] ?? null) === 1
                && ($result['errorCode'] ?? null) === 17
            ) {
                return response()->json([
                    'status' => 'verification_limited',
                    'message' => $result['responseMessage']
                        ?? 'Bakong verification limit has been exceeded. Please try again later.',
                    'data' => $payment->fresh(),
                ], 429);
            }

            /*
            |--------------------------------------------------------------------------
            | Transaction Not Found / Still Pending
            |--------------------------------------------------------------------------
            |
            | This is NOT an error.
            |
            | The user may have clicked Confirm Payment before Bakong
            | has made the transaction available for verification.
            |
            | Return HTTP 200 so Axios does not treat this as an error.
            |--------------------------------------------------------------------------
            */

            if (($result['responseCode'] ?? null) !== 0) {
                return response()->json([
                    'status' => 'pending',
                    'message' => $result['responseMessage']
                        ?? 'Payment has not been verified yet.',
                    'data' => $payment->fresh(),
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | Transaction Data
            |--------------------------------------------------------------------------
            */

            $transaction = $result['data'] ?? null;

            if (! is_array($transaction)) {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Transaction data is not available yet.',
                    'data' => $payment->fresh(),
                ], 200);
            }

            /*
            |--------------------------------------------------------------------------
            | Extract Transaction Amount
            |--------------------------------------------------------------------------
            */

            $transactionAmount = $this->extractTransactionAmount(
                $transaction
            );

            if ($transactionAmount === null) {
                Log::warning(
                    'BAKONG TRANSACTION AMOUNT MISSING',
                    [
                        'payment_id' => $payment->id,
                        'transaction' => $transaction,
                    ]
                );

                return response()->json([
                    'status' => 'verification_error',
                    'message' => 'Transaction amount could not be verified.',
                    'data' => $payment->fresh(),
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Amount
            |--------------------------------------------------------------------------
            */

            $expectedAmount = round(
                (float) $payment->amount,
                2
            );

            $transactionAmount = round(
                $transactionAmount,
                2
            );

            if ($transactionAmount !== $expectedAmount) {
                Log::warning(
                    'BAKONG AMOUNT MISMATCH',
                    [
                        'payment_id' => $payment->id,
                        'expected' => $expectedAmount,
                        'received' => $transactionAmount,
                    ]
                );

                return response()->json([
                    'status' => 'verification_error',
                    'message' => 'Payment amount does not match the order amount.',
                    'data' => $payment->fresh(),
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Currency
            |--------------------------------------------------------------------------
            */

            $transactionCurrency = $this->extractTransactionCurrency(
                $transaction
            );

            if (
                $transactionCurrency !== null
                && strtoupper($transactionCurrency)
                    !== strtoupper($payment->currency)
            ) {
                Log::warning(
                    'BAKONG CURRENCY MISMATCH',
                    [
                        'payment_id' => $payment->id,
                        'expected' => $payment->currency,
                        'received' => $transactionCurrency,
                    ]
                );

                return response()->json([
                    'status' => 'verification_error',
                    'message' => 'Payment currency does not match the order currency.',
                    'data' => $payment->fresh(),
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Merchant Account
            |--------------------------------------------------------------------------
            */

            $transactionAccount = $this->extractMerchantAccount(
                $transaction
            );

            $expectedMerchantAccount = (string) config(
                'services.bakong.merchant_account_id'
            );

            if (
                $transactionAccount !== null
                && $expectedMerchantAccount !== ''
                && $transactionAccount !== $expectedMerchantAccount
            ) {
                Log::warning(
                    'BAKONG MERCHANT ACCOUNT MISMATCH',
                    [
                        'payment_id' => $payment->id,
                        'expected' => $expectedMerchantAccount,
                        'received' => $transactionAccount,
                    ]
                );

                return response()->json([
                    'status' => 'verification_error',
                    'message' => 'Payment receiver does not match the merchant account.',
                    'data' => $payment->fresh(),
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Bill Number
            |--------------------------------------------------------------------------
            */

            $transactionBillNumber = $this->extractBillNumber(
                $transaction
            );

            if (
                $transactionBillNumber !== null
                && $payment->bill_number !== null
                && $transactionBillNumber !== $payment->bill_number
            ) {
                Log::warning(
                    'BAKONG BILL NUMBER MISMATCH',
                    [
                        'payment_id' => $payment->id,
                        'expected' => $payment->bill_number,
                        'received' => $transactionBillNumber,
                    ]
                );

                return response()->json([
                    'status' => 'verification_error',
                    'message' => 'Payment reference does not match.',
                    'data' => $payment->fresh(),
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Mark Payment + Order as Paid
            |--------------------------------------------------------------------------
            */

            $paidPayment = DB::transaction(
                function () use (
                    $payment,
                    $transaction
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | Lock Payment
                    |--------------------------------------------------------------------------
                    */

                    $lockedPayment = Payment::query()
                        ->whereKey($payment->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Prevent Duplicate Processing
                    |--------------------------------------------------------------------------
                    */

                    if ($lockedPayment->status === 'paid') {
                        return $lockedPayment;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Extract Transaction Data
                    |--------------------------------------------------------------------------
                    */

                    $transactionHash =
                        $this->extractTransactionHash(
                            $transaction
                        );

                    $externalReference =
                        $this->extractExternalReference(
                            $transaction
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Update Payment
                    |--------------------------------------------------------------------------
                    */

                    $lockedPayment->update([
                        'status' => 'paid',
                        'transaction_hash' => $transactionHash,
                        'external_ref' => $externalReference,
                        'paid_at' => now(),
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Lock Order
                    |--------------------------------------------------------------------------
                    */

                    $order = Order::query()
                        ->whereKey($lockedPayment->order_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Update Order
                    |--------------------------------------------------------------------------
                    */

                    if ($order->status === 'pending') {
                        $order->update([
                            'status' => 'paid',
                        ]);
                    }

                    return $lockedPayment->fresh();
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Payment Successful
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' => 'paid',
                'message' => 'Payment verified successfully.',
                'data' => $paidPayment,
            ], 200);
        } catch (RuntimeException $e) {
            Log::error(
                'BAKONG CHECK MD5 ERROR',
                [
                    'payment_id' => $payment->id,
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'status' => 'verification_unavailable',
                'message' => $e->getMessage(),
                'data' => $payment->fresh(),
            ], 503);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Payment
    |--------------------------------------------------------------------------
    */

    public function cancel(
        Request $request,
        Payment $payment
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        if (
            $payment->order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed to cancel this payment.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Paid Payment Cannot Be Cancelled
        |--------------------------------------------------------------------------
        */

        if ($payment->status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Paid payment cannot be cancelled.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Cancel
        |--------------------------------------------------------------------------
        */

        $payment->update([
            'status' => 'failed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment cancelled successfully.',
            'data' => $payment->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Transaction Amount
    |--------------------------------------------------------------------------
    */

    private function extractTransactionAmount(
        array $transaction
    ): ?float {
        $value = $transaction['amount']
            ?? $transaction['transactionAmount']
            ?? $transaction['amountValue']
            ?? null;

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Transaction Currency
    |--------------------------------------------------------------------------
    */

    private function extractTransactionCurrency(
        array $transaction
    ): ?string {
        $currency = $transaction['currency']
            ?? $transaction['transactionCurrency']
            ?? null;

        if ($currency === null) {
            return null;
        }

        return strtoupper((string) $currency);
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Merchant Account
    |--------------------------------------------------------------------------
    */

    private function extractMerchantAccount(
        array $transaction
    ): ?string {
        $account = $transaction['bakongAccountID']
            ?? $transaction['receiverAccount']
            ?? $transaction['receiverAccountId']
            ?? $transaction['accountId']
            ?? null;

        if ($account === null) {
            return null;
        }

        return (string) $account;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Bill Number
    |--------------------------------------------------------------------------
    */

    private function extractBillNumber(
        array $transaction
    ): ?string {
        $billNumber = $transaction['billNumber']
            ?? $transaction['bill_number']
            ?? null;

        if ($billNumber === null) {
            return null;
        }

        return (string) $billNumber;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Transaction Hash
    |--------------------------------------------------------------------------
    */

    private function extractTransactionHash(
        array $transaction
    ): ?string {
        $hash = $transaction['hash']
            ?? $transaction['transactionHash']
            ?? $transaction['txHash']
            ?? null;

        if ($hash === null) {
            return null;
        }

        return (string) $hash;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract External Reference
    |--------------------------------------------------------------------------
    */

    private function extractExternalReference(
        array $transaction
    ): ?string {
        $reference = $transaction['externalReference']
            ?? $transaction['external_reference']
            ?? $transaction['externalRef']
            ?? null;

        if ($reference === null) {
            return null;
        }

        return (string) $reference;
    }
}
