@extends('layouts.tactical')

@section('title', 'Command Reporting & Evidence // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-4">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Command Reporting &amp; Evidence Dossier</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL D</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Arsip laporan intelijen siber sosial formal, dossier bukti digital, dan ringkasan eksekutif komando.</p>
  </div>

  <div>
    <button onclick="document.getElementById('new-report-modal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600/20 text-emerald-400 border border-emerald-500/50 hover:bg-emerald-600/40 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2 shadow-[0_0_12px_rgba(16,185,129,0.2)]">
      <i class="fa-solid fa-file-circle-plus"></i>
      <span>Terbitkan Brief Baru</span>
    </button>
  </div>
</div>

<!-- Reports Table -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-4">
  <div class="flex items-center justify-between">
    <div class="text-xs font-mono uppercase tracking-wider text-neutral-400">
      Total Laporan Terbit: <b class="text-cyan-400">{{ $reports->total() }}</b>
    </div>
    <span class="text-[10px] font-mono text-neutral-500">FORMAT: PUSSIBERAD 16-SECTION INTEL BRIEF</span>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
          <th class="py-2.5 px-3">Kode Laporan</th>
          <th class="py-2.5 px-3">Kasus Terhubung</th>
          <th class="py-2.5 px-3">Judul Laporan Intelijen</th>
          <th class="py-2.5 px-3">Penyusun</th>
          <th class="py-2.5 px-3">Tanggal Terbit</th>
          <th class="py-2.5 px-3">Klasifikasi</th>
          <th class="py-2.5 px-3 text-right">Berkas PDF</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($reports as $rep)
        <tr class="hover:bg-neutral-850/50 transition-colors">
          <td class="py-3 px-3 font-semibold text-cyan-400">
            <a href="{{ route('reports.show', $rep->id) }}" class="hover:underline">{{ $rep->report_code }}</a>
            @if($rep->is_simulation)
            <span class="block text-[8px] text-amber-500 font-normal">SIMULASI</span>
            @endif
          </td>
          <td class="py-3 px-3 text-neutral-300">
            <a href="{{ route('cases.show', $rep->case_id) }}" class="text-cyan-400 hover:underline">
              {{ $rep->case?->case_code ?? '-' }}
            </a>
          </td>
          <td class="py-3 px-3 max-w-md">
            <a href="{{ route('reports.show', $rep->id) }}" class="font-sans text-neutral-200 hover:text-cyan-300 font-medium block truncate">
              {{ $rep->title }}
            </a>
          </td>
          <td class="py-3 px-3 text-[11px] text-neutral-400">{{ $rep->author?->name ?? 'Analis Sistem' }}</td>
          <td class="py-3 px-3 text-[11px] text-neutral-400">{{ $rep->created_at->format('d M Y H:i') }} WIB</td>
          <td class="py-3 px-3">
            <span class="px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 text-[9px] uppercase">
              {{ $rep->classification_label }}
            </span>
          </td>
          <td class="py-3 px-3 text-right space-x-2">
            <a href="{{ route('reports.show', $rep->id) }}" class="px-2 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-[11px] rounded-sm transition-colors">
              <i class="fa-solid fa-eye mr-1"></i> Tinjau
            </a>
            <a href="{{ route('reports.pdf', $rep->id) }}" class="px-2.5 py-1 bg-emerald-950/60 border border-emerald-500/50 hover:bg-emerald-900/60 text-emerald-400 text-[11px] rounded-sm transition-colors">
              <i class="fa-solid fa-download mr-1"></i> Unduh PDF
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-8 text-center text-neutral-500">Belum ada laporan intel brief yang diterbitkan.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pt-2">
    {{ $reports->links() }}
  </div>
</div>

<!-- Modal Terbitkan Laporan Baru -->
<div id="new-report-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-lg p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-file-circle-plus text-emerald-400"></i>
        <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-neutral-200">Terbitkan Intelligence Brief Baru</h2>
      </div>
      <button onclick="document.getElementById('new-report-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('reports.generate') }}" method="POST" class="space-y-4 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase text-neutral-400">Pilih Kasus Investigasi yang Telah Ditinjau:</label>
        <select name="case_id" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
          @foreach($cases as $cs)
            <option value="{{ $cs->id }}">
              {{ $cs->case_code }} - {{ Str::limit($cs->title, 45) }} ({{ $cs->priority }})
            </option>
          @endforeach
        </select>
      </div>

      <div class="p-3 bg-neutral-950 border border-neutral-850 rounded-sm text-[10px] text-neutral-500 space-y-1.5 leading-relaxed">
        <div class="text-neutral-300 font-bold uppercase">Struktur Dokumen Intel Brief:</div>
        <p>Sistem akan secara otomatis menyusun laporan 16 Bagian Resmi mencakup: Identitas Kasus, Ringkasan Eksekutif, Lingkup Data, Klaim Utama, Temuan Cross-Source, Authenticity Diff, SNA Timeline, Keterbatasan, Rekomendasi Tindak Lanjut, dan Lampiran Hash SHA-256 Bukti.</p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
        <button type="button" onclick="document.getElementById('new-report-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 uppercase rounded-sm">
          Batal
        </button>
        <button type="submit" class="px-4 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/50 hover:bg-emerald-600/40 uppercase rounded-sm">
          Generate Intel Brief
        </button>
      </div>
    </form>
  </div>
</div>

@endsection
