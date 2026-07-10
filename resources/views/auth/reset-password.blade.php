<x-layouts.guest title="Set new password — ISMS">
    <h1 class="text-2xl font-bold text-neutral-900">Set a new password</h1>
    <p class="text-sm text-neutral-500 mt-1 mb-6">Choose a new password for your account.</p>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="block text-sm font-semibold text-neutral-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold text-neutral-900 mb-1">New password</label>
            <input id="password" type="password" name="password" required
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-neutral-900 mb-1">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
        </div>
        <button type="submit"
            class="w-full bg-[#C9A227] hover:bg-[#b8942a] text-neutral-900 font-bold rounded-lg py-3 text-sm transition">
            Update password
        </button>
    </form>
</x-layouts.guest>
