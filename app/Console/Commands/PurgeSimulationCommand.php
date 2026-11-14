<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\AiServer;
use App\Models\Connector;
use App\Models\EvidenceComparison;
use App\Models\EvidenceItem;
use App\Models\InformationRecord;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\NarrativeCluster;
use App\Models\Organization;
use App\Models\Report;
use App\Models\SimilarityAnalysis;
use App\Models\Source;
use App\Models\ThreatActor;
use App\Models\ThreatAnalysis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus rekaman contoh/simulasi awal (is_simulation=true), entitas dummy, dan model/server mock
 * tanpa menyentuh akun Trace-ID operasional, RBAC, atau audit log permanen (append-only).
 */
class PurgeSimulationCommand extends Command
{
    protected $signature = 'trace:purge-simulation {--force : Jalankan tanpa konfirmasi interaktif}';
    protected $description = 'Hapus seluruh data fixture simulasi dan entitas dummy agar basis data bersih untuk operasi nyata multi-instansi.';

    public function handle(AuditLogger $audit): int
    {
        if (! $this->option('force') && ! $this->confirm('Hapus seluruh rekaman simulasi & dummy (akun Trace-ID operasional & audit log tetap utuh)?')) {
            return self::INVALID;
        }

        // Bersihkan terlebih dahulu rekaman versi untuk info simulasi (termasuk soft-deleted) agar tidak terganjal FK
        $simInfoIds = InformationRecord::withTrashed()
            ->where('title', 'like', '[SIMULASI]%')
            ->orWhere('is_simulation', true)
            ->pluck('id');
        if ($simInfoIds->isNotEmpty()) {
            DB::table('record_reviews')->whereIn('record_id', $simInfoIds)->delete();
            DB::table('record_versions')->whereIn('record_id', $simInfoIds)->delete();
        }

        $mockServerIds = AiServer::where('driver', 'mock')
            ->orWhere('status', 'SIMULASI')
            ->pluck('id');

        $counts = [
            'narratives' => Narrative::where('is_simulation', true)->delete(),
            'reports' => Report::where('is_simulation', true)->delete(),
            'threat_analyses' => ThreatAnalysis::where('is_simulation', true)->delete(),
            'comparisons' => EvidenceComparison::where('is_simulation', true)->delete(),
            'similarities' => SimilarityAnalysis::where('is_simulation', true)->delete(),
            'clusters' => NarrativeCluster::where('is_simulation', true)->delete(),
            'actors' => ThreatActor::where('is_simulation', true)->delete(),
            'evidence' => EvidenceItem::where('is_simulation', true)->delete(),
            'sources' => Source::where('is_simulation', true)->delete(),
            'cases' => InvestigationCase::where('is_simulation', true)->delete(),
            'info_sim' => InformationRecord::withTrashed()->whereIn('id', $simInfoIds)->forceDelete(),
            'ai_models_mock' => AiModel::where('is_simulation', true)->orWhereIn('server_id', $mockServerIds)->delete(),
            'ai_servers_mock' => AiServer::whereIn('id', $mockServerIds)->delete(),
        ];

        // Deduplikasi server lokal berulang dengan driver & base_url identik
        $dedupCount = 0;
        $servers = AiServer::orderByDesc('is_active')->orderByDesc('id')->get();
        $seen = [];
        foreach ($servers as $srv) {
            $sig = strtolower((string) $srv->driver) . '|' . rtrim(strtolower((string) ($srv->base_url ?? '')), '/');
            if (isset($seen[$sig])) {
                AiModel::where('server_id', $srv->id)->delete();
                $srv->delete();
                $dedupCount++;
            } else {
                $seen[$sig] = $srv->id;
            }
        }
        $counts['duplicate_ai_servers'] = $dedupCount;

        // Bersihkan akun placeholder dummy SYSTEM jika tidak direferensikan oleh audit log append-only
        $dummyUsersDeleted = 0;
        $dummyUsers = User::where('account_status', 'SYSTEM')
            ->orWhere('name', 'SIMULATION FIXTURE OWNER')
            ->get();
        foreach ($dummyUsers as $du) {
            $inAudit = DB::table('system_logs')->where('user_id', $du->id)->exists();
            $inRecords = InformationRecord::where('created_by', $du->id)->orWhere('reviewed_by', $du->id)->exists();
            $inCases = InvestigationCase::where('created_by', $du->id)->exists();
            $inVersions = DB::table('record_versions')->where('changed_by', $du->id)->exists();
            if (! $inAudit && ! $inRecords && ! $inCases && ! $inVersions) {
                DB::table('skills')->where('installed_by', $du->id)->update(['installed_by' => null]);
                $du->delete();
                $dummyUsersDeleted++;
            } else {
                $du->forceFill([
                    'name' => 'SYSTEM_INIT_ANCHOR',
                    'position' => 'System Audit Anchor',
                    'organization_id' => null,
                    'unit_id' => null,
                    'account_status' => 'REVOKED',
                    'approval_status' => 'REJECTED',
                ])->save();
            }
        }
        $counts['dummy_users'] = $dummyUsersDeleted;

        // Bersihkan organisasi dummy "Satuan Uji Simulasi (Non-Produksi)" bila sudah tidak berpenghuni
        $dummyOrgDeleted = 0;
        $dummyOrgs = Organization::where('name', 'like', '%Simulasi%')->get();
        foreach ($dummyOrgs as $do) {
            $used = User::where('organization_id', $do->id)->exists()
                || DB::table('information_records')->where('organization_id', $do->id)->exists()
                || DB::table('cases')->where('organization_id', $do->id)->exists();
            if (! $used) {
                Unit::where('organization_id', $do->id)->delete();
                $do->delete();
                $dummyOrgDeleted++;
            }
        }
        $counts['dummy_orgs'] = $dummyOrgDeleted;

        // Netralkan label klasifikasi / deskripsi konektor yang masih menyebut PUSSIBERAD atau SIMULASI
        $staleLabel = DB::table('app_settings')->where('key', 'classification_label')->value('value');
        if (is_string($staleLabel) && (stripos($staleLabel, 'PUSSIBERAD') !== false || stripos($staleLabel, 'SIMULASI') !== false)) {
            DB::table('app_settings')->where('key', 'classification_label')->delete();
        }

        Connector::where('description', 'like', '%PUSSIBERAD%')->update([
            'description' => 'Gateway pencarian web terbuka multi-instansi melalui Google Search Grounding dan Exa.ai.',
        ]);

        Cache::forget('trace_ai_status_snapshot');
        Cache::forget('trace_host_hardware_telemetry');

        $audit->log('SIMULATION_DATA_PURGED', ['type' => 'System', 'id' => 'production-hardening'], $counts);

        $this->info('Pembersihan data simulasi & entitas dummy selesai:');
        foreach ($counts as $k => $v) {
            $this->line(" - {$k}: {$v}");
        }

        return self::SUCCESS;
    }
}
