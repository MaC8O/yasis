<x-app-layout title="Expulsion reviews" subtitle="Each case needs a decision from both the VP and the Principal. Nobody is expelled automatically.">
    <x-card>
        @if ($cases->isEmpty())
            <p class="text-sm text-neutral-400">No expulsion review cases.</p>
        @else
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-neutral-500 border-b border-neutral-200">
                            <th class="py-2 font-semibold">Student</th>
                            <th class="py-2 font-semibold">Opened</th>
                            <th class="py-2 font-semibold">Status</th>
                            <th class="py-2 font-semibold">Decisions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cases as $case)
                            <tr class="border-b border-neutral-100 last:border-0">
                                <td class="py-2.5"><a href="{{ route('behavior.cases.show', $case) }}" class="font-semibold text-brand hover:underline">{{ $case->student?->name }}</a></td>
                                <td class="py-2.5">{{ $case->created_at->format('j M Y') }}</td>
                                <td class="py-2.5"><x-badge :color="$case->isOpen() ? 'yellow' : 'neutral'">{{ $case->isOpen() ? 'Open · round '.$case->round.' · waiting for the '.$case->awaiting : 'Decided: '.$case->outcome }}</x-badge></td>
                                <td class="py-2.5 text-neutral-500">{{ $case->decisions->count() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden space-y-3">
                @foreach ($cases as $case)
                    <li><a href="{{ route('behavior.cases.show', $case) }}" class="block border border-neutral-200 rounded-xl p-4">
                        <p class="font-semibold text-brand">{{ $case->student?->name }}</p>
                        <p class="text-xs text-neutral-500">Opened {{ $case->created_at->format('j M Y') }} · {{ $case->decisions->count() }} decision(s)</p>
                        <x-badge class="mt-2" :color="$case->isOpen() ? 'yellow' : 'neutral'">{{ $case->isOpen() ? 'Open · round '.$case->round.' · waiting for the '.$case->awaiting : 'Decided: '.$case->outcome }}</x-badge>
                    </a></li>
                @endforeach
            </ul>
            <div class="mt-4 text-xs">{{ $cases->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
