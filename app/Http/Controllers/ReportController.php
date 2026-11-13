<?php

namespace App\Http\Controllers;

use App\Models\InvestigationCase;
use App\Models\Report;
use App\Services\Reporting\ReportGeneratorService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        private ReportGeneratorService $generator,
        private AuditLogger $audit
    ) {
    }

    public function index(Request $request)
    {
        $statusFilter = $request->query('validation_status');
        $query = Report::with(['case.analyses', 'case.evidence', 'author'])->latest();

        if ($statusFilter && in_array($statusFilter, ['VALIDATED', 'PENDING REVIEW', 'REJECTED', 'RECLASSIFIED'], true)) {
            $query->whereHas('case.analyses', fn ($q) => $q->where('validation_status', $statusFilter));
        }

        $reports = $query->paginate(15);
        $cases = InvestigationCase::with(['evidence', 'analyses'])->latest()->get();

        return view('reports.index', compact('reports', 'cases', 'statusFilter'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'case_id' => 'required|exists:cases,id',
        ]);

        $case = InvestigationCase::findOrFail($validated['case_id']);
        $report = $this->generator->generate($case, Auth::user());

        return redirect()->route('reports.show', $report->id)
            ->with('success', "Intel Brief {$report->report_code} berhasil dibuat.");
    }

    public function show($id)
    {
        $report = Report::with(['case.evidence', 'author'])->findOrFail($id);

        $this->audit->log('REPORT_VIEWED', $report, [
            'report_code' => $report->report_code,
        ]);

        return view('reports.show', compact('report'));
    }

    public function exportPdf($id)
    {
        $report = Report::with(['case.evidence', 'author'])->findOrFail($id);
        return $this->generator->exportPdf($report);
    }

    public function exportSpreadsheet($id)
    {
        $report = Report::with(['case.evidence', 'author'])->findOrFail($id);
        return $this->generator->exportSpreadsheet($report);
    }
}
