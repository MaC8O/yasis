<x-app-layout title="System Settings" subtitle="SMTP/notification configuration and calendar events." badge="Admin" role="admin">
    <x-card>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">SMTP host</label>
                    <input type="text" name="smtp_host" value="{{ $settings['smtp_host'] }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">SMTP port</label>
                    <input type="text" name="smtp_port" value="{{ $settings['smtp_port'] }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">SMTP username</label>
                    <input type="text" name="smtp_username" value="{{ $settings['smtp_username'] }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">From address</label>
                    <input type="email" name="smtp_from_address" value="{{ $settings['smtp_from_address'] }}"
                        class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="notifications_enabled" value="1" @checked($settings['notifications_enabled'] === '1')>
                Email notifications enabled (absence, leave, and account alerts)
            </label>

            <div>
                <label class="block text-sm font-semibold mb-1">Calendar note (shown on dashboards)</label>
                <textarea name="calendar_note" rows="3" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">{{ $settings['calendar_note'] }}</textarea>
            </div>

            <button type="submit" class="bg-[#1F573D] text-white font-semibold rounded-lg px-5 py-2.5 text-sm">Save settings</button>
        </form>
    </x-card>
</x-app-layout>
