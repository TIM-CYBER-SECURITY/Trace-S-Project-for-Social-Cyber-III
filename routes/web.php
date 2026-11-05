<?php

use App\Http\Controllers\AiManagementController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SpreadAnalysisController;
use App\Http\Middleware\AuthenticateTrace;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------
// Autentikasi Taktis (Command Center Gate)
// -------------------------------------------------------------
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/login/quick', [AuthController::class, 'quickLogin'])->name('login.quick');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/prototype', fn () => response(file_get_contents(public_path('prototype.html')))->header('Content-Type', 'text/html'))->name('prototype');

// -------------------------------------------------------------
// Area Terlindungi (Memerlukan Sesi Otentikasi)
// -------------------------------------------------------------
Route::middleware([AuthenticateTrace::class])->group(function () {

    // Root redirect ke dashboard
    Route::get('/', fn () => redirect()->route('dashboard'));

    // Modul A: Early Warning Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/threat-feed', [DashboardController::class, 'threatFeed'])->name('api.threat-feed');

    // Modul B: Credibility Scanner, Authenticity Comparator, Cross-Source
    Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::post('/scanner/scan', [ScannerController::class, 'scan'])->name('scanner.scan');
    Route::post('/scanner/compare', [ScannerController::class, 'compare'])->name('scanner.compare');
    Route::post('/scanner/cross-source', [ScannerController::class, 'verifyCrossSource'])->name('scanner.crosssource');
    Route::post('/scanner/review', [ScannerController::class, 'review'])
        ->middleware(RoleMiddleware::class . ':analis')
        ->name('scanner.review');

    // Modul C: Threat Actor Network & Spread Analysis
    Route::get('/network', [SpreadAnalysisController::class, 'index'])->name('network.index');

    // Modul D: Command Reporting & Evidence
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/{id}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{id}/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');

    // Modul E: AI Analyst Console
    Route::get('/console', [ConsoleController::class, 'index'])->name('console.index');
    Route::post('/console/message', [ConsoleController::class, 'sendMessage'])->name('console.message');

    // Modul G: Case Management
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{id}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{id}/evidence/import', [CaseController::class, 'importEvidence'])->name('cases.import-evidence');
    Route::post('/cases/{id}/evidence/fetch-url', [CaseController::class, 'fetchUrlEvidence'])->name('cases.fetch-url');

    // Modul F: Local AI Management (Khusus Administrator)
    Route::middleware([RoleMiddleware::class . ':admin'])->prefix('admin')->group(function () {
        Route::get('/ai', [AiManagementController::class, 'index'])->name('admin.ai.index');
        Route::post('/ai/test-connection', [AiManagementController::class, 'testConnection'])->name('admin.ai.test');
        Route::post('/ai/switch-model', [AiManagementController::class, 'switchModel'])->name('admin.ai.switch-model');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('admin.audit.index');
    });
});
