<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address' => $this->address,
            'scheduled_at' => $this->scheduled_at->toIso8601String(),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
