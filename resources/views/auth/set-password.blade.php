<x-layouts.guest title="Set your password — ISMS">
    <h1 class="text-2xl font-bold text-neutral-900">Set your password</h1>
    <p class="text-sm text-neutral-500 mt-1 mb-6">
        For your security an administrator asked you to choose a new password before continuing.
        You can’t reach the rest of the system until this is done.
    </p>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.set.update') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-sm font-semibold text-neutral-900 mb-1">New password</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
            <p class="text-xs text-neutral-500 mt-1">At least 8 characters, using both letters and digits.</p>
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-neutral-900 mb-1">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
        </div>
        <button type="submit"
            class="w-full bg-[#C9A227] hover:bg-[#b8942a] text-neutral-900 font-bold rounded-lg py-3 text-sm transition">
            Save password &amp; continue
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-neutral-500 hover:text-neutral-800 underline">
            Sign out instead
        </button>
    </form>
</x-layouts.guest>
