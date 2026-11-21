<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(BookingStatus $from, BookingStatus $to)
    {
        parent::__construct("A {$from->value} booking cannot be changed to {$to->value}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
