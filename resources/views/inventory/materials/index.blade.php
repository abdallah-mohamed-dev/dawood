<x-app-layout title="المواد">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">المواد</h1>
        <a href="{{ route('inventory.materials.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-bg-subtle">
                <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">التصنيف</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">اسم المادة</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">وحدة القياس</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الكمية الحالية</th>
                    <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border [&>tr:hover]:bg-bg-subtle">
                @forelse ($materials as $material)
                    <tr>
                        <td class="px-4 py-2">{{ $material->category->name }}</td>
                        <td class="px-4 py-2">{{ $material->name }}</td>
                        <td class="px-4 py-2">{{ $material->unit }}</td>
                        <td class="px-4 py-2">
                            <x-quantity :amount="(int) ($stockByMaterial[$material->id] ?? 0)" :unit="$material->unit" />
                        </td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('inventory.materials.edit', $material) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('inventory.materials.destroy', $material) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
