<x-app-layout title="Registrar Dashboard" subtitle="Manage student records, enrollment, guardians, sections & homeroom, transcripts & certificates, and student exits." badge="Registrar" role="registrar">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <x-stat-tile label="Active students" color="blue">{{ number_format($activeStudents) }}</x-stat-tile>
        <x-stat-tile label="Guardian link rate" color="yellow">{{ $guardianLinkRate }}%</x-stat-tile>
        <x-stat-tile label="Total guardians" color="green">{{ number_format($totalGuardians) }}</x-stat-tile>
        <x-stat-tile label="Documents queue" color="pink">{{ number_format($documentsQueue) }}</x-stat-tile>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Registrar work queue" subtitle="Daily record and enrollment tasks.">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-neutral-500 border-b border-neutral-200">
                        <th class="py-2 font-semibold">Task</th>
                        <th class="py-2 font-semibold">Count</th>
                        <th class="py-2 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-neutral-100">
                        <td class="py-2.5">Students missing guardian</td>
                        <td class="py-2.5">{{ $missingGuardian }}</td>
                        <td class="py-2.5"><a href="{{ route('registrar.students.index') }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Review</a></td>
                    </tr>
                    <tr class="border-b border-neutral-100">
                        <td class="py-2.5">Document requests queued</td>
                        <td class="py-2.5">{{ $documentsQueue }}</td>
                        <td class="py-2.5"><a href="{{ route('registrar.documents.index') }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Prepare</a></td>
                    </tr>
                    <tr class="border-b border-neutral-100">
                        <td class="py-2.5">Promotion batches pending co-approval</td>
                        <td class="py-2.5">{{ $pendingPromotions }}</td>
                        <td class="py-2.5"><a href="{{ route('registrar.promotions.index') }}" class="text-xs font-semibold text-[#1F573D] hover:underline">View</a></td>
                    </tr>
                    <tr>
                        <td class="py-2.5">Absence classifications needing correction</td>
                        <td class="py-2.5">{{ $needsCorrection }}</td>
                        <td class="py-2.5"><a href="{{ route('registrar.attendance-corrections.index') }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Correct</a></td>
                    </tr>
                </tbody>
            </table>
        </x-card>

        <x-card title="Quick actions">
            <div class="grid grid-cols-2 gap-3 text-sm font-semibold">
                <a href="{{ route('registrar.students.index') }}" class="hover:underline">Students</a>
                <a href="{{ route('registrar.guardians.index') }}" class="hover:underline">Guardians</a>
                <a href="{{ route('registrar.sections.index') }}" class="hover:underline">Sections</a>
                <a href="{{ route('registrar.documents.index') }}" class="hover:underline">Transcripts</a>
            </div>
            <a href="{{ route('registrar.students.create') }}" class="inline-block mt-4 bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Register Student</a>
        </x-card>
    </div>

    <x-card title="Enrollment by class" subtitle="Active placements per section, Nursery through Grade 12.">
        <x-chart.bar-list :items="$enrollmentByClass" label-width="w-32" />
    </x-card>

    <x-card title="Recent record activity" subtitle="Latest registrar actions.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Time</th>
                    <th class="py-2 font-semibold">By</th>
                    <th class="py-2 font-semibold">Action</th>
                    <th class="py-2 font-semibold">Entity</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentActivity as $log)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $log->created_at->format('M j, H:i') }}</td>
                        <td class="py-2.5">{{ $log->user?->name ?? '—' }}</td>
                        <td class="py-2.5">{{ $log->action }}</td>
                        <td class="py-2.5 text-neutral-500">{{ $log->entity_type }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
