<x-layouts.guest title="Forgot password — ISMS">
    <div class="flex items-center gap-3 mb-6">
        <span class="w-11 h-11 rounded-xl bg-gold text-ink font-extrabold text-xl flex items-center justify-center shrink-0" aria-hidden="true">Y</span>
        <div>
            <p class="font-bold text-neutral-900 leading-tight">Yangon Adventist Seminary</p>
            <p class="text-sm text-neutral-500 leading-tight">Integrated School Management System</p>
        </div>
    </div>

    <h1 class="text-2xl font-bold text-neutral-900">Reset your password</h1>
    <p class="text-sm text-neutral-500 mt-1 mb-6">Enter your email and we'll send you a reset link. If you can't access your email, ask the Admin office to re-send your login.</p>

    @if (session('status'))
        <div class="mb-4 text-sm text-success bg-success-soft border border-success-line rounded-lg px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-sm text-danger bg-danger-soft border border-danger-line rounded-lg px-3 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-semibold text-neutral-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
        </div>
        <button type="submit"
            class="w-full bg-gold hover:bg-gold-dark text-neutral-900 font-bold rounded-lg py-3 text-sm transition">
            Send reset link
        </button>
    </form>

    <p class="text-center mt-4">
        <a href="{{ route('login') }}" class="text-sm text-neutral-500 hover:text-neutral-800 underline">Back to sign in</a>
    </p>
</x-layouts.guest>
