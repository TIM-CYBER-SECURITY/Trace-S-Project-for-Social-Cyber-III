<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\AiManagementController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperatorDashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SkillRunController;
use App\Http\Controllers\SpreadAnalysisController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------
// Publik Terbatas: Kesehatan Sistem, Bahasa & Autentikasi Trace-ID
// -------------------------------------------------------------
Route::get('/health', HealthController::class)->name('health');

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'id'], true)) {
        session(['locale' => $locale]);
    }
    return back();
})->name('lang.switch');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// -------------------------------------------------------------
// Area Operasional Terlindungi (Trace-ID Authentication + RBAC Permissions)
// -------------------------------------------------------------
Route::middleware(['auth.trace'])->group(function () {
    // Penggantian kata sandi (wajib pada login awal atau reset)
    Route::get('/password/change', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/change', [AuthController::class, 'updatePassword'])->name('password.update');

    // Pintu masuk utama: arahkan ke Dashboard Administrator atau Operator
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    // Workspace & Riwayat Operator
    Route::prefix('operator')->name('operator.')->group(function () {
        Route::get('/dashboard', [OperatorDashboardController::class, 'index'])
            ->middleware('permission:dashboard.view')
            ->name('dashboard');
        Route::get('/history', [OperatorDashboardController::class, 'history'])
            ->middleware('permission:history.view_own')
            ->name('history');
    });

    // Pengelolaan Informasi & Alur Persetujuan
    Route::prefix('records')->name('records.')->group(function () {
        Route::get('/', [InformationController::class, 'index'])
            ->middleware('permission:info.view')
            ->name('index');
        Route::get('/create', [InformationController::class, 'create'])
            ->middleware('permission:info.submit')
            ->name('create');
        Route::post('/', [InformationController::class, 'store'])
            ->middleware('permission:info.submit')
            ->name('store');
        Route::get('/{code}', [InformationController::class, 'show'])
            ->middleware('permission:info.view')
            ->name('show');
        Route::get('/{code}/edit', [InformationController::class, 'edit'])
            ->middleware('permission:info.update')
            ->name('edit');
        Route::put('/{code}', [InformationController::class, 'update'])
            ->middleware('permission:info.update')
            ->name('update');
        Route::post('/{code}/submit', [InformationController::class, 'submit'])
            ->middleware('permission:info.submit')
            ->name('submit');
        Route::post('/{code}/review', [InformationController::class, 'review'])
            ->middleware('permission:info.review,info.approve')
            ->name('review');
        Route::post('/{code}/archive', [InformationController::class, 'archive'])
            ->middleware('permission:info.delete')
            ->name('archive');
        Route::delete('/{code}', [InformationController::class, 'destroy'])
            ->middleware('permission:info.delete')
            ->name('destroy');
    });

    // Eksekusi Skills (Teks-Murni Terkontrol)
    Route::prefix('skills')->name('skills.')->middleware('permission:skills.use')->group(function () {
        Route::get('/', [SkillRunController::class, 'index'])->name('index');
        Route::get('/executions/{id}', [SkillRunController::class, 'execution'])->name('execution');
        Route::get('/{key}', [SkillRunController::class, 'show'])->name('show');
        Route::post('/{key}/execute', [SkillRunController::class, 'execute'])->name('execute');
    });

    // Notifikasi Pengguna
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'read'])->name('read');
    });

    // Modul Intelijen Siber Sosial Trace-S (A1–G) dengan Granular Permission
    Route::get('/early-warning', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('early-warning');
    Route::post('/early-warning/narratives', [DashboardController::class, 'storeNarrative'])
        ->middleware('permission:case.write')
        ->name('early-warning.narratives.store');
    Route::post('/early-warning/sync-rss', [DashboardController::class, 'syncRss'])
        ->middleware('permission:connector.use')
        ->name('early-warning.sync-rss');
    Route::post('/early-warning/rss-feeds', [DashboardController::class, 'storeRssFeed'])
        ->middleware('permission:connector.manage')
        ->name('early-warning.rss-feeds.store');
    Route::get('/api/threat-feed', [DashboardController::class, 'threatFeed'])
        ->middleware('permission:dashboard.view')
        ->name('api.threat-feed');
    Route::get('/sentiment', [DashboardController::class, 'sentiment'])
        ->middleware('permission:dashboard.view')
        ->name('sentiment.index');

    Route::get('/scanner', [ScannerController::class, 'index'])
        ->middleware('permission:analysis.run')
        ->name('scanner.index');
    Route::post('/scanner/scan', [ScannerController::class, 'scan'])
        ->middleware('permission:analysis.run')
        ->name('scanner.scan');
    Route::post('/scanner/compare', [ScannerController::class, 'compare'])
        ->middleware('permission:analysis.run')
        ->name('scanner.compare');
    Route::post('/scanner/cross-source', [ScannerController::class, 'verifyCrossSource'])
        ->middleware('permission:analysis.run')
        ->name('scanner.crosssource');
    Route::post('/scanner/review', [ScannerController::class, 'review'])
        ->middleware('permission:analysis.review')
        ->name('scanner.review');

    Route::get('/network', [SpreadAnalysisController::class, 'index'])
        ->middleware('permission:spread.view')
        ->name('network.index');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])
        ->middleware('permission:report.generate')
        ->name('reports.generate');
    Route::get('/reports/{id}', [ReportController::class, 'show'])
        ->middleware('permission:report.view')
        ->name('reports.show');
    Route::get('/reports/{id}/pdf', [ReportController::class, 'exportPdf'])
        ->middleware('permission:report.export')
        ->name('reports.pdf');

    Route::get('/console', [ConsoleController::class, 'index'])
        ->middleware('permission:console.use')
        ->name('console.index');
    Route::post('/console/message', [ConsoleController::class, 'sendMessage'])
        ->middleware('permission:console.use')
        ->name('console.message');

    Route::get('/cases', [CaseController::class, 'index'])
        ->middleware('permission:case.view')
        ->name('cases.index');
    Route::post('/cases', [CaseController::class, 'store'])
        ->middleware('permission:case.write')
        ->name('cases.store');
    Route::get('/cases/{id}', [CaseController::class, 'show'])
        ->middleware('permission:case.view')
        ->name('cases.show');
    Route::post('/cases/{id}/evidence/import', [CaseController::class, 'importEvidence'])
        ->middleware('permission:evidence.import')
        ->name('cases.import-evidence');
    Route::post('/cases/{id}/evidence/fetch-url', [CaseController::class, 'fetchUrlEvidence'])
        ->middleware('permission:evidence.import')
        ->name('cases.fetch-url');

    // Area Tata Kelola Administrator
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->middleware('permission:system.health')
            ->name('dashboard');

        Route::get('/accounts', [AccountController::class, 'index'])
            ->middleware('permission:accounts.manage')
            ->name('accounts.index');
        Route::get('/accounts/{traceId}', [AccountController::class, 'show'])
            ->middleware('permission:accounts.manage')
            ->name('accounts.show');
        Route::post('/accounts/{traceId}', [AccountController::class, 'act'])
            ->middleware('permission:accounts.manage')
            ->name('accounts.act');

        Route::get('/skills', [SkillController::class, 'index'])
            ->middleware('permission:skills.configure')
            ->name('skills.index');
        Route::get('/skills/{key}', [SkillController::class, 'show'])
            ->middleware('permission:skills.configure')
            ->name('skills.show');
        Route::post('/skills/{key}', [SkillController::class, 'act'])
            ->middleware('permission:skills.configure')
            ->name('skills.act');

        Route::get('/system', [SystemController::class, 'index'])
            ->middleware('permission:system.health')
            ->name('system.index');
        Route::post('/system', [SystemController::class, 'update'])
            ->middleware('permission:system.configure')
            ->name('system.update');
        Route::post('/system/purge-simulation', [SystemController::class, 'purgeSimulation'])
            ->middleware('permission:system.configure')
            ->name('system.purge-simulation');

        Route::get('/ai', [AiManagementController::class, 'index'])
            ->middleware('permission:ai.manage')
            ->name('ai.index');
        Route::post('/ai/configure', [AiManagementController::class, 'configure'])
            ->middleware('permission:ai.manage')
            ->name('ai.configure');
        Route::post('/ai/sync-models', [AiManagementController::class, 'syncModels'])
            ->middleware('permission:ai.manage')
            ->name('ai.sync');
        Route::post('/ai/detect-local', [AiManagementController::class, 'detectLocal'])
            ->middleware('permission:ai.manage')
            ->name('ai.detect');
        Route::post('/ai/test-connection', [AiManagementController::class, 'testConnection'])
            ->middleware('permission:ai.manage')
            ->name('ai.test');
        Route::post('/ai/switch-model', [AiManagementController::class, 'switchModel'])
            ->middleware('permission:ai.manage')
            ->name('ai.switch-model');

        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('audit.index');
    });
});
