@php
  $isId = app()->getLocale() === 'id';
  $grpMe = Auth::user();
  $grpOrg = $grpMe?->organization;
  $grpOrgId = $grpMe?->organization_id;
  $grpUsers = $grpOrgId
      ? \App\Models\User::with(['roleRef', 'unit'])
          ->where('organization_id', $grpOrgId)
          ->whereNotNull('trace_id')
          ->orderByDesc('last_login_at')
          ->orderBy('trace_id')
          ->get()
      : collect();
  $grpUserIds = $grpUsers->pluck('id');
  $grpLogs = ($grpOrgId && $grpUserIds->isNotEmpty())
      ? \App\Models\SystemLog::with('user')
          ->whereIn('user_id', $grpUserIds)
          ->whereIn('action', [
              'LOGIN_SUCCESS', 'LOGOUT', 'PASSWORD_CHANGED',
              'RECORD_CREATED', 'RECORD_REVISED', 'RECORD_SUBMITTED',
              'RECORD_UNDER_REVIEW', 'RECORD_APPROVED', 'RECORD_REJECTED', 'RECORD_REVISION_REQUIRED',
              'CASE_CREATED', 'CREDIBILITY_SCAN_RUN', 'NARRATIVE_LOGGED', 'CLOUD_AI_CONFIGURED',
          ])
          ->latest('id')
          ->limit(15)
          ->get()
      : collect();
  $grpCloudCfg = app(\App\Services\Ai\AiManager::class)->resolvedConfig($grpOrgId);
@endphp

