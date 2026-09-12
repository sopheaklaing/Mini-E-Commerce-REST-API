<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'order_id' => $this->order_id,

            'bill_number' => $this->bill_number,

            'amount' => $this->amount,

            'currency' => $this->currency,

            'status' => $this->status,

            'qr_code' => $this->qr_code,

            'md5' => $this->md5,

            'qr_code_url' => $this->qr_code_url,

            'expired_at' => $this->expired_at,

            'paid_at' => $this->paid_at,
        ];
    }
}
