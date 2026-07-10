<x-app-layout title="Approvals" subtitle="Two-key governance — the VP Academic signs first, you co-approve." badge="Two-key co-approval" role="principal">
    <x-card>
        <p class="text-sm text-neutral-600">
            Promotions, transcript releases, and post-lock grade changes require both the VP Academic and the Principal.
            The VP signs first, you provide the second key. Every decision is written to the audit log for non-repudiation.
        </p>
    </x-card>

    <x-card title="Promotion batches — VP approved, awaiting your co-sign">
        <div class="space-y-4">
            @forelse ($promotionBatches as $batch)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">{{ $batch->fromSection->name }} — {{ $batch->items->count() }} students</p>
                        <p class="text-xs text-neutral-500">
                            VP: {{ $batch->vpApprovedBy->user->name ?? '—' }} &check; {{ $batch->vp_approved_at?->format('d M') }}
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('principal.approvals.promotions.approve', $batch) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Co-approve</button>
                        </form>
                        <form method="POST" action="{{ route('principal.approvals.promotions.reject', $batch) }}">
                            @csrf
                            <button type="submit" class="border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm">Return</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No promotion batches awaiting your co-approval.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Transcript releases — VP approved, awaiting your co-sign">
        <div class="space-y-4">
            @forelse ($transcripts as $doc)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">{{ $doc->student->name }}</p>
                        <p class="text-xs text-neutral-500">
                            VP: {{ $doc->approvedBy->user->name ?? '—' }} &check; {{ $doc->approved_at?->format('d M') }}
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('principal.approvals.transcripts.approve', $doc) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Co-approve</button>
                        </form>
                        <form method="POST" action="{{ route('principal.approvals.transcripts.reject', $doc) }}">
                            @csrf
                            <button type="submit" class="border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm">Return</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No transcripts awaiting your co-approval.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Post-lock grade changes — VP approved, awaiting your co-sign" subtitle="Co-approving applies the corrected score immediately.">
        <div class="space-y-4">
            @forelse ($gradeChanges as $req)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">
                            {{ $req->student->name }} ·
                            {{ $req->assessment->category->subject->name ?? '' }} — {{ $req->assessment->name }}
                            ({{ $req->term->name }}):
                            {{ $req->old_score !== null ? $req->old_score + 0 : 'no score' }} → <span class="text-[#1F573D]">{{ $req->new_score + 0 }}</span>
                        </p>
                        <p class="text-xs text-neutral-500">
                            {{ $req->assessment->category->section->name ?? '' }} ·
                            Requested by {{ $req->requestedBy->user->name ?? '—' }} · Reason: {{ $req->reason }} ·
                            VP: {{ $req->vpApprovedBy->user->name ?? '—' }} &check; {{ $req->vp_approved_at?->format('d M') }}
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('principal.approvals.grade-changes.approve', $req) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Co-approve &amp; apply</button>
                        </form>
                        <form method="POST" action="{{ route('principal.approvals.grade-changes.reject', $req) }}">
                            @csrf
                            <button type="submit" class="border border-neutral-300 text-neutral-700 font-semibold rounded-lg px-4 py-2 text-sm">Reject</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No grade-change requests awaiting your co-approval.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
