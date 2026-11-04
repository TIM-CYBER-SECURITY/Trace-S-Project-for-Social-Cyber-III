@extends('layouts.tactical')

@section('title', $case->case_code . ' // Detail Investigasi')

@section('content')

<!-- Case Header -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
    <div class="flex items-center gap-2.5">
      <div class="px-2.5 py-1 bg-cyan-950/60 border border-cyan-500/50 text-cyan-400 font-mono font-bold text-sm rounded-sm">
        {{ $case->case_code }}
      </div>
      <div>
        <h1 class="text-sm sm:text-base font-bold font-sans text-neutral-100">{{ $case->title }}</h1>
        <div class="flex items-center gap-2 text-[10px] font-mono text-neutral-400 mt-0.5">
          <span>Kategori: <b class="text-neutral-300">{{ $case->category }}</b></span>
          <span>•</span>
          <span>Dibuat: <b class="text-neutral-300">{{ $case->created_at->format('d M Y H:i') }} WIB</b></span>
          <span>•</span>
          <span>Operator: <b class="text-neutral-300">{{ $case->creator?->name ?? 'Sistem' }}</b></span>
        </div>
      </div>
    </div>

    <!-- Badges & Action -->
    <div class="flex items-center gap-2">
      @if($case->priority === 'KRITIS')
        <span class="px-2 py-0.5 bg-rose-950/50 border border-rose-600/50 text-rose-400 text-[10px] font-mono uppercase font-bold">PRIORITAS KRITIS</span>
      @elseif($case->priority === 'TINGGI')
        <span class="px-2 py-0.5 bg-amber-950/50 border border-amber-500/50 text-amber-400 text-[10px] font-mono uppercase font-bold">PRIORITAS TINGGI</span>
      @else
        <span class="px-2 py-0.5 bg-cyan-950/50 border border-cyan-500/50 text-cyan-400 text-[10px] font-mono uppercase">{{ $case->priority }}</span>
      @endif

      @if($case->is_simulation)
        <span class="px-2 py-0.5 border border-amber-500/40 text-amber-500 bg-amber-950/30 text-[10px] font-mono">SIMULASI</span>
      @endif

      <form action="{{ route('reports.generate') }}" method="POST">
        @csrf
        <input type="hidden" name="case_id" value="{{ $case->id }}">
        <button type="submit" class="px-3 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/50 hover:bg-emerald-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-1.5 shadow-[0_0_10px_rgba(16,185,129,0.2)]">
          <i class="fa-solid fa-file-pdf"></i>
          <span>Terbitkan Intel Brief</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Inti Klaim yang Diperiksa -->
  <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-sm">
    <div class="text-[10px] uppercase font-mono tracking-widest text-cyan-400 mb-1 flex items-center gap-1.5">
      <i class="fa-solid fa-quote-left"></i>
      <span>Inti Klaim yang Diperiksa</span>
    </div>
    <div class="text-sm font-sans text-neutral-200 leading-relaxed font-medium">
      "{{ $case->claim_text }}"
    </div>
    @if($case->description)
    <div class="text-xs text-neutral-400 font-sans mt-2 pt-2 border-t border-neutral-850">
      {{ $case->description }}
    </div>
    @endif
  </div>
</div>

