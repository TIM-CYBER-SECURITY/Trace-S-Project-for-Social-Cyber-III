@extends('layouts.tactical')

@section('title', 'Manajemen Kasus Investigasi // TRACE-S')

@section('content')

<!-- Header Bar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800 pb-4">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-lg font-bold font-mono tracking-wide text-neutral-100 uppercase">Investigasi Kasus Siber Sosial</h1>
      <span class="text-[10px] font-mono px-2 py-0.5 border border-cyan-500/40 bg-cyan-950/30 text-cyan-400 rounded-sm">MODUL G</span>
    </div>
    <p class="text-xs text-neutral-500 mt-0.5">Pengelolaan berkas perkara investigasi, repositori bukti digital terotentikasi, dan audit trail.</p>
  </div>

  <div>
    <button onclick="document.getElementById('new-case-modal').classList.remove('hidden')" class="px-4 py-2 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 hover:text-cyan-300 text-xs font-mono uppercase tracking-wider rounded-sm transition-all flex items-center gap-2 shadow-[0_0_12px_rgba(6,182,212,0.2)]">
      <i class="fa-solid fa-folder-plus"></i>
      <span>Buat Kasus Baru</span>
    </button>
  </div>
</div>

<!-- Cases Table -->
<div class="bg-neutral-900 border border-neutral-800 rounded-sm p-4 space-y-4">
  <div class="flex items-center justify-between">
    <div class="text-xs font-mono uppercase tracking-wider text-neutral-400">
      Total Kasus Terdaftar: <b class="text-cyan-400">{{ $cases->total() }}</b>
    </div>
    <div class="text-[11px] font-mono text-neutral-500">
      Urutan: Kasus Terbaru
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left font-mono text-xs">
      <thead>
        <tr class="border-b border-neutral-800 text-[10px] uppercase tracking-wider text-neutral-500 bg-neutral-950/40">
          <th class="py-2.5 px-3">Case ID</th>
          <th class="py-2.5 px-3">Judul &amp; Inti Klaim</th>
          <th class="py-2.5 px-3">Kategori</th>
          <th class="py-2.5 px-3">Prioritas</th>
          <th class="py-2.5 px-3">Bukti</th>
          <th class="py-2.5 px-3">Status</th>
          <th class="py-2.5 px-3 text-right">Tindakan</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-800/60">
        @forelse($cases as $c)
        <tr class="hover:bg-neutral-850/50 transition-colors">
          <td class="py-3 px-3 font-semibold text-cyan-400">
            <a href="{{ route('cases.show', $c->id) }}" class="hover:underline">{{ $c->case_code }}</a>
            @if($c->is_simulation)
            <span class="block text-[8px] font-normal text-amber-500">SIMULASI</span>
            @endif
          </td>
          <td class="py-3 px-3 max-w-md">
            <a href="{{ route('cases.show', $c->id) }}" class="font-sans text-neutral-200 hover:text-cyan-300 font-medium block truncate">
              {{ $c->title }}
            </a>
            <div class="text-[11px] text-neutral-400 line-clamp-1 italic mt-0.5">"{{ $c->claim_text }}"</div>
          </td>
          <td class="py-3 px-3 text-[11px] text-neutral-400">{{ $c->category }}</td>
          <td class="py-3 px-3">
            @if($c->priority === 'KRITIS')
              <span class="px-2 py-0.5 bg-rose-950/50 border border-rose-600/50 text-rose-400 text-[9px] uppercase font-semibold">KRITIS</span>
            @elseif($c->priority === 'TINGGI')
              <span class="px-2 py-0.5 bg-amber-950/50 border border-amber-500/50 text-amber-400 text-[9px] uppercase font-semibold">TINGGI</span>
            @else
              <span class="px-2 py-0.5 bg-cyan-950/50 border border-cyan-500/50 text-cyan-400 text-[9px] uppercase">{{ $c->priority }}</span>
            @endif
          </td>
          <td class="py-3 px-3 text-neutral-300">
            <span class="font-bold text-neutral-200">{{ $c->evidence_count }}</span> berkas
          </td>
          <td class="py-3 px-3">
            <span class="px-2 py-0.5 bg-neutral-800 border border-neutral-700 text-neutral-300 text-[9px] uppercase">
              {{ $c->status }}
            </span>
          </td>
          <td class="py-3 px-3 text-right space-x-2">
            <a href="{{ route('cases.show', $c->id) }}" class="px-2.5 py-1 bg-cyan-950/40 border border-cyan-500/40 hover:bg-cyan-900/40 text-cyan-400 text-[11px] rounded-sm transition-colors">
              Buka Berkas
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="py-8 text-center text-neutral-500">Belum ada kasus yang didaftarkan. Klik tombol "Buat Kasus Baru" untuk memulai.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pt-2">
    {{ $cases->links() }}
  </div>
