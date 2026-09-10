<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'order_number' => $this->order_number,

            'status' => $this->status,

            'subtotal' => $this->subtotal,

            'discount' => $this->discount,

            'shipping_fee' => $this->shipping_fee,

            'total' => $this->total,

            'shipping_address' => $this->shipping_address,

            'items' => $this->items->map(
                function ($item) {
                    return [
                        'id' => $item->id,

                        'product_id' => $item->product_id,

                        'product_name' => $item->product?->name,

                        'quantity' => $item->quantity,

                        'price' => $item->price,

                        'subtotal' => $item->subtotal,
                    ];
                }
            ),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
