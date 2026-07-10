<x-app-layout :title="$student->first_name.' '.$student->last_name" :subtitle="$student->student_id_number" badge="Registrar" role="registrar">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Profile">
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-neutral-500">Department</dt><dd class="font-semibold">{{ $student->department?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Birth date</dt><dd>{{ $student->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Gender</dt><dd>{{ $student->gender ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Admission date</dt><dd>{{ $student->admission_date->format('M j, Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Status</dt><dd><x-badge :color="$student->enrollment_status === 'Enrolled' ? 'green' : 'pink'">{{ $student->enrollment_status }}</x-badge></dd></div>
            </dl>
            <a href="{{ route('registrar.students.edit', $student) }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Edit profile</a>
        </x-card>

        <x-card title="Guardians">
            @forelse ($student->guardians as $guardian)
                <div class="flex justify-between items-center py-2 border-b border-neutral-100 last:border-0 text-sm">
                    <div>
                        <p class="font-semibold">{{ $guardian->user?->name }}</p>
                        <p class="text-neutral-500">{{ $guardian->relationship }} · {{ $guardian->user?->email }}</p>
                    </div>
                    @if ($guardian->pivot->is_primary)
                        <x-badge color="green">Primary</x-badge>
                    @endif
                </div>
            @empty
                <p class="text-sm text-neutral-400">No guardian linked yet.</p>
            @endforelse
            <a href="{{ route('registrar.guardians.index', ['student' => $student->id]) }}" class="inline-block mt-4 text-sm font-semibold text-[#1F573D] hover:underline">Manage guardian links</a>
        </x-card>

        <x-card title="Enrollment">
            @forelse ($student->enrollments as $enrollment)
                <div class="flex justify-between items-center py-2 border-b border-neutral-100 last:border-0 text-sm">
                    <span>{{ $enrollment->section?->name }}</span>
                    <x-badge color="blue">{{ $enrollment->status }}</x-badge>
                </div>
            @empty
                <p class="text-sm text-neutral-400">Not enrolled in a section yet.</p>
            @endforelse
        </x-card>

        <x-card title="Student exit" subtitle="Transfer/drop or graduation processing.">
            @if ($student->enrollment_status === 'Enrolled')
                <div class="flex gap-3">
                    <form method="POST" action="{{ route('registrar.students.transfer', $student) }}" onsubmit="return confirm('Mark this student as Transferred and create a Transfer/Leaving Certificate draft?');">
                        @csrf
                        <button type="submit" class="bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Transfer / Drop</button>
                    </form>
                    <form method="POST" action="{{ route('registrar.students.graduate', $student) }}" onsubmit="return confirm('Mark this student as Graduated and create Completion Certificate + final transcript drafts?');">
                        @csrf
                        <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2.5 text-sm">Mark Graduated</button>
                    </form>
                </div>
            @else
                <x-badge color="blue">Already {{ $student->enrollment_status }}</x-badge>
            @endif
        </x-card>
    </div>

    <x-card title="Document requests" subtitle="Transcripts and certificates for this student.">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-neutral-500 border-b border-neutral-200">
                    <th class="py-2 font-semibold">Type</th>
                    <th class="py-2 font-semibold">Status</th>
                    <th class="py-2 font-semibold">Requested</th>
                    <th class="py-2 font-semibold"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($student->documentRequests as $doc)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="py-2.5">{{ $doc->type }}</td>
                        <td class="py-2.5"><x-badge color="yellow">{{ $doc->status }}</x-badge></td>
                        <td class="py-2.5 text-neutral-500">{{ $doc->created_at->format('M j, Y') }}</td>
                        <td class="py-2.5 text-right"><a href="{{ route('registrar.documents.index') }}" class="text-xs font-semibold text-[#1F573D] hover:underline">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No documents requested yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
