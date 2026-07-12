@php
    $typeColors = \App\Models\CalendarEvent::TYPE_HEX;
    $prev = $cursor->copy()->subMonth();
    $next = $cursor->copy()->addMonth();
    $leadingBlanks = (int) $cursor->copy()->startOfMonth()->dayOfWeek;
    $daysInMonth = $cursor->daysInMonth;
    $prevDays = $prev->daysInMonth;
    $trailingBlanks = (7 - (($leadingBlanks + $daysInMonth) % 7)) % 7;
    $typeCounts = $monthEvents->countBy('event_type');
    $payload = fn ($event) => \Illuminate\Support\Js::from([
        'title' => $event->title, 'event_type' => $event->event_type,
        'start' => $event->start_date->format('D, M j, Y'),
        'end' => ($event->end_date && ! $event->end_date->isSameDay($event->start_date)) ? $event->end_date->format('D, M j, Y') : '',
        'description' => $event->description ?? '',
    ]);
@endphp

<x-app-layout title="Academic Calendar" subtitle="School events, holidays, exams and term breaks." badge="{{ ucwords(str_replace('_',' ',$role)) }}" :role="$role">
    <div x-data="{
        active: {{ \Illuminate\Support\Js::from(array_keys($typeColors)) }},
        detail: null,
        toggle(t) { this.active.includes(t) ? this.active = this.active.filter(x => x !== t) : this.active.push(t); },
        shown(t) { return this.active.includes(t); },
        view(ev) { this.detail = ev; },
    }">
        <x-card class="!py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800" aria-label="Previous month">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <a href="{{ route('calendar.index', ['year' => $next->year, 'month' => $next->month]) }}"
                       class="w-9 h-9 flex items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50 hover:text-neutral-800" aria-label="Next month">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                    <div class="ml-1">
                        <h2 class="text-xl font-bold leading-tight">{{ $cursor->format('F Y') }}</h2>
                        <p class="text-xs text-neutral-400">{{ $monthEvents->count() }} {{ Str::plural('event', $monthEvents->count()) }} this month</p>
                    </div>
                    @unless ($cursor->isSameMonth(now()))
                        <a href="{{ route('calendar.index') }}" class="ml-2 text-sm font-semibold text-[#1F573D] border border-[#1F573D]/30 rounded-lg px-3 py-1.5 hover:bg-[#1F573D]/5">Today</a>
                    @endunless
                </div>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                            <div class="min-h-28 border-b border-r border-neutral-100 last:border-r-0 p-1.5 flex flex-col gap-1 {{ $isToday ? 'bg-[#1F573D]/[0.04]' : ($isWeekend ? 'bg-neutral-50/60' : 'bg-white') }}">
                                <span class="text-xs font-semibold px-0.5 {{ $isToday ? 'bg-[#1F573D] text-white rounded-full w-6 h-6 flex items-center justify-center' : ($isWeekend ? 'text-neutral-400' : 'text-neutral-500') }}">{{ $day }}</span>
                                <div class="flex flex-col gap-1">
                                    @foreach (($eventsByDay[$day] ?? []) as $event)
                                        @php $c = $typeColors[$event->event_type] ?? '#6b7280'; @endphp
                                        <button type="button" x-show="shown('{{ $event->event_type }}')" x-cloak
                                            @click="view({{ $payload($event) }})"
                                            class="flex items-center gap-1.5 text-left text-[11px] leading-tight pl-1.5 pr-1 py-1 rounded-md hover:shadow-sm transition-shadow"
                                            style="background: {{ $c }}14; border-left: 3px solid {{ $c }};"
                                            title="{{ $event->title }} · {{ $event->event_type }}">
                                            <span class="truncate font-medium text-neutral-700">{{ $event->title }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                        @for ($t = 1; $t <= $trailingBlanks; $t++)
                            <div class="min-h-28 border-b border-neutral-100 {{ $t === $trailingBlanks ? '' : 'border-r' }} bg-neutral-50/40 p-2"><span class="text-xs text-neutral-300">{{ $t }}</span></div>
                        @endfor
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Upcoming" subtitle="The next events from today.">
                    <div class="space-y-1">
                        @forelse ($upcoming as $event)
                            @php $c = $typeColors[$event->event_type] ?? '#6b7280'; @endphp
                            <button type="button" @click="view({{ $payload($event) }})"
                                    class="w-full flex items-start gap-3 rounded-xl p-2 -mx-2 hover:bg-neutral-50 text-left">
                                <div class="shrink-0 w-11 text-center rounded-lg py-1" style="background: {{ $c }}14;">
                                    <p class="text-[10px] uppercase font-semibold tabular-nums" style="color: {{ $c }};">{{ $event->start_date->format('M') }}</p>
                                    <p class="text-lg font-bold leading-none tabular-nums" style="color: {{ $c }};">{{ $event->start_date->format('j') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold truncate">{{ $event->title }}</p>
                                    <p class="text-xs text-neutral-400 mt-0.5">
                                        {{ $event->event_type }}
                                        @if ($event->end_date && ! $event->end_date->isSameDay($event->start_date))
                                            · until {{ $event->end_date->format('M j') }}
                                        @else
                                            · {{ $event->start_date->diffForHumans(['parts' => 1]) }}
                                        @endif
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
                            <button type="button" x-show="shown('{{ $event->event_type }}')" x-cloak
                                @click="view({{ $payload($event) }})"
                                class="w-full flex items-center gap-2.5 py-2 border-b border-neutral-100 last:border-0 text-left hover:bg-neutral-50 rounded-lg -mx-1 px-1">
                                <span class="w-1 h-8 rounded-full shrink-0" style="background: {{ $c }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold truncate">{{ $event->title }}</p>
                                    <p class="text-xs text-neutral-400">
                                        {{ $event->start_date->format('D, M j') }}@if ($event->end_date && ! $event->end_date->isSameDay($event->start_date)) – {{ $event->end_date->format('M j') }}@endif
                                        · {{ $event->event_type }}
                                    </p>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-neutral-400 py-2">No events this month.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        </div>

        {{-- Read-only detail popover --}}
        <div x-show="detail" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="detail = null">
            <div class="absolute inset-0 bg-black/50" @click="detail = null" x-transition.opacity></div>
            <div class="relative bg-white rounded-2xl w-full max-w-md p-6 shadow-xl"
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-lg font-bold" x-text="detail?.title"></h3>
                    <button type="button" @click="detail = null" class="text-neutral-400 hover:text-neutral-700 shrink-0" aria-label="Close">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <span class="inline-block mt-1 rounded-full bg-neutral-100 text-neutral-600 text-xs font-medium px-2.5 py-0.5" x-text="detail?.event_type"></span>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-16 shrink-0 text-neutral-400">Date</dt>
                        <dd class="font-medium"><span x-text="detail?.start"></span><template x-if="detail?.end"><span> – <span x-text="detail?.end"></span></span></template></dd>
                    </div>
                    <template x-if="detail?.description">
                        <div class="flex gap-2">
                            <dt class="w-16 shrink-0 text-neutral-400">Details</dt>
                            <dd class="text-neutral-600" x-text="detail?.description"></dd>
                        </div>
                    </template>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
