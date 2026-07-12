<x-app-layout title="Approvals" subtitle="Two-key governance — you sign first, the Principal co-approves." badge="VP Academic" role="vp_academic">
    <x-card title="Promotion batches">
        <div class="space-y-4">
            @forelse ($promotionBatches as $batch)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">{{ $batch->fromSection->name }} — {{ $batch->items->count() }} students</p>
                        <p class="text-xs text-neutral-500">Prepared by {{ $batch->preparedBy->user->name ?? '—' }}</p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('vp_academic.approvals.promotions.approve', $batch) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('vp_academic.approvals.promotions.reject', $batch) }}">
                            @csrf
                            <button type="submit" class="border border-red-300 text-red-700 font-semibold rounded-lg px-4 py-2 text-sm">Return</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No promotion batches awaiting your review.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Transcript releases">
        <div class="space-y-4">
            @forelse ($transcripts as $doc)
                <div class="flex items-center justify-between border-b border-neutral-100 last:border-0 pb-4">
                    <div>
                        <p class="font-semibold text-sm">{{ $doc->student->name }}</p>
                        <p class="text-xs text-neutral-500">Prepared by {{ $doc->preparedBy->user->name ?? '—' }}</p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('vp_academic.approvals.transcripts.approve', $doc) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('vp_academic.approvals.transcripts.reject', $doc) }}">
                            @csrf
                            <button type="submit" class="border border-red-300 text-red-700 font-semibold rounded-lg px-4 py-2 text-sm">Return</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No transcripts awaiting your review.</p>
            @endforelse
        </div>
    </x-card>

    <x-card title="Post-lock grade changes" subtitle="Requested by teachers for locked terms — your approval is the first key; the Principal applies the second.">
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
                            Requested by {{ $req->requestedBy->user->name ?? '—' }} · Reason: {{ $req->reason }}
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('vp_academic.approvals.grade-changes.approve', $req) }}">
                            @csrf
                            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-4 py-2 text-sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('vp_academic.approvals.grade-changes.reject', $req) }}">
                            @csrf
                            <button type="submit" class="border border-red-300 text-red-700 font-semibold rounded-lg px-4 py-2 text-sm">Reject</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-neutral-400">No grade-change requests awaiting your review.</p>
            @endforelse
        </div>
    </x-card>
</x-app-layout>
