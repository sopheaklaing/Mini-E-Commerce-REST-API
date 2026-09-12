<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Successful Payment
    |--------------------------------------------------------------------------
    */

    public function test_user_can_verify_successful_payment(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'pending',
                'subtotal' => 10,
                'discount' => 0,
                'shipping_fee' => 0,
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'test-md5-success',
                'bill_number' => 'ORDER-'.$order->id.'-123',
                'expired_at' => now()->addMinutes(15),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        $gateway
            ->shouldReceive('checkMd5')
            ->once()
            ->with('test-md5-success')
            ->andReturn([
                'responseCode' => 0,
                'responseMessage' => 'Success',
                'data' => [
                    'hash' => 'transaction-hash-123',
                    'fromAccountId' => 'user@bkrt',
                    'toAccountId' => config(
                        'services.bakong.merchant_account_id'
                    ),
                    'currency' => 'USD',
                    'amount' => 10,
                    'billNumber' => $payment->bill_number,
                    'externalRef' => 'external-ref-123',
                ],
            ]);

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'paid',
                'message' => 'Payment verified successfully.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'transaction_hash' => 'transaction-hash-123',
            'external_ref' => 'external-ref-123',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Already Paid
    |--------------------------------------------------------------------------
    */

    public function test_already_paid_payment_returns_paid(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'paid',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_hash' => 'already-paid-hash',
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        /*
         * Bakong should NOT be called because payment is already paid.
         */
        $gateway
            ->shouldReceive('checkMd5')
            ->never();

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'paid',
                'message' => 'Payment has already been completed.',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Expired Payment
    |--------------------------------------------------------------------------
    */

    public function test_expired_payment_cannot_be_verified(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'pending',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'expired-md5',
                'expired_at' => now()->subMinute(),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        /*
         * Bakong should NOT be called because the QR is already expired.
         */
        $gateway
            ->shouldReceive('checkMd5')
            ->never();

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => 'expired',
                'message' => 'Payment QR has expired.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'expired',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Not Found
    |--------------------------------------------------------------------------
    */

    public function test_payment_remains_pending_when_transaction_is_not_found(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'pending',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'transaction-not-found-md5',
                'expired_at' => now()->addMinutes(15),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        $gateway
            ->shouldReceive('checkMd5')
            ->once()
            ->with('transaction-not-found-md5')
            ->andReturn([
                'responseCode' => 1,
                'errorCode' => 1,
                'responseMessage' => 'Transaction could not be found. Please try again.',
            ]);

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        /*
         * IMPORTANT:
         *
         * Pending verification is a normal state.
         * The controller now returns HTTP 200.
         */
        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Amount Mismatch
    |--------------------------------------------------------------------------
    */

    public function test_payment_fails_when_amount_does_not_match(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'pending',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'amount-mismatch-md5',
                'expired_at' => now()->addMinutes(15),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        $gateway
            ->shouldReceive('checkMd5')
            ->once()
            ->with('amount-mismatch-md5')
            ->andReturn([
                'responseCode' => 0,
                'responseMessage' => 'Success',
                'data' => [
                    'hash' => 'amount-mismatch-hash',
                    'fromAccountId' => 'user@bkrt',
                    'toAccountId' => config(
                        'services.bakong.merchant_account_id'
                    ),
                    'currency' => 'USD',
                    'amount' => 5,
                    'billNumber' => $payment->bill_number,
                    'externalRef' => 'amount-mismatch-ref',
                ],
            ]);

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => 'verification_error',
                'message' => 'Payment amount does not match the order amount.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Currency Mismatch
    |--------------------------------------------------------------------------
    */

    public function test_payment_fails_when_currency_does_not_match(): void
    {
        $order = Order::factory()
            ->for($this->user)
            ->create([
                'status' => 'pending',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'currency-mismatch-md5',
                'expired_at' => now()->addMinutes(15),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        $gateway
            ->shouldReceive('checkMd5')
            ->once()
            ->with('currency-mismatch-md5')
            ->andReturn([
                'responseCode' => 0,
                'responseMessage' => 'Success',
                'data' => [
                    'hash' => 'currency-mismatch-hash',
                    'fromAccountId' => 'user@bkrt',
                    'toAccountId' => config(
                        'services.bakong.merchant_account_id'
                    ),
                    'currency' => 'KHR',
                    'amount' => 10,
                    'billNumber' => $payment->bill_number,
                    'externalRef' => 'currency-mismatch-ref',
                ],
            ]);

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'status' => 'verification_error',
                'message' => 'Payment currency does not match the order currency.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public function test_user_cannot_access_another_users_payment(): void
    {
        $anotherUser = User::factory()->create();

        $order = Order::factory()
            ->for($anotherUser)
            ->create([
                'status' => 'pending',
                'total' => 10,
            ]);

        $payment = Payment::factory()
            ->for($order)
            ->create([
                'amount' => 10,
                'currency' => 'USD',
                'status' => 'pending',
                'md5' => 'private-payment-md5',
                'expired_at' => now()->addMinutes(15),
            ]);

        $gateway = Mockery::mock(PaymentGatewayService::class);

        /*
         * Bakong must never be called for an unauthorized request.
         */
        $gateway
            ->shouldReceive('checkMd5')
            ->never();

        $this->app->instance(
            PaymentGatewayService::class,
            $gateway
        );

        $response = $this->actingAs(
            $this->user,
            'api'
        )->getJson(
            "/api/payments/{$payment->id}/status"
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }
}
