<x-app-layout :title="$student->name" :subtitle="$student->student_id_number" badge="Registrar" role="registrar">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="Profile">
            <div class="flex items-center gap-4 mb-4">
                @if ($student->photo_path)
                    <img src="{{ Storage::url($student->photo_path) }}" alt="{{ $student->name }}"
                         class="w-20 h-20 rounded-xl object-cover border border-neutral-200 shrink-0">
                @else
                    <span class="w-20 h-20 rounded-xl bg-brand text-white font-bold text-xl flex items-center justify-center shrink-0">
                        {{ collect(explode(' ', $student->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                    </span>
                @endif
                <div>
                    <p class="font-bold text-lg">{{ $student->name }}</p>
                    <p class="text-sm text-neutral-500">{{ $student->student_id_number }}</p>
                </div>
            </div>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-neutral-500">Department</dt><dd class="font-semibold">{{ $student->department?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Birth date</dt><dd>{{ $student->date_of_birth?->format('M j, Y') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Gender</dt><dd>{{ $student->gender ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Admission date</dt><dd>{{ $student->admission_date->format('M j, Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-neutral-500">Status</dt><dd><x-badge :color="$student->enrollment_status === 'Enrolled' ? 'green' : 'pink'">{{ $student->enrollment_status }}</x-badge></dd></div>
            </dl>
            <a href="{{ route('registrar.students.edit', $student) }}" class="inline-block mt-4 text-sm font-semibold text-brand hover:underline">Edit profile</a>
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
            <a href="{{ route('registrar.guardians.index', ['student' => $student->id]) }}" class="inline-block mt-4 text-sm font-semibold text-brand hover:underline">Manage guardian links</a>
        </x-card>

        <x-card title="Portal login" subtitle="The student's own sign-in to view grades, attendance and schedule.">
            @if ($student->user)
                <p class="text-sm text-neutral-500 mb-3">{{ $student->user->email }}</p>
                <x-badge :color="match ($student->user->status) { 'Active' => 'green', 'Inactive' => 'pink', default => 'yellow' }">Portal {{ strtolower($student->user->status) }}</x-badge>
                @include('registrar.partials.account-status-actions', ['account' => $student->user])
            @else
                <p class="text-sm text-neutral-400 mb-3">No portal login yet. Creating one e-mails the student a link to set their password.</p>
                <form method="POST" action="{{ route('registrar.students.login.store', $student) }}" class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <label for="student-login-email" class="sr-only">Student e-mail</label>
                    <input id="student-login-email" type="email" name="email" value="{{ old('email') }}" required placeholder="student@example.com"
                           class="flex-1 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                    <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2 text-sm hover:bg-brand-dark transition-colors">Create portal login</button>
                </form>
            @endif
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
            @foreach ($pendingExitRequests as $exitRequest)
                <div role="status" class="mb-4 flex items-start gap-3 bg-warning-soft border border-warning-line text-warning text-sm rounded-xl px-4 py-3">
                    <x-icon name="alert" class="w-5 h-5 mt-0.5 shrink-0" />
                    <p><span class="font-semibold">Pending exit request</span> from an expulsion review, created {{ $exitRequest->created_at->format('j M Y') }}. {{ $exitRequest->reason }}
                        The student’s status has not been changed. Process it with Transfer / Drop below and tick the box to confirm this exit is that request.</p>
                </div>
            @endforeach
            @if ($student->enrollment_status === 'Enrolled')
                <div class="flex flex-wrap gap-3 items-start">
                    <form method="POST" action="{{ route('registrar.students.transfer', $student) }}" class="space-y-2"
                          data-confirm="Mark this student as Transferred and create a Transfer/Leaving Certificate draft?" onsubmit="return confirm(this.dataset.confirm);">
                        @csrf
                        @foreach ($pendingExitRequests as $exitRequest)
                            <label class="flex items-start gap-2 text-sm max-w-sm">
                                <input type="checkbox" name="exit_request_id" value="{{ $exitRequest->id }}" class="w-5 h-5 mt-0.5 rounded border-neutral-300">
                                <span>This exit is the pending exit request from {{ $exitRequest->created_at->format('j M Y') }}</span>
                            </label>
                        @endforeach
                        <button type="submit" class="bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700 transition-colors">Transfer / Drop</button>
                    </form>
                    <form method="POST" action="{{ route('registrar.students.graduate', $student) }}" onsubmit="return confirm('Mark this student as Graduated and create Completion Certificate + final transcript drafts?');">
                        @csrf
                        <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-brand-dark transition-colors">Mark Graduated</button>
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
                        <td class="py-2.5 text-right"><a href="{{ route('registrar.documents.index') }}" class="text-xs font-semibold text-brand hover:underline">Manage</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-neutral-400">No documents requested yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-app-layout>
