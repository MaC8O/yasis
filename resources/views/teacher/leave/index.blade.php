<x-app-layout title="Leave Request" subtitle="Submit a leave request and track its approval status." badge="Teacher" role="teacher">
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach ($leaveTypes as $type)
                @php $balance = $balances[$type->id]; @endphp
                <div class="rounded-2xl border border-neutral-200 px-5 py-4 {{ $type->name === 'Annual' ? 'bg-[#D7ECD9]' : ($type->name === 'Sick' ? 'bg-[#DCE7F9]' : 'bg-neutral-50') }}">
                    <p class="font-semibold text-sm">{{ $type->name }} leave</p>
                    @if ($type->name === 'Unpaid')
                        <p class="text-xs text-neutral-500 mt-1">No cap</p>
                    @else
                        <p class="text-xs text-neutral-500 mt-1">{{ $balance->allocated - $balance->used - $balance->pending }} remaining
                            @if ($balance->pending > 0) · {{ $balance->pending }} reserved @endif
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card title="New leave request" subtitle="Requests route to HR. Pending days are reserved against your balance until decided.">
            <form method="POST" action="{{ route('teacher.leave.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1">LEAVE TYPE</label>
                    <select name="leave_type_id" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} Leave</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-neutral-500 mb-1">FROM</label>
                        <input type="date" name="from_date" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-neutral-500 mb-1">TO</label>
                        <input type="date" name="to_date" required class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-500 mb-1">REASON</label>
                    <textarea name="reason" rows="3" placeholder="Briefly explain the reason for leave..." class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm"></textarea>
                </div>
                <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-6 py-3 text-sm">Submit request</button>
                <span class="text-xs text-neutral-400 ml-2">Class coverage is arranged by the Registrar.</span>
            </form>
        </x-card>

        <x-card title="My requests" subtitle="Status of your submitted requests.">
            <div class="space-y-4">
                @forelse ($requests as $req)
                    <div x-data="{ editing: false }">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm">{{ $req->leaveType->name }} Leave</p>
                                <p class="text-xs text-neutral-500">{{ $req->from_date->format('d M') }} – {{ $req->to_date->format('d M') }} · {{ $req->days }} day(s) · applied {{ $req->created_at->format('d M') }}</p>
                            </div>
                            <x-badge :color="$req->status === 'Approved' ? 'green' : ($req->status === 'Rejected' ? 'pink' : ($req->status === 'Cancelled' ? 'blue' : 'yellow'))">
                                {{ $req->status }}
                            </x-badge>
                        </div>
                        @if ($req->status === 'Pending')
                            <div class="flex gap-3 mt-2">
                                <button type="button" @click="editing = !editing" class="text-xs font-semibold text-neutral-700 border border-neutral-300 rounded-lg px-3 py-1.5">Edit</button>
                                <form method="POST" action="{{ route('teacher.leave.cancel', $req) }}" onsubmit="return confirm('Cancel this leave request?');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-red-700 border border-red-300 rounded-lg px-3 py-1.5">Cancel</button>
                                </form>
                            </div>
                            <form x-show="editing" method="POST" action="{{ route('teacher.leave.update', $req) }}" class="grid grid-cols-3 gap-2 mt-3">
                                @csrf @method('PUT')
                                <input type="date" name="from_date" value="{{ $req->from_date->toDateString() }}" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                <input type="date" name="to_date" value="{{ $req->to_date->toDateString() }}" class="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1.5 text-xs">
                                <button type="submit" class="text-xs font-semibold bg-[#1F573D] text-white rounded-lg px-3 py-1.5">Save</button>
                            </form>
                        @else
                            <p class="text-xs text-neutral-400 mt-1">Locked — already decided.</p>
                        @endif
                        <hr class="mt-4 border-neutral-100">
                    </div>
                @empty
                    <p class="text-sm text-neutral-400">No leave requests submitted yet.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