<div class="panel overflow-hidden trace-animate-in">
  <div class="panel-header flex-wrap gap-2">
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-[#586B47] trace-live-dot"></span>
      <span class="font-mono text-xs font-bold uppercase tracking-wider">
        {{ $isId ? 'Grup Kode Instansi, Roster Akun & Log Real-Time' : 'Agency Code Group, Roster & Real-Time Activity Log' }}
      </span>
    </div>
    <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px]">
      <span class="badge badge-olive">{{ $grpOrg?->name ?? 'UNASSIGNED' }}</span>
      <span class="badge badge-sand">{{ $isId ? 'KODE' : 'CODE' }}: {{ $grpOrg?->code ?? '-' }}</span>
      <span class="badge badge-neutral">AI: {{ $grpCloudCfg['engine_mode'] ?? 'HYBRID_AUTO' }}</span>
    </div>
  </div>

  <div class="p-4 space-y-4">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 font-mono text-xs">
      <div class="panel-subtle p-3">
        <div class="label-meta">{{ $isId ? 'INSTANSI' : 'AGENCY' }}</div>
        <div class="font-bold mt-1 truncate" title="{{ $grpOrg?->name }}">{{ $grpOrg?->name ?? '-' }}</div>
      </div>
      <div class="panel-subtle p-3">
        <div class="label-meta">{{ $isId ? 'KODE INSTANSI' : 'AGENCY CODE' }}</div>
        <div class="font-bold mt-1 text-[#586B47] dark:text-[#C5B89C]">{{ $grpOrg?->code ?? '-' }}</div>
      </div>
      <div class="panel-subtle p-3">
        <div class="label-meta">{{ $isId ? 'ANGGOTA AKTIF / TOTAL' : 'ACTIVE / TOTAL ACCOUNTS' }}</div>
        <div class="font-bold mt-1">{{ $grpUsers->where('account_status', 'ACTIVE')->count() }} / {{ $grpUsers->count() }}</div>
      </div>
      <div class="panel-subtle p-3">
        <div class="label-meta">{{ $isId ? 'MODE CLOUD AI GRUP' : 'GROUP CLOUD AI MODE' }}</div>
        <div class="font-bold mt-1">{{ $grpCloudCfg['engine_mode'] ?? 'HYBRID_AUTO' }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <!-- Daftar Anggota Satu Kode Instansi -->
      <div class="border border-[var(--border-color)] overflow-hidden">
        <div class="px-3 py-2 bg-[var(--bg-subtle)] border-b border-[var(--border-color)] flex items-center justify-between font-mono text-[11px] font-bold uppercase">
          <span>{{ $isId ? 'Anggota Kode Instansi' : 'Agency Code Members' }} ({{ $grpUsers->count() }})</span>
          <span class="text-[var(--text-muted)]">{{ $grpOrg?->code ?? '-' }}</span>
        </div>
        <div class="overflow-x-auto max-h-72 overflow-y-auto">
          <table class="table-mil">
            <thead>
              <tr>
                <th>TRACE-ID</th>
                <th>{{ $isId ? 'NAMA & POSISI' : 'NAME & ROLE' }}</th>
                <th>STATUS</th>
                <th>{{ $isId ? 'KODE DEVICE' : 'DEVICE' }}</th>
                <th>{{ $isId ? 'LOGIN TERAKHIR' : 'LAST LOGIN' }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($grpUsers as $gu)
                <tr>
                  <td class="font-mono text-[11px] font-bold whitespace-nowrap">{{ $gu->trace_id }}</td>
                  <td class="text-xs">
                    <div class="font-semibold">{{ $gu->name }}</div>
                    <div class="font-mono text-[10px] text-[var(--text-muted)]">{{ $gu->roleLabel() }}{{ $gu->position ? ' · ' . $gu->position : '' }}</div>
                  </td>
                  <td>
                    <span class="badge {{ $gu->account_status === 'ACTIVE' ? 'badge-olive' : ($gu->account_status === 'SUSPENDED' ? 'badge-warn' : 'badge-neutral') }}">
                      {{ $gu->account_status }}
                    </span>
                  </td>
                  <td class="font-mono text-[11px] text-[var(--text-muted)] whitespace-nowrap">{{ $gu->last_device_code ?? '-' }}</td>
                  <td class="font-mono text-[11px] text-[var(--text-muted)] whitespace-nowrap">{{ $gu->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Log Aktivitas Real-Time Grup Instansi -->
      <div class="border border-[var(--border-color)] overflow-hidden">
        <div class="px-3 py-2 bg-[var(--bg-subtle)] border-b border-[var(--border-color)] flex items-center justify-between font-mono text-[11px] font-bold uppercase">
          <span>{{ $isId ? 'Log Login, Submisi & Analisis Real-Time' : 'Real-Time Login, Submission & Scan Log' }}</span>
          <span class="badge badge-olive">LIVE</span>
        </div>
        <div class="overflow-x-auto max-h-72 overflow-y-auto">
          <table class="table-mil">
            <thead>
              <tr>
                <th>{{ $isId ? 'WAKTU' : 'TIME' }}</th>
                <th>AKTOR</th>
                <th>AKSI</th>
                <th>TARGET</th>
                <th>DEVICE</th>
              </tr>
            </thead>
            <tbody>
              @forelse($grpLogs as $gl)
                <tr>
                  <td class="font-mono text-[10px] text-[var(--text-muted)] whitespace-nowrap">{{ $gl->created_at?->format('m-d H:i:s') }}</td>
                  <td class="font-mono text-[11px] font-semibold whitespace-nowrap">{{ $gl->user?->trace_id ?? ($gl->actor_label ?? 'SYSTEM') }}</td>
                  <td><span class="badge {{ str_contains($gl->action, 'LOGIN') ? 'badge-olive' : 'badge-sand' }}">{{ $gl->action }}</span></td>
                  <td class="font-mono text-[10px] text-[var(--text-muted)] whitespace-nowrap">{{ $gl->target_type }}:{{ $gl->target_id }}</td>
                  <td class="font-mono text-[10px] text-[var(--text-muted)] whitespace-nowrap">{{ $gl->metadata['device_code'] ?? ($gl->user?->last_device_code ?? '-') }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
