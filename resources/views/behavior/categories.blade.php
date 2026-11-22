<x-app-layout title="Incident categories" subtitle="The categories teachers choose from when reporting an incident. Categories are never deleted: turn one off to stop it being offered; existing reports keep it." badge="VP">
    <x-card title="Add a category">
        <form method="POST" action="{{ route('behavior.categories.store') }}" class="grid grid-cols-1 sm:grid-cols-[1fr_2fr_auto] gap-3 sm:items-end">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1" for="new-name">Name</label>
                <input id="new-name" name="name" required maxlength="100" value="{{ old('name') }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1" for="new-desc">Description (optional)</label>
                <input id="new-desc" name="description" maxlength="255" value="{{ old('description') }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-sm">
            </div>
            <button type="submit" class="bg-brand text-white font-semibold rounded-lg px-5 py-2.5 text-sm hover:bg-brand-dark">Add</button>
        </form>
    </x-card>

    <x-card title="Categories">
        <ul class="divide-y divide-neutral-100">
            @foreach ($categories as $category)
                <li class="py-3">
                    <form method="POST" action="{{ route('behavior.categories.update', $category) }}" class="grid grid-cols-1 md:grid-cols-[1fr_2fr_6rem_auto_auto] gap-3 md:items-end">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="name-{{ $category->id }}">Name</label>
                            <input id="name-{{ $category->id }}" name="name" required maxlength="100" value="{{ $category->name }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm {{ $category->is_active ? '' : 'text-neutral-400' }}">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="desc-{{ $category->id }}">Description</label>
                            <input id="desc-{{ $category->id }}" name="description" maxlength="255" value="{{ $category->description }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-neutral-500 mb-1" for="order-{{ $category->id }}">Order</label>
                            <input id="order-{{ $category->id }}" name="sort_order" type="number" min="0" max="9999" value="{{ $category->sort_order }}" class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm">
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold min-h-11">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="w-5 h-5 rounded border-neutral-300" @checked($category->is_active)> In use
                        </label>
                        <button type="submit" class="bg-white border border-neutral-300 font-semibold rounded-lg px-4 py-2 text-sm hover:border-brand">Save</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </x-card>
</x-app-layout>