<!-- START: 12-STEP OPERATIONAL PIPELINE STEPPER (Supplemental §4) -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
    <div class="text-xs font-mono uppercase tracking-wider text-neutral-300 font-semibold flex items-center gap-2">
      <i class="fa-solid fa-route text-cyan-400"></i>
      <span>Alur Operasional Intelijen Siber Sosial (12 Tahap Terpadu)</span>
    </div>
    <span class="text-[10px] font-mono text-neutral-500">Pipeline Integritas Bukti</span>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 text-xs font-mono">
    @foreach($steps as $idx => $st)
    <div class="p-2.5 rounded-sm border transition-colors {{ $st['done'] ? 'bg-cyan-950/20 border-cyan-500/40 text-neutral-200' : 'bg-neutral-950/40 border-neutral-800 text-neutral-500' }}">
      <div class="flex items-center justify-between text-[9px] mb-1">
        <span class="font-bold {{ $st['done'] ? 'text-cyan-400' : 'text-neutral-600' }}">TAHAP {{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</span>
        @if($st['done'])
          <i class="fa-solid fa-circle-check text-emerald-400"></i>
        @else
          <i class="fa-regular fa-circle text-neutral-600"></i>
        @endif
      </div>
      <div class="font-semibold text-[11px] leading-tight mb-1 {{ $st['done'] ? 'text-neutral-200' : 'text-neutral-500' }}">
        {{ $st['name'] }}
      </div>
      <div class="text-[9px] text-neutral-500 leading-tight">
        {{ $st['desc'] }}
      </div>
    </div>
    @endforeach
  </div>
</div>
<!-- END: 12-STEP OPERATIONAL PIPELINE STEPPER -->

<!-- START: QUICK ACTION HUB -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'scan']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:border-cyan-500/60 rounded-sm flex items-center gap-3 transition-colors group">
    <div class="w-9 h-9 rounded-sm bg-cyan-950/60 border border-cyan-500/40 flex items-center justify-center text-cyan-400 group-hover:scale-105 transition-transform">
      <i class="fa-solid fa-shield-halved"></i>
    </div>
    <div>
      <div class="text-xs font-bold font-mono text-neutral-200 group-hover:text-cyan-300">Credibility Scan</div>
      <div class="text-[10px] text-neutral-500 font-mono">Skoring linguistik &amp; ekstraksi klaim</div>
    </div>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'compare']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:border-cyan-500/60 rounded-sm flex items-center gap-3 transition-colors group">
    <div class="w-9 h-9 rounded-sm bg-cyan-950/60 border border-cyan-500/40 flex items-center justify-center text-cyan-400 group-hover:scale-105 transition-transform">
      <i class="fa-solid fa-scale-balanced"></i>
    </div>
    <div>
      <div class="text-xs font-bold font-mono text-neutral-200 group-hover:text-cyan-300">Authenticity Comparator</div>
      <div class="text-[10px] text-neutral-500 font-mono">Diff teks, angka, &amp; dampak makna</div>
    </div>
  </a>

  <a href="{{ route('scanner.index', ['case_id' => $case->id, 'tab' => 'crosssource']) }}" class="p-3 bg-neutral-900 border border-neutral-800 hover:border-cyan-500/60 rounded-sm flex items-center gap-3 transition-colors group">
    <div class="w-9 h-9 rounded-sm bg-cyan-950/60 border border-cyan-500/40 flex items-center justify-center text-cyan-400 group-hover:scale-105 transition-transform">
      <i class="fa-solid fa-code-compare"></i>
    </div>
    <div>
      <div class="text-xs font-bold font-mono text-neutral-200 group-hover:text-cyan-300">Cross-Source Verification</div>
      <div class="text-[10px] text-neutral-500 font-mono">5 jenis kemiripan &amp; independensi sumber</div>
    </div>
  </a>
</div>
<!-- END: QUICK ACTION HUB -->

