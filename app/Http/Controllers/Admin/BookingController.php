<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('admin.bookings.index', [
            'bookings' => Booking::with(['employee.department', 'desk.zone'])
                ->when($status, fn ($query, $value) => $query->where('booking_status', $value))
                ->orderByDesc('booking_date')
                ->orderByDesc('start_time')
                ->paginate(20)
                ->withQueryString(),
            'statuses' => Booking::statusOptions(),
            'status' => $status,
        ]);
    }

    /**
     * ผู้ดูแลระบบยกเลิกการจองแทนพนักงานได้
     */
    public function destroy(Booking $booking): RedirectResponse
    {
        if (! $booking->isCancelable()) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ เนื่องจากเช็คอินแล้วหรือใบจองถูกปิดใช้งาน');
        }

        $booking->forceFill(['booking_status' => Booking::STATUS_EXPIRED])->save();

        return back()->with('success', "ยกเลิกการจอง {$booking->booking_id} เรียบร้อยแล้ว");
    }
}