</div>

<!-- Modal Buat Kasus Baru -->
<div id="new-case-modal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-neutral-900 border border-neutral-800 rounded-sm w-full max-w-xl p-5 shadow-2xl space-y-4">
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2">
        <i class="fa-solid fa-folder-plus text-cyan-400"></i>
        <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-neutral-200">Registrasi Kasus Investigasi Baru</h2>
      </div>
      <button onclick="document.getElementById('new-case-modal').classList.add('hidden')" class="text-neutral-500 hover:text-neutral-300">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form action="{{ route('cases.store') }}" method="POST" class="space-y-3.5 text-xs font-mono">
      @csrf
      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-wider text-neutral-400">Judul Kasus / Operasi</label>
        <input type="text" name="title" required placeholder="mis. Investigasi Disinformasi Alokasi Fiskal APBN 2026"
               class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600">
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-wider text-neutral-400">Narasi / Klaim Utama yang Diperiksa</label>
        <textarea name="claim_text" rows="3" required placeholder="Tuliskan pernyataan atau klaim verbatim yang menjadi objek penyelidikan..."
                  class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-wider text-neutral-400">Kategori Kasus</label>
          <select name="category" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="DISINFORMASI STRATEGIS / KOGNITIF">Disinformasi Strategis / Kognitif</option>
            <option value="MANIPULASI KONTEN DIGITAL">Manipulasi Konten Digital (Editan)</option>
            <option value="PROPAGANDA & POLARISASI">Propaganda &amp; Polarisasi Sosial</option>
            <option value="MISINFORMASI VIRAL">Misinformasi Viral</option>
          </select>
        </div>

        <div class="space-y-1">
          <label class="text-[10px] uppercase tracking-wider text-neutral-400">Tingkat Prioritas Awal</label>
          <select name="priority" required class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500">
            <option value="SEDANG">SEDANG (Standar)</option>
            <option value="TINGGI" selected>TINGGI (Eskalasi Cepat)</option>
            <option value="KRITIS">KRITIS (Ancaman Stabilitas)</option>
            <option value="RENDAH">RENDAH (Pemantauan Pasif)</option>
          </select>
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-[10px] uppercase tracking-wider text-neutral-400">Catatan Awal Operator</label>
        <textarea name="operator_notes" rows="2" placeholder="Informasi awal metode temuan, platform penyebar, dsb..."
                  class="w-full bg-neutral-950 border border-neutral-700 text-neutral-200 px-3 py-2 text-xs font-mono rounded-sm focus:outline-none focus:border-cyan-500 placeholder-neutral-600"></textarea>
      </div>

      <div class="text-[10px] text-neutral-500 border-t border-neutral-800 pt-3">
        * Kasus akan mendapatkan Case ID otomatis (mis. <span class="text-cyan-400">TS-{{ date('Y') }}-XXX</span>) dan tercatat dalam audit log.
      </div>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('new-case-modal').classList.add('hidden')" class="px-3 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 uppercase tracking-wider rounded-sm">
          Batal
        </button>
        <button type="submit" class="px-4 py-1.5 bg-cyan-600/20 text-cyan-400 border border-cyan-500/50 hover:bg-cyan-600/40 uppercase tracking-wider rounded-sm">
          Simpan &amp; Lanjutkan
        </button>
      </div>
    </form>
  </div>
</div>

@endsection
