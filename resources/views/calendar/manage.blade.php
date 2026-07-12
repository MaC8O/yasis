@php
    $typeColors = \App\Models\CalendarEvent::TYPE_HEX;
    $prev = $cursor->copy()->subMonth();
    $next = $cursor->copy()->addMonth();
    $leadingBlanks = (int) $cursor->copy()->startOfMonth()->dayOfWeek;
    $daysInMonth = $cursor->daysInMonth;
    $prevDays = $prev->daysInMonth;
    $trailingBlanks = (7 - (($leadingBlanks + $daysInMonth) % 7)) % 7;
    $typeCounts = $monthEvents->countBy('event_type');

    $canDelete = fn ($event) => $ctx['canDeleteAny'] || ($event->status === 'Pending' && $event->created_by === auth()->id());
    $payload = fn ($event) => \Illuminate\Support\Js::from([
        'id' => $event->id, 'title' => $event->title, 'event_type' => $event->event_type,
        'start_date' => $event->start_date->toDateString(),
        'end_date' => optional($event->end_date)->toDateString() ?? '',
        'description' => $event->description ?? '',
        'academic_year_id' => $event->academic_year_id ?? '',
        'status' => $event->status,
        'can_delete' => $canDelete($event),
    ]);
    $statusPill = fn ($status) => $status === 'Published'
        ? 'bg-[#D7ECD9] text-[#1f4d2c]'
        : 'bg-[#F5E4A8] text-[#5c4a0a]';
@endphp

