<x-app-layout :title="'Electives · '.$section->name" :subtitle="$section->gradeLevel->name.' · '.$section->academicYear->year_label.' · one subject from each slot per student'" badge="Registrar" role="registrar">
    <div class="space-y-6">
        <a href="{{ route('registrar.electives.index') }}" class="inline-flex items-center min-h-[44px] text-sm font-semibold text-neutral-500 hover:underline">← All sections with electives</a>

        <x-card title="Choices" subtitle="Pick each student's subject in every slot and save once. A choice can be changed later but not cleared.">
            @if ($students->isEmpty())
                <p class="text-sm text-neutral-400">No active students in this section.</p>
            @else
                <form method="POST" action="{{ route('registrar.electives.update', $section) }}">
                    @csrf @method('PUT')

                    {{-- Desktop / tablet table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                                    <th class="py-2 font-semibold">Student</th>
                                    @foreach ($slots as $slot => $options)
                                        <th class="py-2 font-semibold">Slot {{ $slot }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr class="border-b border-neutral-100 last:border-0">
                                        <td class="py-2.5 pr-3">
                                            <span class="font-semibold">{{ $student->name }}</span>
                                            <span class="block text-xs text-neutral-400">{{ $student->student_id_number }}</span>
                                        </td>
                                        @foreach ($slots as $slot => $options)
                                            <td class="py-2.5 pr-3">@include('registrar.electives.partials.slot-select', ['view' => 'd'])</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Phone cards --}}
                    <div class="md:hidden space-y-3">
                        @foreach ($students as $student)
                            <div class="rounded-xl border border-neutral-200 p-4">
                                <p class="font-semibold">{{ $student->name }} <span class="text-xs font-normal text-neutral-400">{{ $student->student_id_number }}</span></p>
                                <div class="mt-2 space-y-2">
                                    @foreach ($slots as $slot => $options)
                                        <div>
                                            <label for="m-{{ $student->id }}-{{ $slot }}" class="block text-xs font-semibold text-neutral-500 mb-1">Slot {{ $slot }}</label>
                                            @include('registrar.electives.partials.slot-select', ['view' => 'm'])
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="mt-4 w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm min-h-[44px] hover:bg-brand-dark">Save choices</button>
                </form>
            @endif
        </x-card>
    </div>

    <script>
        // The table (desktop) and the cards (phone) hold the same fields; only the visible copy is submitted.
        document.addEventListener('DOMContentLoaded', () => {
            const sync = () => {
                const phone = window.matchMedia('(max-width: 767px)').matches;
                document.querySelectorAll('[data-elective]').forEach((el) => { el.disabled = (el.dataset.elective === 'm') !== phone; });
            };
            sync();
            window.matchMedia('(max-width: 767px)').addEventListener('change', sync);
        });
    </script>
</x-app-layout>
