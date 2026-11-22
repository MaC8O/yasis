{{-- Switch between the review queue and the list of all plans (VP, Principal, Admin). $active: 'all' | 'queue'; $queueCount --}}
<nav class="flex gap-2 text-sm" aria-label="Lesson plan lists">
    <a href="{{ route('lesson-plans.queue') }}" @if ($active === 'queue') aria-current="page" @endif
       class="rounded-lg px-4 py-2 font-semibold {{ $active === 'queue' ? 'bg-brand text-white' : 'bg-white border border-neutral-200 text-neutral-700 hover:bg-neutral-50' }}">
        Review queue <span class="tabular-nums">({{ $queueCount }})</span>
    </a>
    <a href="{{ route('lesson-plans.index') }}" @if ($active === 'all') aria-current="page" @endif
       class="rounded-lg px-4 py-2 font-semibold {{ $active === 'all' ? 'bg-brand text-white' : 'bg-white border border-neutral-200 text-neutral-700 hover:bg-neutral-50' }}">
        All plans
    </a>
</nav>
