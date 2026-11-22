{{--
    Shown once, as the direct response to the Activate request (Admin or Registrar). The temporary
    password exists only in this response: it is not flashed, not in the session, not in the audit
    entry, and the response is sent with Cache-Control: no-store.
--}}
<x-app-layout title="Account activated" subtitle="Give the temporary password to the account holder." :badge="ucfirst($layoutRole)" :role="$layoutRole">
    <x-card class="max-w-xl">
        <div class="flex items-start gap-3">
            <span class="w-10 h-10 rounded-full bg-success/10 text-success flex items-center justify-center shrink-0">
                <x-icon name="key" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-ink">{{ $account->name }}'s account is active</h2>
                <p class="text-sm text-neutral-500 break-all">{{ $account->email }}</p>
            </div>
        </div>

        <div class="mt-5" x-data="{ copied: false }">
            <p id="temp-password-label" class="text-xs font-bold uppercase tracking-wider text-neutral-500">Temporary password</p>
            <div class="mt-1 flex flex-col sm:flex-row sm:items-center gap-2">
                <code id="temporary-password" aria-labelledby="temp-password-label"
                      class="block flex-1 rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2.5 font-mono text-lg tracking-wider text-ink break-all select-all">{{ $temporaryPassword }}</code>
                <button type="button"
                        @click="navigator.clipboard?.writeText(document.getElementById('temporary-password').textContent.trim()).then(() => copied = true)"
                        class="rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                    <span x-show="! copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>
        </div>

        <ul class="mt-5 space-y-2 text-sm text-neutral-700 list-disc pl-5">
            <li><span class="font-semibold">This is the only time it is shown.</span> It is not saved anywhere you can look it up again. If it is lost, use Reset password.</li>
            <li>Give it to {{ $account->name }} in person or by phone, not by e-mail.</li>
            <li>They must choose their own password the first time they sign in.</li>
        </ul>

        <div class="mt-6">
            <a href="{{ $backUrl }}" class="inline-block bg-neutral-900 text-white font-semibold rounded-lg px-4 py-2.5 text-sm hover:bg-neutral-700">Done</a>
        </div>
    </x-card>
</x-app-layout>
