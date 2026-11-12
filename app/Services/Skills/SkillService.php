<?php

namespace App\Services\Skills;

use App\Models\Skill;
use App\Models\SkillExecution;
use App\Models\User;
use App\Services\Ai\ToolRegistry;
use App\Services\Support\AuditLogger;
use App\Services\Support\Notifier;
use InvalidArgumentException;

/**
 * Skills = modul kapabilitas terkontrol di atas Tool Allowlist (ToolRegistry).
 * - Hanya manifest dari katalog tepercaya (skills/catalog) yang dapat dipasang.
 * - Tidak ada eksekusi kode dari sumber tak tepercaya, shell, SQL mentah, atau jaringan.
 * - Hanya tool berbasis teks murni (tanpa akses data kasus) yang diizinkan sebagai Skill.
 */
class SkillService
{
    public const TEXT_ONLY_TOOLS = ['extract_claims', 'calculate_text_similarity', 'compare_claims', 'compare_numeric_entities'];

    public function __construct(private ToolRegistry $tools, private AuditLogger $audit, private Notifier $notifier)
    {
    }

    /** @return array<string,array> key => manifest valid (+ _errors untuk yang tidak valid) */
    public function catalog(): array
    {
        $out = [];
        foreach (glob(base_path('skills/catalog/*.json')) ?: [] as $file) {
            $m = json_decode((string) file_get_contents($file), true);
            if (! is_array($m) || empty($m['skill_key'])) {
                continue;
            }
            $m['_errors'] = $this->validateManifest($m);
            $out[$m['skill_key']] = $m;
        }
        ksort($out);
        return $out;
    }

    public function validateManifest(array $m): array
    {
        $e = [];
        foreach (['skill_key', 'name', 'version', 'tool', 'input_schema'] as $k) {
            if (empty($m[$k])) {
                $e[] = "Manifest tidak memuat {$k}.";
            }
        }
        if (! empty($m['skill_key']) && ! preg_match('/^[a-z0-9-]{3,60}$/', $m['skill_key'])) {
            $e[] = 'skill_key tidak valid.';
        }
        if (! empty($m['tool'])) {
            if (! $this->tools->isAllowed($m['tool'])) {
                $e[] = "Tool '{$m['tool']}' tidak ada di allowlist.";
            } elseif (! in_array($m['tool'], self::TEXT_ONLY_TOOLS, true)) {
                $e[] = "Tool '{$m['tool']}' mengakses data kasus dan tidak diizinkan sebagai Skill.";
            }
        }
        if (! empty($m['version']) && ! preg_match('/^\d+\.\d+\.\d+$/', $m['version'])) {
            $e[] = 'Versi harus semver (x.y.z).';
        }
        $req = $m['requires']['trace_s'] ?? null;
        if ($req && ! $this->satisfies(config('trace.version', '2.0.0'), $req)) {
            $e[] = "Tidak kompatibel: membutuhkan Trace-S {$req}.";
        }
        return $e;
    }

    private function satisfies(string $current, string $constraint): bool
    {
        if (preg_match('/^(>=|>|<=|<|=)?\s*(\d+\.\d+\.\d+)$/', trim($constraint), $mm)) {
            return version_compare($current, $mm[2], $mm[1] ?: '=');
        }
        return false;
    }

    public function install(User $admin, string $key): Skill
    {
        $m = $this->catalog()[$key] ?? null;
        if (! $m) {
            throw new InvalidArgumentException('Skill tidak ada di katalog tepercaya.');
        }
        if ($m['_errors']) {
            throw new InvalidArgumentException('Manifest tidak valid: ' . implode(' ', $m['_errors']));
        }

        $skill = Skill::firstOrNew(['skill_key' => $key]);
        $isUpdate = $skill->exists && $skill->installation_status === 'INSTALLED';
        $skill->fill([
            'name' => $m['name'], 'description' => $m['description'] ?? null, 'version' => $m['version'],
            'tool' => $m['tool'], 'input_schema' => $m['input_schema'],
            'installation_status' => 'INSTALLED', 'installed_by' => $admin->id,
        ]);
        if (! $skill->exists) {
            $skill->status = 'DISABLED';
            $skill->configuration = ['max_input_chars' => 5000];
        }
        $skill->save();

        $this->audit->log($isUpdate ? 'SKILL_UPDATED' : 'SKILL_INSTALLED', $skill, ['key' => $key, 'version' => $m['version']], $admin->id);
        return $skill;
    }

    public function remove(User $admin, Skill $skill): void
    {
        $skill->update(['installation_status' => 'REMOVED', 'status' => 'DISABLED']);
        $this->audit->log('SKILL_REMOVED', $skill, ['key' => $skill->skill_key], $admin->id);
    }