<!-- START: EVIDENCE REPOSITORY (DIGITAL LOCKER) -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-3">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-3">
    <div>
      <h2 class="text-sm font-semibold font-mono uppercase tracking-wide text-neutral-200 flex items-center gap-2">
        <i class="fa-solid fa-box-archive text-cyan-400"></i>
        <span>Repositori Bukti Digital (Evidence Items)</span>
      </h2>
      <p class="text-[11px] text-neutral-500 mt-0.5">Seluruh bukti disimpan dengan hash SHA-256 dan catatan metode akuisisi resmi.</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2">
      <button onclick="document.getElementById('fetch-url-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-mono uppercase rounded-sm border border-neutral-700 flex items-center gap-1.5">
        <i class="fa-solid fa-globe text-cyan-400"></i>
        <span>Ambil via URL</span>
      </button>
      <button onclick="document.getElementById('import-evidence-modal').classList.remove('hidden')" class="px-3 py-1.5 bg-cyan-600/20 text-cyan-400 hover:bg-cyan-600/40 text-xs font-mono uppercase rounded-sm border border-cyan-500/50 flex items-center gap-1.5">
        <i class="fa-solid fa-file-import"></i>
        <span>Impor Manual</span>
      </button>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
          <th class="py-2.5 px-3">Kode Bukti</th>
          <th class="py-2.5 px-3">Peran (Role)</th>
          <th class="py-2.5 px-3">Judul &amp; Teks Ekstraksi</th>
          <th class="py-2.5 px-3">Penerbit / Platform</th>
          <th class="py-2.5 px-3">Status Akses</th>
          <th class="py-2.5 px-3">Hash SHA-256</th>
          <th class="py-2.5 px-3 text-right">Rincian</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($case->evidence as $e)
        <tr class="hover:bg-neutral-850/50 transition-colors">
          <td class="py-3 px-3 font-semibold text-cyan-400 whitespace-nowrap">
            {{ $e->evidence_code }}
            @if($e->is_simulation)
            <span class="block text-[8px] text-amber-500 font-normal">SIMULASI</span>
            @endif
          </td>
          <td class="py-3 px-3 whitespace-nowrap">
            @if($e->role === 'PRIMARY')
              <span class="px-2 py-0.5 bg-emerald-950/60 border border-emerald-500/50 text-emerald-400 text-[9px] uppercase font-bold">SUMBER PRIMER</span>
            @elseif($e->role === 'REFERENCE')
              <span class="px-2 py-0.5 bg-cyan-950/60 border border-cyan-500/50 text-cyan-400 text-[9px] uppercase">REFERENSI</span>
            @elseif($e->role === 'COMPARED')
              <span class="px-2 py-0.5 bg-rose-950/60 border border-rose-600/50 text-rose-400 text-[9px] uppercase font-bold">PEMBANDING</span>
            @else
              <span class="px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-400 text-[9px] uppercase">{{ $e->role }}</span>
            @endif
          </td>
          <td class="py-3 px-3 max-w-sm">
            <div class="font-sans text-neutral-200 font-medium truncate">{{ $e->title }}</div>
            <div class="text-[11px] text-neutral-400 font-sans line-clamp-2 mt-0.5">{{ $e->extracted_text }}</div>
          </td>
          <td class="py-3 px-3 whitespace-nowrap">
            <div class="text-neutral-300 font-medium truncate max-w-xs">{{ $e->publisher ?? '-' }}</div>
            <div class="text-[10px] text-neutral-500 uppercase">{{ $e->platform }}</div>
          </td>
          <td class="py-3 px-3 whitespace-nowrap">
            @if($e->access_status === 'OK')
              <span class="text-emerald-400 flex items-center gap-1"><i class="fa-solid fa-check text-[10px]"></i> BERHASIL</span>
            @elseif($e->access_status === 'GAGAL')
              <span class="text-rose-400 flex items-center gap-1"><i class="fa-solid fa-xmark text-[10px]"></i> GAGAL</span>
            @else
              <span class="text-neutral-500">{{ $e->access_status }}</span>
            @endif
          </td>
          <td class="py-3 px-3 font-mono text-[10px] text-neutral-400 whitespace-nowrap">
            @if($e->content_hash)
              <span title="{{ $e->content_hash }}">{{ substr($e->content_hash, 0, 10) }}...{{ substr($e->content_hash, -8) }}</span>
            @else
              <span class="text-neutral-600">N/A</span>
            @endif
          </td>
          <td class="py-3 px-3 text-right whitespace-nowrap">
            <button onclick="showEvidenceDetail({{ json_encode($e) }})" class="text-cyan-400 hover:text-cyan-300 text-xs px-2 py-1 bg-cyan-950/30 rounded-sm">
              <i class="fa-solid fa-eye"></i> Tinjau
            </button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-6 text-center text-neutral-500">Belum ada berkas bukti yang terkumpul untuk kasus ini.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<!-- END: EVIDENCE REPOSITORY -->

