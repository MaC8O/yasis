{{-- One import's actions: publish or unpublish, revert, download. $batch. --}}
<div class="flex flex-wrap {{ $align ?? 'justify-end' }} gap-x-4 gap-y-1">
    @unless ($batch->isReverted())
        <form method="POST" action="{{ route($batch->isPublished() ? 'treasurer.ledger.unpublish' : 'treasurer.ledger.publish', $batch) }}">
            @csrf
            <button type="submit" class="inline-flex items-center min-h-[36px] text-xs font-semibold text-brand hover:underline">{{ $batch->isPublished() ? 'Unpublish' : 'Publish' }}</button>
        </form>
        <form method="POST" action="{{ route('treasurer.ledger.revert', $batch) }}" onsubmit="return confirm('Revert import #{{ $batch->id }}? Its new lines are removed and the lines it changed get their previous values back.')">
            @csrf
            <button type="submit" class="inline-flex items-center min-h-[36px] text-xs font-semibold text-danger hover:underline">Revert</button>
        </form>
    @endunless
    @if ($batch->stored_path)
        <a href="{{ route('treasurer.ledger.download', $batch) }}" class="inline-flex items-center min-h-[36px] text-xs font-semibold text-brand hover:underline">Download file</a>
    @endif
</div>
