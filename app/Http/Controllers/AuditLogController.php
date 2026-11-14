<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $me = \Illuminate\Support\Facades\Auth::user();
        $query = SystemLog::with('user')->latest('id');

        if ($me?->organization_id) {
            $orgUserIds = \App\Models\User::where('organization_id', $me->organization_id)->pluck('id');
            $query->where(function ($q) use ($orgUserIds) {
                $q->whereIn('user_id', $orgUserIds)->orWhereNull('user_id');
            });
        }

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }
        if ($ip = $request->query('ip')) {
            $query->where('ip', $ip);
        }
        if ($result = $request->query('result')) {
            $query->where('result', $result);
        }
        if ($actor = $request->query('actor')) {
            $query->where('actor_label', 'like', "%{$actor}%");
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.audit', compact('logs'));
    }
}
