<x-layouts.guest title="Sign in — ISMS">
    <div class="flex items-center gap-3 mb-6">
        <div class="bg-[#C9A227] text-neutral-900 font-bold rounded-lg px-3 py-2 text-sm tracking-wide">ISMS</div>
        <div>
            <p class="font-bold text-neutral-900 leading-tight">Yangon Adventist Seminary</p>
            <p class="text-sm text-neutral-500 leading-tight">Integrated School Management System</p>
        </div>
    </div>

    <h1 class="text-2xl font-bold text-neutral-900">Sign in to your account</h1>
    <p class="text-sm text-neutral-500 mt-1 mb-6">Use the email and password issued by the Admin office.</p>

    @if (session('status'))
        <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ showPassword: false }">
        @csrf
        <div>
            <label for="email" class="block text-sm font-semibold text-neutral-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                placeholder="you@yasis.edu"
                class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold text-neutral-900 mb-1">Password</label>
            <div class="relative">
                <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 pr-16 text-sm focus:outline-none focus:ring-2 focus:ring-[#C9A227]">
                <button type="button" @click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-neutral-500 hover:text-neutral-800">
                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                </button>
            </div>
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-neutral-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-neutral-300">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-neutral-500 hover:text-neutral-800 underline">Forgot your password?</a>
        </div>
        <button type="submit"
            class="w-full bg-[#C9A227] hover:bg-[#b8942a] text-neutral-900 font-bold rounded-lg py-3 text-sm transition">
            Sign in
        </button>
    </form>

    <p class="flex items-center justify-center gap-1.5 text-xs text-neutral-400 mt-6">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
        </svg>
        Secured connection · sign-in attempts are monitored and limited
    </p>
</x-layouts.guest>
