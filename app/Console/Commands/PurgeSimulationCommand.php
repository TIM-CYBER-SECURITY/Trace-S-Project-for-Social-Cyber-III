<?php

namespace App\Console\Commands;

use App\Models\EvidenceComparison;
use App\Models\EvidenceItem;
use App\Models\InformationRecord;
use App\Models\InvestigationCase;
use App\Models\Narrative;
use App\Models\NarrativeCluster;
use App\Models\Report;
use App\Models\SimilarityAnalysis;
use App\Models\Source;
use App\Models\ThreatActor;
use App\Models\ThreatAnalysis;
use App\Services\Support\AuditLogger;
use Illuminate\Console\Command;

/**
 * Menghapus rekaman contoh/simulasi awal (is_simulation=true) tanpa menyentuh akun Trace-ID aktif,
 * RBAC, Skills, atau audit log permanen.
 */
class PurgeSimulationCommand extends Command
{
    protected $signature = 'trace:purge-simulation {--force : Jalankan tanpa konfirmasi interaktif}';
    protected $description = 'Hapus seluruh data fixture simulasi awal agar basis data bersih untuk operasi nyata.';

    public function handle(AuditLogger $audit): int
    {
        if (!$this->option('force') && !$this->confirm('Hapus seluruh rekaman bertanda is_simulation=true (akun Trace-ID & audit log tetap utuh)?')) {
            return self::INVALID;
        }

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
            'info_sim' => InformationRecord::where('title', 'like', '[SIMULASI]%')->delete(),
        ];

        $audit->log('SIMULATION_DATA_PURGED', ['type' => 'System', 'id' => 'simulation-fixtures'], $counts);

        $this->info('Pembersihan data simulasi selesai:');
        foreach ($counts as $k => $v) {
            $this->line(" - {$k}: {$v} rekaman dihapus");
        }

        return self::SUCCESS;
    }
}
