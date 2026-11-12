<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = AppNotification::where('recipient_id', Auth::id())->orderByDesc('id');
        if ($t = $request->query('type')) {
            $q->where('notification_type', $t);
        }
        if ($request->query('unread')) {
            $q->whereNull('read_at');
        }
        $items = $q->paginate(20)->withQueryString();
        $types = AppNotification::where('recipient_id', Auth::id())->distinct()->pluck('notification_type');

        return view('notifications.index', compact('items', 'types'));
    }

    public function read(int $id)
    {
        // Hanya pemilik notifikasi yang dapat menandainya.
        $n = AppNotification::where('recipient_id', Auth::id())->findOrFail($id);
        $n->update(['read_at' => $n->read_at ?? now()]);
        return $n->link ? redirect($n->link) : back();
    }

    public function readAll()
    {
        AppNotification::where('recipient_id', Auth::id())->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'Semua notifikasi ditandai telah dibaca.');
    }
}
