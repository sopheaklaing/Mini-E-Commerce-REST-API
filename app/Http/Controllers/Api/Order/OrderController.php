<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Get user's orders.
     */
    public function index(): AnonymousResourceCollection
    {
        $orders = $this->orderService->getUserOrders(
            Auth::user()
        );

        return OrderResource::collection($orders);
    }

    /**
     * Create order from cart.
     */
    public function store(
        OrderRequest $request
    ): JsonResponse {
        try {
            $order = $this->orderService->createOrder(
                Auth::user(),
                $request->validated(
                    'shipping_address'
                )
            );

            return response()->json([
                'message' => 'Order created successfully.',

                'data' => new OrderResource($order),
            ], Response::HTTP_CREATED);

        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Get one user's order.
     */
    public function show(int $id): OrderResource
    {
        $order = $this->orderService->getUserOrder(
            Auth::user(),
            $id
        );

        return new OrderResource($order);
    }

    /**
     * Cancel user's pending order.
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder(
                Auth::user(),
                $id
            );

            return response()->json([
                'message' => 'Order cancelled successfully.',

                'data' => new OrderResource($order),
            ]);

        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