    public function setStatus(User $admin, Skill $skill, bool $enabled): void
    {
        if ($skill->installation_status !== 'INSTALLED') {
            throw new InvalidArgumentException('Skill tidak terpasang.');
        }
        $skill->update(['status' => $enabled ? 'ENABLED' : 'DISABLED']);
        $this->audit->log($enabled ? 'SKILL_ENABLED' : 'SKILL_DISABLED', $skill, ['key' => $skill->skill_key], $admin->id);
        $this->notifier->notify(
            $skill->users()->wherePivot('enabled', true)->where('account_status', 'ACTIVE')->get(),
            'SKILL', 'Ketersediaan Skill berubah', "{$skill->name} kini " . ($enabled ? 'tersedia' : 'dinonaktifkan') . '.'
        );
    }

    public function configure(User $admin, Skill $skill, array $config): void
    {
        $max = max(100, min(5000, (int) ($config['max_input_chars'] ?? 5000)));
        $skill->update(['configuration' => ['max_input_chars' => $max]]);
        $this->audit->log('SKILL_CONFIGURED', $skill, ['key' => $skill->skill_key, 'max_input_chars' => $max], $admin->id);
    }

    public function assign(User $admin, Skill $skill, User $user, bool $enabled): void
    {
        if ($user->organization_id !== $admin->organization_id) {
            throw new InvalidArgumentException('Pengguna di luar organisasi Anda.');
        }
        $skill->users()->syncWithoutDetaching([$user->id => ['enabled' => $enabled, 'assigned_by' => $admin->id, 'assigned_at' => now()]]);
        $this->audit->log('SKILL_ASSIGNED', $skill, ['key' => $skill->skill_key, 'to' => $user->trace_id, 'enabled' => $enabled], $admin->id);
        $this->notifier->notify($user, 'SKILL', 'Akses Skill diperbarui', "{$skill->name}: " . ($enabled ? 'diaktifkan untuk Anda.' : 'dicabut dari akun Anda.'));
    }

    /** Skills yang boleh dijalankan pengguna ini. */
    public function availableFor(User $user)
    {
        return Skill::where('installation_status', 'INSTALLED')->where('status', 'ENABLED')->orderBy('name')->get()
            ->filter(fn (Skill $s) => $this->canUse($user, $s))->values();
    }

    public function canUse(User $user, Skill $skill): bool
    {
        if (! $user->hasPermission('skills.use') || ! $skill->isUsable()) {
            return false;
        }
        if ($user->hasPermission('skills.configure')) {
            return true; // Administrator
        }
        return $skill->users()->where('users.id', $user->id)->wherePivot('enabled', true)->exists();
    }

    public function execute(User $user, Skill $skill, array $input): SkillExecution
    {
        if (! $this->canUse($user, $skill)) {
            $this->audit->log('SKILL_EXECUTE_DENIED', $skill, ['key' => $skill->skill_key], $user->id, 'DENIED');
            throw new InvalidArgumentException('Anda tidak berwenang menjalankan Skill ini.');
        }

        $max = (int) ($skill->configuration['max_input_chars'] ?? 5000);
        $params = [];
        foreach ($skill->input_schema as $f) {
            $val = trim((string) ($input[$f['name']] ?? ''));
            if (($f['required'] ?? false) && $val === '') {
                throw new InvalidArgumentException("Kolom {$f['label']} wajib diisi.");
            }
            if (mb_strlen($val) > min($max, (int) ($f['max'] ?? $max))) {
                throw new InvalidArgumentException("Kolom {$f['label']} melebihi batas karakter.");
            }
            $params[$f['name']] = $val;
        }
        $manifest = $this->catalog()[$skill->skill_key] ?? [];
        $params += $manifest['fixed_params'] ?? [];

        $t0 = microtime(true);
        try {
            $result = $this->tools->execute($skill->tool, $params);
            $status = 'SUCCESS';
            $error = null;
        } catch (\Throwable $e) {
            $result = null;
            $status = 'FAILED';
            $error = mb_substr($e->getMessage(), 0, 300);
        }

        $exec = SkillExecution::create([
            'skill_id' => $skill->id, 'user_id' => $user->id, 'status' => $status,
            'input' => array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 500) : $v, $params),
            'result' => $result, 'error' => $error,
            'duration_ms' => (int) round((microtime(true) - $t0) * 1000), 'created_at' => now(),
        ]);
        $this->audit->log('SKILL_EXECUTED', $skill, ['key' => $skill->skill_key, 'execution' => $exec->id], $user->id, $status === 'SUCCESS' ? 'SUCCESS' : 'FAILURE');

        return $exec;
    }
}
