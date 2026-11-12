<?php

namespace App\Http\Controllers;

use App\Models\InformationRecord;
use App\Models\SystemLog;
use App\Services\Records\RecordService;
use App\Services\Skills\SkillService;
use Illuminate\Support\Facades\Auth;

class OperatorDashboardController extends Controller
{
    public function index(RecordService $records, SkillService $skills)
    {
        $me = Auth::user();
        $mine = InformationRecord::where('created_by', $me->id);

        $counts = [
            'drafts' => (clone $mine)->where('status', 'DRAFT')->count(),
            'pending' => (clone $mine)->whereIn('status', ['SUBMITTED', 'UNDER REVIEW'])->count(),
            'revision' => (clone $mine)->where('status', 'REVISION REQUIRED')->count(),
            'approved' => (clone $mine)->where('status', 'APPROVED')->count(),
            'rejected' => (clone $mine)->where('status', 'REJECTED')->count(),
        ];
        $recent = (clone $mine)->orderByDesc('updated_at')->limit(8)->get();
        $revisions = (clone $mine)->where('status', 'REVISION REQUIRED')->orderByDesc('updated_at')->limit(5)->get();
        $latestApproved = $records->visibleTo($me)->where('status', 'APPROVED')->orderByDesc('approved_at')->limit(5)->get();
        $activity = SystemLog::where('user_id', $me->id)->whereNotIn('action', ['LOGOUT'])->orderByDesc('id')->limit(6)->get();
        $availableSkills = $skills->availableFor($me);

        return view('operator.dashboard', compact('counts', 'recent', 'revisions', 'latestApproved', 'activity', 'availableSkills'));
    }

    /** Riwayat pribadi: pengajuan, revisi, persetujuan, aktivitas. */
    public function history()
    {
        $me = Auth::user();
        $records = InformationRecord::where('created_by', $me->id)->withCount('versions')->orderByDesc('updated_at')->paginate(10, ['*'], 'records');
        $reviews = \App\Models\RecordReview::with(['reviewer'])->whereIn('record_id', InformationRecord::where('created_by', $me->id)->pluck('id'))->orderByDesc('id')->limit(15)->get();
        $activity = SystemLog::where('user_id', $me->id)->orderByDesc('id')->paginate(15, ['*'], 'activity');

        return view('operator.history', compact('records', 'reviews', 'activity'));
    }
}
