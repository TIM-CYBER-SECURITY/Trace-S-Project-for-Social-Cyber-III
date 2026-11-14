@php
  $cfg = $cfg ?? app(\App\Services\Ai\AiManager::class)->resolvedConfig();
  $status = $status ?? ($aiStatus ?? app(\App\Services\Ai\AiManager::class)->status());
  $engMode = old('engine_mode', $cfg['engine_mode'] ?? 'HYBRID_AUTO');
  $cloudProv = old('cloud_provider', $cfg['cloud_provider'] ?? 'google_ai_studio');
  $cloudTmo = old('cloud_timeout', $cfg['cloud_timeout'] ?? 10);
  $gModel = old('google_model', $cfg['google_model'] ?? 'gemini-2.0-flash');
  $eModel = old('exa_model', $cfg['exa_model'] ?? 'exa');
  $exaSearchOn = (bool) ($cfg['exa_search_enabled'] ?? true);
  $collapsible = (bool) ($compactCollapsible ?? false);
  $autoOpen = old('engine_mode') !== null || old('cloud_provider') !== null || str_contains((string) session('success', ''), 'Hybrid Cloud AI');
  $orgCode = auth()->user()?->organization?->code;
  $orgName = auth()->user()?->organization?->name;
@endphp

@if($collapsible)
<details class="panel overflow-hidden border-[#586B47]/50" {{ $autoOpen ? 'open' : '' }}>
  <summary class="panel-header flex-wrap gap-2 cursor-pointer select-none list-none hover:bg-[var(--bg-subtle)] transition-colors">
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full {{ !empty($status['cloud_configured']) ? 'bg-[#586B47]' : 'bg-[#B47818]' }}"></span>
      <span class="font-mono text-xs font-bold uppercase tracking-wider">
        {{ $isId ? 'Konfigurasi Hybrid Cloud AI Instansi & Auto-Failover Lokal (Google AI Studio & Exa.ai)' : 'Agency Hybrid Cloud AI & Local Auto-Failover (Google AI Studio & Exa.ai)' }}
      </span>
      @if($orgCode)
        <span class="badge badge-sand">KODE GRUP: {{ $orgCode }}</span>
      @endif
    </div>
    <div class="flex items-center gap-1.5 font-mono text-[10px]">
      <span class="badge {{ $engMode === 'HYBRID_AUTO' ? 'badge-olive' : ($engMode === 'FORCE_LOCAL' ? 'badge-neutral' : 'badge-warn') }}">
        MODE: {{ $engMode }}
      </span>
      @if(!empty($cfg['has_google_key']))
        <span class="badge badge-olive">GEMINI: {{ $cfg['google_key_masked'] }}</span>
      @endif
      @if(!empty($cfg['has_exa_key']))
        <span class="badge badge-olive">EXA: {{ $cfg['exa_key_masked'] }}</span>
      @endif
      <span class="text-[var(--text-muted)] px-1"><i class="fa-solid fa-chevron-down text-[10px]"></i></span>
    </div>
  </summary>
@else
<div class="panel overflow-hidden border-[#586B47]/50">
  <div class="panel-header flex-wrap gap-2">
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 rounded-full {{ !empty($status['cloud_configured']) ? 'bg-[#586B47]' : 'bg-[#B47818]' }}"></span>
      <span class="font-mono text-xs font-bold uppercase tracking-wider">
        {{ $isId ? 'Dual-Tier Hybrid Cloud AI API & Auto-Failover ke AI Lokal (Google AI Studio & Exa.ai)' : 'Dual-Tier Hybrid Cloud AI API & Auto-Failover to Local AI' }}
      </span>
      @if($orgCode)
        <span class="badge badge-sand">GRUP: {{ $orgCode }}</span>
      @endif
    </div>
    <div class="flex items-center gap-1.5 font-mono text-[10px]">
      <span class="badge {{ $engMode === 'HYBRID_AUTO' ? 'badge-olive' : ($engMode === 'FORCE_LOCAL' ? 'badge-neutral' : 'badge-warn') }}">
        MODE: {{ $engMode }}
      </span>
      @if(!empty($cfg['has_google_key']))
        <span class="badge badge-olive">GEMINI KEY SAVED ({{ $cfg['google_key_masked'] }})</span>
      @endif
      @if(!empty($cfg['has_exa_key']))
        <span class="badge badge-olive">EXA KEY SAVED ({{ $cfg['exa_key_masked'] }})</span>
      @endif
    </div>
  </div>
