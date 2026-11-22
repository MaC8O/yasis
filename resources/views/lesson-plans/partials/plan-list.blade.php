{{--
    Plans for VP / Principal / Admin: a table on wide screens, cards on phones.
    $plans (collection or paginator, with teacher.user, section.department, subject, term)
--}}
<div class="hidden md:block overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-neutral-500 border-b border-neutral-200">
                <th class="py-2 font-semibold">Plan</th>
                <th class="py-2 font-semibold">Teacher</th>
                <th class="py-2 font-semibold">Class</th>
                <th class="py-2 font-semibold">Term</th>
                <th class="py-2 font-semibold">Submitted</th>
                <th class="py-2 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr class="border-b border-neutral-100 last:border-0">
                    <td class="py-2.5 pr-3"><a href="{{ route('lesson-plans.show', $plan) }}" class="font-semibold text-brand hover:underline">{{ $plan->title }}</a>
                        <span class="block text-xs text-neutral-400">Version {{ $plan->current_version }}</span></td>
                    <td class="py-2.5 pr-3 text-neutral-700">{{ $plan->teacher?->user?->name }}</td>
                    <td class="py-2.5 pr-3 text-neutral-700">{{ $plan->subject?->name }} · {{ $plan->section?->name }}
                        <span class="block text-xs text-neutral-400">{{ $plan->section?->department?->name }}</span></td>
                    <td class="py-2.5 pr-3 text-neutral-700">{{ $plan->term?->name }}</td>
                    <td class="py-2.5 pr-3 text-neutral-600 whitespace-nowrap">{{ $plan->submitted_at->timezone(config('app.timezone'))->format('j M Y') }}</td>
                    <td class="py-2.5"><x-badge :color="$plan->statusColor()">{{ $plan->statusLabel() }}</x-badge></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<ul class="md:hidden space-y-3">
    @foreach ($plans as $plan)
        <li>
            <a href="{{ route('lesson-plans.show', $plan) }}" class="block border border-neutral-200 rounded-xl p-4 active:bg-neutral-50">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-brand break-words">{{ $plan->title }}</p>
                        <p class="text-xs text-neutral-500">{{ $plan->teacher?->user?->name }}</p>
                    </div>
                    <x-badge :color="$plan->statusColor()" class="shrink-0">{{ $plan->statusLabel() }}</x-badge>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-xs text-neutral-500">Class</dt><dd class="font-medium">{{ $plan->subject?->name }} · {{ $plan->section?->name }}</dd></div>
                    <div><dt class="text-xs text-neutral-500">Term</dt><dd class="font-medium">{{ $plan->term?->name }}</dd></div>
                    <div><dt class="text-xs text-neutral-500">Submitted</dt><dd class="font-medium">{{ $plan->submitted_at->timezone(config('app.timezone'))->format('j M Y') }}</dd></div>
                    <div><dt class="text-xs text-neutral-500">Version</dt><dd class="font-medium tabular-nums">{{ $plan->current_version }}</dd></div>
                </dl>
            </a>
        </li>
    @endforeach
</ul>
