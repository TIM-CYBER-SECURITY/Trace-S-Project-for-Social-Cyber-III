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

    public function index()
    {
        $reports = Report::with(['case', 'author'])->latest()->paginate(15);
        $cases = InvestigationCase::latest()->get();

        return view('reports.index', compact('reports', 'cases'));
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
}
