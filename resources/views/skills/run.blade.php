@extends('layouts.app')
@php $isId = app()->getLocale() === 'id'; @endphp

@section('title', 'TRACE-S // Skill: ' . $skill->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--border-color)] pb-3">
  <div>
    <div class="label-meta">{{ $skill->skill_key }} &middot; v{{ $skill->version }} &middot; TOOL: {{ $skill->tool }}</div>
    <h1 class="font-mono text-sm font-bold uppercase">{{ $skill->name }}</h1>
  </div>
  <a href="{{ route('skills.index') }}" class="btn-secondary">{{ $isId ? 'Daftar Skills' : 'All Skills' }}</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <form method="POST" action="{{ route('skills.execute', $skill->skill_key) }}" class="panel p-5 space-y-4">
    @csrf
    <div class="label-meta">{{ $isId ? 'PARAMETER INPUT TEKS' : 'TEXT INPUT PARAMETERS' }} (MAX {{ $skill->configuration['max_input_chars'] ?? 5000 }} CHARS)</div>
    <p class="text-xs text-[var(--text-secondary)]">{{ $skill->description }}</p>

    @foreach($skill->input_schema ?? [] as $field)
      <div>
        <label class="block label-meta mb-1">
          {{ $field['label'] ?? $field['name'] }} @if($field['required'] ?? false)*@endif
        </label>
        @if(($field['type'] ?? 'textarea') === 'textarea')
          <textarea name="{{ $field['name'] }}" rows="5" @required($field['required'] ?? false) class="input-mil font-mono text-xs">{{ old($field['name'], $execution?->input[$field['name']] ?? '') }}</textarea>
        @else
          <input type="text" name="{{ $field['name'] }}" value="{{ old($field['name'], $execution?->input[$field['name']] ?? '') }}" @required($field['required'] ?? false) class="input-mil font-mono text-xs">
        @endif
      </div>
    @endforeach

    <button type="submit" class="btn-primary">
      <i class="fa-solid fa-play"></i><span>{{ $isId ? 'Jalankan Skill' : 'Execute Skill' }}</span>
    </button>
  </form>

  <div class="panel p-5 space-y-3">
    <div class="flex items-center justify-between border-b border-[var(--border-color)] pb-2">
      <span class="label-meta">{{ $isId ? 'KELUARAN SKILL' : 'EXECUTION OUTPUT' }}</span>
      @if($execution)
        <span class="badge {{ $execution->status === 'SUCCESS' ? 'badge-olive' : 'badge-danger' }}">{{ $execution->status }} &middot; {{ $execution->duration_ms }} ms</span>
      @endif
    </div>

    @if($execution)
      @if($execution->error)
        <div class="p-3 rounded border border-[#991B1B] bg-[#991B1B]/10 text-xs font-mono text-[#991B1B] dark:text-[#FCA5A5]">
          {{ $execution->error }}
        </div>
      @else
        <pre class="p-3 rounded bg-[var(--bg-subtle)] border border-[var(--border-color)] font-mono text-xs overflow-x-auto whitespace-pre-wrap">{{ json_encode($execution->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
      @endif
    @else
      <p class="text-xs text-[var(--text-muted)]">{{ $isId ? 'Kirim parameter teks di sebelah kiri untuk melihat hasil pemrosesan deterministik.' : 'Submit text parameters on the left to inspect deterministic output.' }}</p>
    @endif
  </div>
</div>
@endsection
