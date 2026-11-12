@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // Skill: ' . $skill->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <div class="label-meta">{{ $skill->skill_key }} &middot; v{{ $skill->version }} &middot; TOOL: {{ $skill->tool }}</div>
    <h1 class="font-mono text-sm font-bold uppercase">{{ $skill->name }}</h1>
  </div>
  <div class="flex items-center gap-2">
    <span class="badge {{ $skill->status === 'ENABLED' ? 'badge-olive' : 'badge-warn' }}">{{ $skill->status }}</span>
    <a href="{{ route('admin.skills.index') }}" class="btn-secondary">{{ $isId ? 'Daftar Skills' : 'Back' }}</a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="space-y-4">
    <div class="panel p-4 space-y-3">
      <div class="label-meta">{{ $isId ? 'STATUS AKTIVASI' : 'ACTIVATION CONTROL' }}</div>
      @if($skill->status === 'ENABLED')
        <form method="POST" action="{{ route('admin.skills.act', $skill->skill_key) }}">
          @csrf
          <input type="hidden" name="action" value="disable">
          <button type="submit" class="w-full btn-danger justify-center">{{ $isId ? 'Nonaktifkan Skill' : 'Disable Skill' }}</button>
        </form>
      @else
        <form method="POST" action="{{ route('admin.skills.act', $skill->skill_key) }}">
          @csrf
          <input type="hidden" name="action" value="enable">
          <button type="submit" class="w-full btn-primary justify-center">{{ $isId ? 'Aktifkan Skill' : 'Enable Skill' }}</button>
        </form>
      @endif

      @if(Auth::user()->hasPermission('skills.install') && $skill->installation_status === 'INSTALLED')
        <form method="POST" action="{{ route('admin.skills.act', $skill->skill_key) }}" class="pt-2 border-t border-[var(--border-color)]">
          @csrf
          <input type="hidden" name="action" value="remove">
          <button type="submit" class="w-full btn-danger justify-center">{{ $isId ? 'Hapus Instalasi' : 'Uninstall Skill' }}</button>
        </form>
      @endif
    </div>

    <form method="POST" action="{{ route('admin.skills.act', $skill->skill_key) }}" class="panel p-4 space-y-3">
      @csrf
      <input type="hidden" name="action" value="configure">
      <div class="label-meta">{{ $isId ? 'BATAS KARAKTER INPUT' : 'INPUT CHARACTER LIMIT' }}</div>
      <input type="number" name="max_input_chars" min="100" max="5000" value="{{ $skill->configuration['max_input_chars'] ?? 5000 }}" class="input-mil font-mono text-xs">
      <button type="submit" class="w-full btn-secondary justify-center">{{ $isId ? 'Simpan Konfigurasi' : 'Save Configuration' }}</button>
    </form>
  </div>

  <div class="lg:col-span-2 space-y-4">
    <form method="POST" action="{{ route('admin.skills.act', $skill->skill_key) }}" class="panel p-4 space-y-3">
      @csrf
      <input type="hidden" name="action" value="assign">
      <div class="label-meta">{{ $isId ? 'PENUGASAN AKSES SKILL KE PERSONEL ORGANISASI' : 'ASSIGN SKILL PERMISSION TO ORGANIZATION USERS' }}</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-1">
        @foreach($operators as $op)
          @php $checked = $granted->has($op->id) && (bool) $granted->get($op->id)->pivot->enabled; @endphp
          <label class="flex items-center gap-2 p-2 rounded border border-[var(--border-color)] text-xs cursor-pointer">
            <input type="checkbox" name="user_ids[]" value="{{ $op->id }}" @checked($checked) class="accent-[#586B47]">
            <div>
              <div class="font-mono font-semibold">{{ $op->trace_id }} ({{ $op->roleLabel() }})</div>
              <div class="text-[11px] text-[var(--text-muted)]">{{ $op->name }}</div>
            </div>
          </label>
        @endforeach
      </div>
      <button type="submit" class="btn-primary">{{ $isId ? 'Simpan Penugasan' : 'Update Assignments' }}</button>
    </form>

    <div class="panel overflow-hidden">
      <div class="panel-header"><span class="font-mono text-xs font-bold uppercase">{{ $isId ? 'Eksekusi Terakhir' : 'Recent Executions' }}</span></div>
      <div class="overflow-x-auto">
        <table class="table-mil">
          <thead><tr><th>TIME</th><th>USER</th><th>STATUS</th><th>DURATION</th></tr></thead>
          <tbody>
            @forelse($executions as $ex)
              <tr>
                <td class="font-mono text-xs text-[var(--text-muted)]">{{ $ex->created_at?->format('Y-m-d H:i') }}</td>
                <td class="font-mono text-xs">{{ $ex->user?->trace_id ?? '-' }}</td>
                <td><span class="badge {{ $ex->status === 'SUCCESS' ? 'badge-olive' : 'badge-danger' }}">{{ $ex->status }}</span></td>
                <td class="font-mono text-xs">{{ $ex->duration_ms }} ms</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-xs text-[var(--text-muted)] py-4">-</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
