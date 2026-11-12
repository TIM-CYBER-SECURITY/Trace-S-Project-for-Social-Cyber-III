@php
  $isId = app()->getLocale() === 'id';
@endphp

@if(session('issued_credential'))
  @php $cred = session('issued_credential'); @endphp
  <div class="mb-4 p-4 rounded border border-[#586B47] bg-[#586B47]/10 text-[var(--text-primary)] space-y-2" role="alert">
    <div class="flex items-center justify-between gap-2">
      <div class="font-mono text-xs font-bold uppercase tracking-wider text-[#586B47] dark:text-[#C5B89C] flex items-center gap-2">
        <i class="fa-solid fa-key"></i>
        <span>{{ $isId ? 'KREDENSIAL AWAL / SEMENTARA (TAMPIL SATU KALI)' : 'INITIAL / TEMPORARY CREDENTIAL (SHOWN ONCE)' }}</span>
      </div>
      <span class="badge badge-warn">{{ $isId ? 'CATAT SEGERA' : 'RECORD NOW' }}</span>
    </div>
    <p class="text-xs text-[var(--text-secondary)]">
      {{ $isId
        ? 'Kata sandi sementara ini tidak disimpan dalam bentuk teks terang dan tidak akan ditampilkan ulang. Serahkan kepada pemilik akun melalui kanal resmi yang aman.'
        : 'This temporary password is not stored in plaintext and will never be shown again. Deliver it via a secure official channel.' }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-mono text-xs pt-1">
      <div class="p-2.5 rounded border border-[var(--border-color)] bg-[var(--bg-surface)]">
        <div class="text-[10px] text-[var(--text-muted)] uppercase">TRACE-ID</div>
        <div class="font-bold select-all mt-0.5">{{ $cred['trace_id'] ?? '-' }}</div>
      </div>
      <div class="p-2.5 rounded border border-[var(--border-color)] bg-[var(--bg-surface)]">
        <div class="text-[10px] text-[var(--text-muted)] uppercase">{{ $isId ? 'KATA SANDI SEMENTARA' : 'TEMPORARY PASSWORD' }}</div>
        <div class="font-bold select-all mt-0.5">{{ $cred['password'] ?? '-' }}</div>
      </div>
    </div>
  </div>
@endif

@if(session('success'))
  <div class="mb-3 px-3.5 py-2.5 rounded border border-[#586B47]/60 bg-[#586B47]/12 text-[#3E4D31] dark:text-[#D5E2C8] text-xs font-mono flex items-center justify-between gap-3" role="status">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-circle-check"></i>
      <span>{{ session('success') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Close" class="opacity-70 hover:opacity-100">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
@endif

@if(session('info'))
  <div class="mb-3 px-3.5 py-2.5 rounded border border-[#46514C]/50 bg-[#46514C]/10 text-[var(--text-primary)] text-xs font-mono flex items-center justify-between gap-3" role="status">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-circle-info text-[#586B47]"></i>
      <span>{{ session('info') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Close" class="opacity-70 hover:opacity-100">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
@endif

@if(session('warning'))
  <div class="mb-3 px-3.5 py-2.5 rounded border border-[#B47818]/50 bg-[#B47818]/12 text-[#8A580B] dark:text-[#E7C37B] text-xs font-mono flex items-center justify-between gap-3" role="alert">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span>{{ session('warning') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Close" class="opacity-70 hover:opacity-100">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
@endif

@if(session('error'))
  <div class="mb-3 px-3.5 py-2.5 rounded border border-[#991B1B]/55 bg-[#991B1B]/10 text-[#991B1B] dark:text-[#FCA5A5] text-xs font-mono flex items-center justify-between gap-3" role="alert">
    <div class="flex items-center gap-2">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>{{ session('error') }}</span>
    </div>
    <button type="button" onclick="this.parentElement.remove()" aria-label="Close" class="opacity-70 hover:opacity-100">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
@endif

@if(isset($errors) && is_object($errors) && method_exists($errors, 'any') && $errors->any())
  <div class="mb-3 px-3.5 py-2.5 rounded border border-[#991B1B]/55 bg-[#991B1B]/10 text-[#991B1B] dark:text-[#FCA5A5] text-xs font-mono" role="alert">
    <div class="font-semibold flex items-center gap-2 mb-1">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>{{ $isId ? 'Validasi gagal:' : 'Validation errors:' }}</span>
    </div>
    <ul class="list-disc list-inside space-y-0.5 text-[11px]">
      @foreach($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif
