<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>أمر شراء — {{ $room->room_type }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="min-h-screen bg-bg p-8 font-sans text-gray-900 antialiased">
    <div class="mx-auto max-w-3xl rounded-xl border border-border bg-surface p-8 shadow-sm">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">أمر شراء</h1>
                <p class="mt-2 text-sm">الغرفة: {{ $room->room_type }}</p>
                <p class="text-sm">العميل: {{ $room->customer->name }}</p>
                <p class="text-sm">التاريخ: {{ now()->format('Y-m-d') }}</p>
            </div>
            <button type="button" onclick="window.print()" class="no-print rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark">طباعة</button>
        </div>

        @if ($lines->isEmpty())
            <p class="rounded-lg bg-bg-subtle p-4 text-sm text-secondary">مفيش خامات ناقصة للغرفة دي — كل احتياجاتها متوفرة في المخزن.</p>
        @else
            <table class="min-w-full divide-y divide-border border border-border text-sm">
                <thead class="bg-bg-subtle">
                    <tr>
                        <th class="px-3 py-2 text-start font-semibold">الخامة</th>
                        <th class="px-3 py-2 text-start font-semibold">الناقص</th>
                        <th class="px-3 py-2 text-start font-semibold">سعر الوحدة</th>
                        <th class="px-3 py-2 text-start font-semibold">التكلفة التقديرية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($lines as $line)
                        @php $material = $line['roomMaterial']->material; @endphp
                        <tr>
                            <td class="px-3 py-2">{{ $material->name }}</td>
                            <td class="px-3 py-2"><x-quantity :amount="$line['shortage']" :unit="$material->unit" /></td>
                            <td class="px-3 py-2"><x-money :amount="$material->getRawOriginal('unit_price')" /></td>
                            <td class="px-3 py-2"><x-money :amount="$line['cost']" /></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-bold">
                        <td colspan="3" class="px-3 py-2">الإجمالي</td>
                        <td class="px-3 py-2"><x-money :amount="$total" /></td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <div class="mt-16 flex justify-between text-sm">
            <div>توقيع المستلم: ........................</div>
            <div>توقيع المسؤول: ........................</div>
        </div>
    </div>
</body>
</html>
