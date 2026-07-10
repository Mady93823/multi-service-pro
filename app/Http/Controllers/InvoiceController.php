<?php

namespace App\Http\Controllers;

use App\Domain\Invoicing\BookingInvoice;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * GST tax invoice download (M09). The customer who booked and any admin —
 * the assigned provider gets earnings, not the customer's tax document.
 */
class InvoiceController extends Controller
{
    public function __invoke(Booking $booking, BookingInvoice $invoice): Response
    {
        Gate::authorize('invoice', $booking);

        abort_unless($invoice->isAvailableFor($booking), 404);

        return Pdf::loadView('invoices.booking', $invoice->data($booking))
            ->setPaper('a4')
            ->download($invoice->filename($booking));
    }
}
