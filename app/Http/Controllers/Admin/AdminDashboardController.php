<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InformationRecord;
use App\Models\RegistrationRequest;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $me = Auth::user();
        $org = $me->organization;
        $orgId = $me->organization_id;

        $accounts = User::where('organization_id', $orgId)->whereNotNull('trace_id');
        $stats = [
            'accounts_total' => (clone $accounts)->count(),
            'accounts_active' => (clone $accounts)->where('account_status', 'ACTIVE')->count(),
            'accounts_suspended' => (clone $accounts)->where('account_status', 'SUSPENDED')->count(),
            'accounts_provisioned' => (clone $accounts)->where('account_status', 'PROVISIONED')->count(),
            'requests_pending' => RegistrationRequest::where('organization', $org?->name)->whereIn('approval_status', ['PENDING REVIEW', 'UNDER REVIEW'])->count(),
            'reviews_pending' => InformationRecord::where('organization_id', $orgId)->whereIn('status', ['SUBMITTED', 'UNDER REVIEW'])->count(),
            'info_approved' => InformationRecord::where('organization_id', $orgId)->where('status', 'APPROVED')->count(),
            'info_revision' => InformationRecord::where('organization_id', $orgId)->where('status', 'REVISION REQUIRED')->count(),
        ];

        $userIds = User::where('organization_id', $orgId)->pluck('id');
        $recent = SystemLog::whereIn('user_id', $userIds)
            ->whereNotIn('action', ['LOGOUT'])->orderByDesc('id')->limit(10)->get();

        $queue = InformationRecord::with('creator')->where('organization_id', $orgId)
            ->whereIn('status', ['SUBMITTED', 'UNDER REVIEW'])->orderBy('submitted_at')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recent', 'queue', 'org'));
    }
}
