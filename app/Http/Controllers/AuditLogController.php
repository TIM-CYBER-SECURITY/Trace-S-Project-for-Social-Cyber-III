<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemLog::with('user')->latest('id');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }
        if ($ip = $request->query('ip')) {
            $query->where('ip', $ip);
        }

        $logs = $query->paginate(25);

        return view('admin.audit', compact('logs'));
    }
}