<!-- Modal Impor Bukti Manual -->
<div id="import-evidence-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-xl p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-file-import text-cyan-400"></i>
        <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-neutral-200">Impor Bukti Digital Manual</h2>
      </div>
      <button onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.import-evidence', $case->id) }}" method="POST" enctype="multipart/form-data" class="space-y-3 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400">Judul Bukti</label>
        <input type="text" name="title" required placeholder="mis. Salinan Siaran Pers / Screenshot Unggahan"
               class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Peran Bukti (Role)</label>
          <select name="role" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="REFERENCE">REFERENCE (Artikel / Rujukan Berita)</option>
            <option value="PRIMARY">PRIMARY (Dokumen / Regulasi / Akun Resmi)</option>
            <option value="COMPARED">COMPARED (Materi yang Diduga Berubah)</option>
            <option value="POST">POST (Unggahan Sosial Media Viral)</option>
            <option value="OTHER">OTHER (Lain-lain)</option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Jenis Berkas (Type)</label>
          <select name="type" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="TEXT">Teks Langsung</option>
            <option value="DOCUMENT">Dokumen (PDF / Word)</option>
            <option value="SCREENSHOT">Tangkapan Layar (Screenshot)</option>
            <option value="IMAGE">Gambar Infografis</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Penerbit / Akun Sumber</label>
          <input type="text" name="publisher" placeholder="mis. Kementerian / @akun_sumber"
                 class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
        </div>

        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Platform</label>
          <select name="platform" class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="INSTAGRAM">Instagram</option>
            <option value="TIKTOK">TikTok</option>
            <option value="YOUTUBE">YouTube</option>
            <option value="X">X (Twitter)</option>
            <option value="FACEBOOK">Facebook</option>
            <option value="NEWS">Portal Berita</option>
            <option value="GOV_PORTAL">Situs Pemerintah Resmi</option>
            <option value="WEB">Web Publik</option>
          </select>
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400">Isi Teks Ekstraksi (Pertahankan Teks Asli)</label>
        <textarea name="extracted_text" rows="4" placeholder="Ketik atau tempel isi teks persis seperti pada bukti..."
                  class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500"></textarea>
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400">Lampiran Berkas (Opsional, Maks 5MB)</label>
        <input type="file" name="evidence_file" class="w-full text-xs text-neutral-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border-0 file:text-xs file:font-mono file:bg-neutral-800 file:text-cyan-400 hover:file:bg-neutral-700">
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('import-evidence-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 uppercase rounded-sm">
          Batal
        </button>
        <button type="submit" class="px-4 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 uppercase rounded-sm">
          Simpan Bukti
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Fetch via URL -->
<div id="fetch-url-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-globe text-cyan-400"></i>
        <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-neutral-200">Pengambilan Bukti dari URL Web</h2>
      </div>
      <button onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.fetch-url', $case->id) }}" method="POST" class="space-y-3 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400">Alamat URL Sah yang Dapat Diakses</label>
        <input type="url" name="url" required placeholder="https://portal-berita.co.id/artikel/..."
               class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Nama Penerbit / Media (Opsional)</label>
          <input type="text" name="publisher" placeholder="mis. Antara News"
                 class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
        </div>

        <div class="space-y-1">
          <label class="text-[10px] uppercase text-neutral-400">Peran Bukti</label>
          <select name="role" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="REFERENCE" selected>REFERENCE (Materi Rujukan)</option>
            <option value="PRIMARY">PRIMARY (Sumber Primer Institusi)</option>
            <option value="COMPARED">COMPARED (Pembanding / Berubah)</option>
          </select>
        </div>
      </div>

      <div class="text-[10px] text-neutral-500 leading-relaxed">
        * Sistem akan mengunduh konten teks secara aman, menghitung hash SHA-256, dan menerapkan filter anti prompt-injection. Sistem tidak menerobos CAPTCHA atau autentikasi privat.
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('fetch-url-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 uppercase rounded-sm">
          Batal
        </button>
        <button type="submit" class="px-4 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 uppercase rounded-sm">
          Ambil Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Rincian Bukti (Dynamic Vanilla JS) -->
<div id="evidence-detail-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-2xl p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <span id="modal-ev-code" class="px-2 py-0.5 bg-cyan-950 border border-cyan-500/50 text-cyan-400 font-mono text-xs font-bold"></span>
        <h2 id="modal-ev-title" class="text-sm font-bold font-sans text-neutral-200"></h2>
      </div>
      <button onclick="document.getElementById('evidence-detail-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="space-y-3 text-xs font-mono">
      <div class="grid grid-cols-2 gap-3 bg-neutral-950 p-3 border border-neutral-850 rounded-sm">
        <div>Penerbit: <b id="modal-ev-publisher" class="text-neutral-200"></b></div>
        <div>Platform: <b id="modal-ev-platform" class="text-neutral-200"></b></div>
        <div>Metode: <b id="modal-ev-method" class="text-cyan-400"></b></div>
        <div>Status Akses: <b id="modal-ev-status" class="text-emerald-400"></b></div>
      </div>

      <div>
        <div class="text-[10px] uppercase text-neutral-500 mb-1">Hash Konten (SHA-256):</div>
        <div id="modal-ev-hash" class="p-2 bg-neutral-950 border border-neutral-850 text-neutral-300 select-all break-all text-[11px]"></div>
      </div>

      <div>
        <div class="text-[10px] uppercase text-neutral-500 mb-1">Teks Ekstraksi Asli (Verbatim):</div>
        <div id="modal-ev-text" class="p-3 bg-neutral-950 border border-neutral-850 text-neutral-300 font-sans text-xs leading-relaxed max-h-48 overflow-y-auto whitespace-pre-wrap"></div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  function showEvidenceDetail(evidence) {
    document.getElementById('modal-ev-code').textContent = evidence.evidence_code;
    document.getElementById('modal-ev-title').textContent = evidence.title;
    document.getElementById('modal-ev-publisher').textContent = evidence.publisher || '-';
    document.getElementById('modal-ev-platform').textContent = evidence.platform || '-';
    document.getElementById('modal-ev-method').textContent = evidence.collection_method || '-';
    document.getElementById('modal-ev-status').textContent = evidence.access_status || '-';
    document.getElementById('modal-ev-hash').textContent = evidence.content_hash || 'TIDAK TERSEDIA';
    document.getElementById('modal-ev-text').textContent = evidence.extracted_text || '(Tidak ada teks ekstraksi)';
    document.getElementById('evidence-detail-modal').classList.remove('hidden');
  }
</script>
@endpush