@endif

  <div class="p-4 space-y-4">
    <div class="panel-subtle p-3 border-l-4 border-l-[#586B47] text-[11px] font-mono text-[var(--text-secondary)]">
      {{ $isId
        ? 'Arsitektur Ketahanan C4ISR (' . ($orgName ?? 'Instansi') . ($orgCode ? " · {$orgCode}" : '') . '): Konfigurasi Cloud AI berlaku terisolasi & bersama untuk seluruh anggota Kode Instansi ini (Operator & Administrator), sedangkan AI Lokal tetap berjalan pada perangkat masing-masing. Saat internet/listrik router terputus atau timeout terlampaui, Trace-S OTOMATIS melakukan Failover ke AI Lokal perangkat Anda.'
        : 'C4ISR Resilience Architecture (' . ($orgName ?? 'Agency') . ($orgCode ? " · {$orgCode}" : '') . '): Cloud AI keys are shared within this Agency Code group, while Local AI runs per host device with automatic failover.' }}
    </div>

    <form method="POST" action="{{ $formAction }}" class="space-y-4 text-xs">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label for="hybrid-mode" class="label-meta block mb-1">{{ $isId ? 'Mode Operasi Mesin AI' : 'AI Engine Mode' }}</label>
          <select id="hybrid-mode" name="engine_mode" class="input-mil font-mono text-xs">
            <option value="HYBRID_AUTO" {{ $engMode === 'HYBRID_AUTO' ? 'selected' : '' }}>HYBRID_AUTO (Cloud Utama -> Auto-Failover Lokal)</option>
            <option value="FORCE_LOCAL" {{ $engMode === 'FORCE_LOCAL' ? 'selected' : '' }}>FORCE_LOCAL (100% Air-Gapped / AI Lokal Saja)</option>
            <option value="FORCE_CLOUD" {{ $engMode === 'FORCE_CLOUD' ? 'selected' : '' }}>FORCE_CLOUD (Wajib Cloud API Saja)</option>
          </select>
        </div>
        <div>
          <label for="cloud-provider" class="label-meta block mb-1">{{ $isId ? 'Provider Cloud AI Prioritas' : 'Primary Cloud Provider' }}</label>
          <select id="cloud-provider" name="cloud_provider" class="input-mil font-mono text-xs">
            <option value="google_ai_studio" {{ $cloudProv === 'google_ai_studio' ? 'selected' : '' }}>Google AI Studio (Gemini API)</option>
            <option value="exa_ai" {{ $cloudProv === 'exa_ai' ? 'selected' : '' }}>Exa.ai (Neural Search & Answer API)</option>
          </select>
        </div>
        <div>
          <label for="cloud-timeout" class="label-meta block mb-1">{{ $isId ? 'Batas Failover Timeout (detik)' : 'Failover Timeout (sec)' }}</label>
          <input id="cloud-timeout" type="number" name="cloud_timeout" min="3" max="60" value="{{ $cloudTmo }}" class="input-mil font-mono text-xs">
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="panel-subtle p-3 space-y-2.5">
          <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-1.5">
            <span class="font-mono text-xs font-bold">1. GOOGLE AI STUDIO (GEMINI API)</span>
            <span class="badge {{ !empty($cfg['has_google_key']) ? 'badge-olive' : 'badge-neutral' }}">
              {{ !empty($cfg['has_google_key']) ? 'TERENKRIPSI DB' : 'BELUM DIISI' }}
            </span>
          </div>
          <div>
            <label for="google-model" class="label-meta block mb-1">Model Google AI Studio</label>
            <select id="google-model" name="google_model" class="input-mil font-mono text-xs">
              <option value="gemini-2.5-flash" {{ $gModel === 'gemini-2.5-flash' ? 'selected' : '' }}>gemini-2.5-flash</option>
              <option value="gemini-2.0-flash" {{ $gModel === 'gemini-2.0-flash' ? 'selected' : '' }}>gemini-2.0-flash (Cepat & Ekonomis)</option>
              <option value="gemini-1.5-flash" {{ $gModel === 'gemini-1.5-flash' ? 'selected' : '' }}>gemini-1.5-flash</option>
              <option value="gemini-1.5-pro" {{ $gModel === 'gemini-1.5-pro' ? 'selected' : '' }}>gemini-1.5-pro (Penalaran Tinggi)</option>
            </select>
          </div>
          <div>
            <label for="google-api-key" class="label-meta block mb-1">API Key Google AI Studio (AIza...)</label>
            <input id="google-api-key" type="password" name="google_api_key" autocomplete="new-password"
                   placeholder="{{ !empty($cfg['has_google_key']) ? 'Tersimpan terenkripsi (' . $cfg['google_key_masked'] . ') - isi untuk mengganti' : 'Masukkan API Key Google AI Studio Anda...' }}"
                   class="input-mil font-mono text-xs">
          </div>
          @if(!empty($cfg['has_google_key']))
            <label class="inline-flex items-center gap-1.5 text-[11px] font-mono text-[var(--text-muted)] cursor-pointer">
              <input type="checkbox" name="clear_google_key" value="1">
              <span>{{ $isId ? 'Hapus API Key Google AI Studio' : 'Clear Google API Key' }}</span>
            </label>
          @endif
        </div>

        <div class="panel-subtle p-3 space-y-2.5">
          <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-1.5">
            <span class="font-mono text-xs font-bold">2. EXA.AI (NEURAL SEARCH &amp; ANSWER API)</span>
            <span class="badge {{ !empty($cfg['has_exa_key']) ? 'badge-olive' : 'badge-neutral' }}">
              {{ !empty($cfg['has_exa_key']) ? 'TERENKRIPSI DB' : 'BELUM DIISI' }}
            </span>
          </div>
          <div>
            <label for="exa-model" class="label-meta block mb-1">Model / Mode Exa.ai</label>
            <select id="exa-model" name="exa_model" class="input-mil font-mono text-xs">
              <option value="exa" {{ $eModel === 'exa' ? 'selected' : '' }}>exa (Fast Grounded Answer)</option>
              <option value="exa-pro" {{ $eModel === 'exa-pro' ? 'selected' : '' }}>exa-pro (Multi-Step Research)</option>
            </select>
          </div>
          <div>
            <label for="exa-api-key" class="label-meta block mb-1">API Key Exa.ai</label>
            <input id="exa-api-key" type="password" name="exa_api_key" autocomplete="new-password"
                   placeholder="{{ !empty($cfg['has_exa_key']) ? 'Tersimpan terenkripsi (' . $cfg['exa_key_masked'] . ') - isi untuk mengganti' : 'Masukkan API Key Exa.ai Anda...' }}"
                   class="input-mil font-mono text-xs">
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <label class="inline-flex items-center gap-1.5 text-[11px] font-mono text-[var(--text-secondary)] cursor-pointer">
              <input type="checkbox" name="exa_search_enabled" value="1" {{ $exaSearchOn ? 'checked' : '' }}>
              <span>{{ $isId ? 'Gunakan Exa Neural Search di Live Scanner' : 'Use Exa Neural Search in Live Scanner' }}</span>
            </label>
            @if(!empty($cfg['has_exa_key']))
              <label class="inline-flex items-center gap-1.5 text-[11px] font-mono text-[var(--text-muted)] cursor-pointer">
                <input type="checkbox" name="clear_exa_key" value="1">
                <span>{{ $isId ? 'Hapus API Key Exa' : 'Clear Exa Key' }}</span>
              </label>
            @endif
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-[var(--border-color)]">
        <div id="cloud-test-status" class="font-mono text-[11px] text-[var(--text-muted)]">
          {{ $isId ? 'Kunci API disimpan terenkripsi AES-256 khusus untuk Kode Instansi Anda.' : 'API keys are AES-256 encrypted per Agency Code group.' }}
        </div>
        <div class="flex items-center gap-2">
          <button type="button" id="test-cloud-btn" data-test-url="{{ $testUrl }}" class="btn-secondary">
            <i class="fa-solid fa-cloud-bolt"></i>
            <span>{{ $isId ? 'Uji Cloud & Failover' : 'Test Cloud & Failover' }}</span>
          </button>
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-shield-halved"></i>
            <span>{{ $isId ? 'Simpan Konfigurasi Hybrid' : 'Save Hybrid Config' }}</span>
          </button>
        </div>
      </div>
    </form>
  </div>
@if($collapsible)
</details>
@else
</div>
@endif

@once
@push('scripts')
<script>
document.addEventListener('click', async (ev) => {
  const btn = ev.target.closest('#test-cloud-btn');
  if (!btn) return;
  const statusEl = document.getElementById('cloud-test-status');
  const testUrl = btn.getAttribute('data-test-url');
  if (!statusEl || !testUrl) return;
  statusEl.textContent = 'Menguji Cloud AI API & kesiapan standby AI Lokal...';
  try {
    const res = await fetch(testUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json'
      }
    });
    const data = await res.json();
    statusEl.textContent = data.message || (data.ok ? 'Cloud AI & Fallback Standby OK' : 'Cloud Offline - Failover Lokal Siap');
  } catch (e) {
    statusEl.textContent = 'Gagal menghubungi endpoint uji Cloud Hybrid.';
  }
});
</script>
@endpush
@endonce
