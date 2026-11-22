<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ServiceResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(BookingStatus::class)],
            'service_id' => ['sometimes', 'integer'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $bookings = $request->user()->bookings()
            ->with('service')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['service_id'] ?? null, fn ($query, $serviceId) => $query->where('service_id', $serviceId))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('scheduled_at', $date))
            ->orderBy('scheduled_at')
            ->paginate($filters['per_page'] ?? 15);

        return BookingResource::collection($bookings);
    }

    public function preview(StoreBookingRequest $request): JsonResponse
    {
        $summary = $this->bookings->preview($request->validated());

        return response()->json(['data' => [
            ...$summary,
            'service' => new ServiceResource($summary['service']),
            'scheduled_at' => $summary['scheduled_at']->toIso8601String(),
            'payment_method' => $summary['payment_method']->value,
        ]]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->user(), $request->validated());

        return (new BookingResource($booking->load('service')))
            ->response()
            ->setStatusCode(201);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): BookingResource
    {
        Gate::authorize('update', $booking);

        $booking = $this->bookings->changeStatus($booking, BookingStatus::from($request->validated('status')));

        return new BookingResource($booking->load('service'));
    }
}