<x-app-layout title="Academic Calendar"
    subtitle="{{ $ctx['role'] === 'admin' ? 'Create, edit, approve and publish school events for all users.' : 'Add academic events (enrollment, exams, deadlines, holidays). Submissions are published once an Admin approves them.' }}"
    badge="{{ ucfirst($ctx['role']) }}" :role="$ctx['role']">
    <div x-data="{
        open: false,
        mode: 'create',
        base: '{{ $ctx['base'] }}',
        storeUrl: '{{ $ctx['storeUrl'] }}',
        active: {{ \Illuminate\Support\Js::from(array_keys($typeColors)) }},
        form: { id: null, title: '', event_type: 'Activity', start_date: '', end_date: '', description: '', academic_year_id: '{{ $activeYear?->id }}', can_delete: true },
        openCreate(date = '') {
            this.mode = 'create';
            this.form = { id: null, title: '', event_type: 'Activity', start_date: date, end_date: '', description: '', academic_year_id: '{{ $activeYear?->id }}', can_delete: true };
            this.open = true;
        },
        openEdit(ev) { this.mode = 'edit'; this.form = { ...ev }; this.open = true; },
        toggle(t) { this.active.includes(t) ? this.active = this.active.filter(x => x !== t) : this.active.push(t); },
        shown(t) { return this.active.includes(t); },
    }">
        {{-- Toolbar --}}
        <x-card class="!py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route($ctx['role'].'.calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800" aria-label="Previous month">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <a href="{{ route($ctx['role'].'.calendar.index', ['year' => $next->year, 'month' => $next->month]) }}"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800" aria-label="Next month">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                    <div class="ml-1">
                        <h2 class="text-xl font-bold leading-tight">{{ $cursor->format('F Y') }}</h2>
                        <p class="text-xs text-neutral-400">{{ $monthEvents->count() }} {{ Str::plural('event', $monthEvents->count()) }} this month</p>
                    </div>
                    @unless ($cursor->isSameMonth(now()))
                        <a href="{{ route($ctx['role'].'.calendar.index') }}" class="ml-2 text-sm font-semibold text-[#1F573D] border border-[#1F573D]/30 rounded-lg px-3 py-1.5 hover:bg-[#1F573D]/5">Today</a>
                    @endunless
                </div>
                <button type="button" @click="openCreate()"
                        class="inline-flex items-center gap-1.5 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-[#184630]">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add event
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-neutral-100">
                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400 mr-1">Filter</span>
                @foreach ($typeColors as $type => $color)
                    <button type="button" @click="toggle('{{ $type }}')"
                            :class="shown('{{ $type }}') ? '' : 'opacity-40'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 px-3 py-1 text-xs font-medium hover:border-neutral-300">
                        <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $color }}"></span>
                        {{ $type }}
                        <span class="text-neutral-400 tabular-nums">{{ $typeCounts[$type] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </x-card>

        {{-- Pending approval queue --}}
        @if ($pending->isNotEmpty())
            <x-card class="!border-[#E7C948] !bg-[#FCF7E6]">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 rounded-full bg-[#C9A227]"></span>
                    <h2 class="text-base font-bold">{{ $ctx['canPublish'] ? 'Awaiting your approval' : 'Awaiting Admin approval' }}</h2>
                    <span class="text-xs font-semibold bg-[#F5E4A8] text-[#5c4a0a] rounded-full px-2 py-0.5">{{ $pending->count() }}</span>
                </div>
                <div class="space-y-1.5">
                    @foreach ($pending as $event)
                        @php $c = $typeColors[$event->event_type] ?? '#6b7280'; @endphp
                        <div class="flex items-center gap-3 py-2 border-b border-[#EADFB8] last:border-0">
                            <span class="w-1 h-8 rounded-full shrink-0" style="background: {{ $c }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold truncate">{{ $event->title }}</p>
                                <p class="text-xs text-neutral-500">
                                    {{ $event->start_date->format('M j, Y') }}@if ($event->end_date && ! $event->end_date->isSameDay($event->start_date)) – {{ $event->end_date->format('M j') }}@endif
                                    · {{ $event->event_type }} · submitted by {{ $event->creator?->name ?? 'Registrar' }}
                                </p>
                            </div>
                            @if ($ctx['canPublish'])
                                <form method="POST" action="{{ $ctx['base'] }}/{{ $event->id }}/publish">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-white bg-[#1F573D] rounded-lg px-3 py-1.5 hover:bg-[#184630]">Approve &amp; publish</button>
                                </form>
                            @endif
                            <button type="button" @click="openEdit({{ $payload($event) }})" class="text-xs font-semibold text-blue-700 hover:underline">Edit</button>
                            @if ($canDelete($event))
                                <form method="POST" action="{{ $ctx['base'] }}/{{ $event->id }}"
                                      onsubmit="return confirm('{{ $ctx['canPublish'] ? 'Reject and delete' : 'Withdraw' }} &quot;{{ $event->title }}&quot;?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">{{ $ctx['canPublish'] ? 'Reject' : 'Withdraw' }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Month grid --}}
            <div class="lg:col-span-2">
                <x-card class="!p-0 overflow-hidden">
                    <div class="grid grid-cols-7 border-b border-neutral-200">
                        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $dow)
                            <div class="py-2.5 text-center text-[11px] font-semibold uppercase tracking-wide {{ in_array($i, [0,6]) ? 'text-neutral-300' : 'text-neutral-400' }}">{{ $dow }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @for ($b = $leadingBlanks; $b > 0; $b--)
                            <div class="min-h-28 border-b border-r border-neutral-100 bg-neutral-50/40 p-2"><span class="text-xs text-neutral-300">{{ $prevDays - $b + 1 }}</span></div>
                        @endfor
                        @for ($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $dow = ($leadingBlanks + $day - 1) % 7;
                                $isWeekend = in_array($dow, [0, 6]);
                                $isToday = $cursor->copy()->day($day)->isToday();
                            @endphp
                            <div class="min-h-28 border-b border-r border-neutral-100 last:border-r-0 p-1.5 flex flex-col gap-1 group relative {{ $isToday ? 'bg-[#1F573D]/[0.04]' : ($isWeekend ? 'bg-neutral-50/60' : 'bg-white') }}">
                                <div class="flex items-center justify-between px-0.5">
                                    <span class="text-xs font-semibold {{ $isToday ? 'bg-[#1F573D] text-white rounded-full w-6 h-6 flex items-center justify-center' : ($isWeekend ? 'text-neutral-400' : 'text-neutral-500') }}">{{ $day }}</span>
                                    <button type="button" @click="openCreate('{{ $cursor->copy()->day($day)->toDateString() }}')"
                                            class="opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center rounded text-neutral-300 hover:text-[#1F573D] hover:bg-[#1F573D]/10" aria-label="Add event on this day">
                                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                </div>
                                <div class="flex flex-col gap-1">
                                    @foreach (($eventsByDay[$day] ?? []) as $event)
                                        @php $c = $typeColors[$event->event_type] ?? '#6b7280'; $pendingChip = $event->status === 'Pending'; @endphp
                                        <button type="button" x-show="shown('{{ $event->event_type }}')" x-cloak
                                            @click="openEdit({{ $payload($event) }})"
                                            class="flex items-center gap-1.5 text-left text-[11px] leading-tight pl-1.5 pr-1 py-1 rounded-md hover:shadow-sm transition-shadow {{ $pendingChip ? 'border border-dashed' : 'border-l-[3px] border-solid' }}"
                                            style="background: {{ $c }}14; border-color: {{ $c }};{{ $pendingChip ? '' : ' border-left-width:3px;' }}"
                                            title="{{ $event->title }} · {{ $event->event_type }}{{ $pendingChip ? ' · Pending approval' : '' }}">
                                            <span class="truncate font-medium text-neutral-700">{{ $event->title }}</span>
                                            @if ($pendingChip)<span class="ml-auto text-[9px] uppercase font-bold shrink-0" style="color: {{ $c }};">•</span>@endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                        @for ($t = 1; $t <= $trailingBlanks; $t++)
                            <div class="min-h-28 border-b border-neutral-100 {{ $t === $trailingBlanks ? '' : 'border-r' }} bg-neutral-50/40 p-2"><span class="text-xs text-neutral-300">{{ $t }}</span></div>
                        @endfor
                    </div>
                    <div class="px-4 py-2.5 border-t border-neutral-100 text-xs text-neutral-400 flex items-center gap-4">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-0 border-t-[3px] border-neutral-400"></span>Published</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-2.5 border border-dashed border-neutral-400 rounded-sm"></span>Pending approval</span>
                    </div>
                </x-card>
            </div>

            {{-- Agenda --}}
            <div class="space-y-6">
                <x-card title="Upcoming" subtitle="The next events from today.">
                    <div class="space-y-1">
                        @forelse ($upcoming as $event)
                            @php $c = $typeColors[$event->event_type] ?? '#6b7280'; @endphp
                            <button type="button" @click="openEdit({{ $payload($event) }})"
                                    class="w-full flex items-start gap-3 rounded-xl p-2 -mx-2 hover:bg-neutral-50 text-left">
                                <div class="shrink-0 w-11 text-center rounded-lg py-1" style="background: {{ $c }}14;">
                                    <p class="text-[10px] uppercase font-semibold tabular-nums" style="color: {{ $c }};">{{ $event->start_date->format('M') }}</p>
                                    <p class="text-lg font-bold leading-none tabular-nums" style="color: {{ $c }};">{{ $event->start_date->format('j') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-semibold truncate">{{ $event->title }}</p>
                                        @if ($event->status === 'Pending')<span class="text-[10px] font-semibold {{ $statusPill('Pending') }} rounded-full px-1.5 py-0.5 shrink-0">Pending</span>@endif
                                    </div>
                                    <p class="text-xs text-neutral-400 mt-0.5">{{ $event->event_type }}
                                        @if ($event->end_date && ! $event->end_date->isSameDay($event->start_date)) · until {{ $event->end_date->format('M j') }} @endif
                                    </p>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-neutral-400 text-center py-6">No upcoming events.</p>
                        @endforelse
                    </div>
                </x-card>

                <x-card title="{{ $cursor->format('F') }} agenda" subtitle="Everything scheduled this month.">
                    <div class="space-y-0.5">
                        @forelse ($monthEvents as $event)
                            @php $c = $typeColors[$event->event_type] ?? '#6b7280'; @endphp
                            <div x-show="shown('{{ $event->event_type }}')" x-cloak class="flex items-center gap-2.5 py-2 border-b border-neutral-100 last:border-0 group">
                                <span class="w-1 h-8 rounded-full shrink-0" style="background: {{ $c }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-semibold truncate">{{ $event->title }}</p>
                                        <span class="text-[10px] font-semibold {{ $statusPill($event->status) }} rounded-full px-1.5 py-0.5 shrink-0">{{ $event->status }}</span>
                                    </div>
                                    <p class="text-xs text-neutral-400">{{ $event->start_date->format('D, M j') }}@if ($event->end_date && ! $event->end_date->isSameDay($event->start_date)) – {{ $event->end_date->format('M j') }}@endif · {{ $event->event_type }}</p>
                                </div>
                                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if ($ctx['canPublish'] && $event->status === 'Published')
                                        <form method="POST" action="{{ $ctx['base'] }}/{{ $event->id }}/unpublish">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-neutral-500 hover:underline">Unpublish</button>
                                        </form>
                                    @endif
                                    <button type="button" @click="openEdit({{ $payload($event) }})" class="text-xs font-semibold text-blue-700 hover:underline">Edit</button>
                                    @if ($canDelete($event))
                                        <form method="POST" action="{{ $ctx['base'] }}/{{ $event->id }}"
                                              onsubmit="return confirm('Delete &quot;{{ $event->title }}&quot;?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400 py-2">No events this month.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-black/50" @click="open = false" x-transition.opacity></div>
            <div class="relative bg-white rounded-2xl w-full max-w-lg p-6 shadow-xl max-h-[90vh] overflow-y-auto"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-lg font-bold" x-text="mode === 'create' ? 'Add event' : 'Edit event'"></h3>
                    <button type="button" @click="open = false" class="text-neutral-400 hover:text-neutral-700" aria-label="Close">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <p class="text-xs text-neutral-400 mb-4" x-show="mode === 'create'">{{ $ctx['newStatusNote'] }}</p>
                <form method="POST" :action="mode === 'create' ? storeUrl : `${base}/${form.id}`" class="space-y-4">
                    @csrf
                    <template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Title</label>
                        <input type="text" name="title" x-model="form.title" required maxlength="120" placeholder="e.g. Enrollment opens"
                               class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Type</label>
                            <select name="event_type" x-model="form.event_type" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                                @foreach ($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">Academic year</label>
                            <select name="academic_year_id" x-model="form.academic_year_id" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                                <option value="">— None —</option>
                                @foreach ($academicYears as $ay)<option value="{{ $ay->id }}">{{ $ay->year_label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold mb-1">Start date</label>
                            <input type="date" name="start_date" x-model="form.start_date" required
                                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-1">End date <span class="text-neutral-400 font-normal">(optional)</span></label>
                            <input type="date" name="end_date" x-model="form.end_date" :min="form.start_date"
                                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Description <span class="text-neutral-400 font-normal">(optional)</span></label>
                        <textarea name="description" x-model="form.description" rows="2" maxlength="500"
                                  class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:border-[#1F573D] focus:ring-1 focus:ring-[#1F573D] outline-none"></textarea>
                    </div>
                    <div class="flex items-center justify-between gap-3 pt-2">
                        <template x-if="mode === 'edit' && form.can_delete">
                            <button type="button" @click="$refs.deleteForm.action = `${base}/${form.id}`; $refs.deleteForm.requestSubmit()"
                                    class="text-sm font-semibold text-red-700 hover:underline">Delete</button>
                        </template>
                        <div class="flex items-center gap-3 ml-auto">
                            <button type="button" @click="open = false" class="text-sm font-semibold text-neutral-500 px-4 py-2">Cancel</button>
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-[#184630]"
                                    x-text="mode === 'create' ? 'Add event' : 'Save changes'"></button>
                        </div>
                    </div>
                </form>
                <form method="POST" x-ref="deleteForm" onsubmit="return confirm('Delete this event?');" class="hidden">@csrf @method('DELETE')</form>
            </div>
        </div>
    </div>
</x-app-layout>
