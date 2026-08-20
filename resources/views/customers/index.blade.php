<x-app-layout title="العملاء">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">العملاء</h1>
        <a href="{{ route('customers.create') }}" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2">
            {{ __('Add') }}
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-sm">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-bg-subtle">
                <tr>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">الاسم</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">رقم الهاتف</th>
                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wide text-secondary">عدد الغرف</th>
                    <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wide text-secondary">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border [&>tr:hover]:bg-bg-subtle">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('customers.show', $customer) }}" class="text-primary hover:underline">{{ $customer->name }}</a>
                        </td>
                        <td class="px-4 py-2">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $customer->rooms_count }}</td>
                        <td class="px-4 py-2 text-end">
                            <a href="{{ route('customers.edit', $customer) }}" class="text-primary hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ms-3 text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-secondary">{{ __('No results found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-app-layout>
