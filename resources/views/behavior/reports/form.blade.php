<x-app-layout :title="$report ? 'Edit behavior report' : 'Report incident'" :subtitle="$student->name.' · '.$student->student_id_number" badge="Teacher">
    <x-card :subtitle="$report
        ? 'Saving keeps the current version: the student and guardians will see that the report was edited, with the date, and can read the earlier version.'
        : 'The student and their guardians can see the report as soon as you submit it, marked “Awaiting review”. Only the Vice Principal can deduct points.'">
        <form method="POST" action="{{ $report ? route('behavior.reports.update', $report) : route('behavior.reports.store', $student) }}"
              class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            @if ($report) @method('PUT') @endif
            @include('behavior.partials.report-fields', ['report' => $report])
            <div class="sm:col-span-2 flex flex-col-reverse sm:flex-row gap-3 sm:items-center">
                <a href="{{ $report ? route('behavior.reports.show', $report) : route('behavior.students.show', $student) }}" class="text-center text-sm font-semibold text-neutral-500 py-2">Cancel</a>
                <button type="submit" class="w-full sm:w-auto bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">{{ $report ? 'Save changes' : 'Submit report' }}</button>
            </div>
        </form>
    </x-card>
</x-app-layout>
