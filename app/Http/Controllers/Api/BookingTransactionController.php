<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingTransactionRequest;
use App\Http\Requests\ViewBookingRequest;
use App\Http\Resources\Api\BookingTransactionResource;
use App\Http\Resources\Api\ViewBookingResource;
use App\Models\BookingTransaction;
use App\Models\OfficeSpace;
use Illuminate\Http\Request;

class BookingTransactionController extends Controller
{

    public function booking_details(ViewBookingRequest $request)
    {
        $validatedData = $request->validated();

        $booking = BookingTransaction::where('phone_number', $validatedData['phone_number'])
            ->where('booking_trx_id', $validatedData['booking_trx_id'])
            ->with(['officeSpace', 'officeSpace.city'])
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }
        return new ViewBookingResource($booking);
    }

    public function store(StoreBookingTransactionRequest $request)
    {
        $validatedData = $request->validated();

        $officeSpace = OfficeSpace::find($validatedData['office_space_id']);

        $validatedData['is_paid'] = false;
        $validatedData['booking_trx_id'] = BookingTransaction::generateUniqueTrxId();
        $validatedData['duration'] = $officeSpace->duration;
        $validatedData['ended_at'] = (new \DateTime($validatedData['started_at']))->modify("+{$officeSpace->duration} days")->format('Y-m-d');

        $bookingTransaction = BookingTransaction::create($validatedData);

        $sid = getenv('TWILIO_ACCOUNT_SID');
        $token = getenv('TWILIO_AUTH_TOKEN');
        $twilio = new \Twilio\Rest\Client($sid, $token);

        $messageBody = "Hi {$bookingTransaction->name}, your booking for {$bookingTransaction->officeSpace->name} has been successfully created. Your booking ID is {$bookingTransaction->booking_trx_id}. Please keep this ID for future reference.";

        $twilio->messages->create(
            "+{$bookingTransaction->phone_number}",
            [
                'from' => getenv('TWILIO_PHONE_NUMBER'),
                'body' => $messageBody
            ]
        );
        $bookingTransaction->load('officeSpace');

        return new BookingTransactionResource($bookingTransaction);
    }
}
