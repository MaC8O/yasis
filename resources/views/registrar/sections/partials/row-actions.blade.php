@php $btn = ($mobile ?? false) ? 'inline-flex items-center justify-center min-h-[44px] px-3 rounded-lg border border-neutral-200 text-sm font-semibold' : 'text-xs font-semibold hover:underline'; @endphp
<a href="{{ route('registrar.sections.show', $section) }}" class="{{ $btn }} text-brand {{ ($mobile ?? false) ? '' : 'mr-3' }}">Open</a>
@unless ($section->isArchived())
    <button type="button" @click="openEdit({{ $section->id }})" class="{{ $btn }} text-brand {{ ($mobile ?? false) ? '' : 'mr-3' }}">Edit</button>
    <form method="POST" action="{{ route('registrar.sections.archive', $section) }}" class="inline"
          data-confirm="Archive {{ $section->name }}? It will be hidden from section lists and pickers; its students, attendance and grades are kept."
          onsubmit="return confirm(this.dataset.confirm);">
        @csrf
        <button type="submit" class="{{ $btn }} text-warning {{ ($mobile ?? false) ? '' : 'mr-3' }}">Archive</button>
    </form>
@else
    <form method="POST" action="{{ route('registrar.sections.restore', $section) }}" class="inline"
          data-confirm="Restore {{ $section->name }} to the active section lists?" onsubmit="return confirm(this.dataset.confirm);">
        @csrf
        <button type="submit" class="{{ $btn }} text-brand {{ ($mobile ?? false) ? '' : 'mr-3' }}">Restore</button>
    </form>
@endunless
<form method="POST" action="{{ route('registrar.sections.destroy', $section) }}" class="inline"
      data-confirm="Delete {{ $section->name }} permanently? This is only allowed when it has no students, attendance, grades or promotion batches; its teaching assignments are removed with it."
      onsubmit="return confirm(this.dataset.confirm);">
    @csrf @method('DELETE')
    <button type="submit" class="{{ $btn }} text-danger">Delete</button>
</form>
