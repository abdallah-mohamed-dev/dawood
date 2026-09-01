<x-app-layout title="النسخ الاحتياطي">
    <h1 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">النسخ الاحتياطي</h1>

    <div class="rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-gray-900">نسخة كاملة من قاعدة البيانات</h2>
        <p class="mb-3 text-sm text-secondary">تحميل نسخة كاملة من بيانات النظام كلها كملف واحد، تقدر تحتفظ بيه كنسخة احتياطية.</p>

        <a
            href="{{ route('backup.database') }}"
            class="inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
        >
            تحميل نسخة قاعدة البيانات
        </a>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-gray-900">نسخة CSV شاملة</h2>
        <p class="mb-3 text-sm text-secondary">ملف مضغوط فيه ملف CSV منفصل لكل جدول من جداول النظام، بيتفتح في Excel.</p>

        <a
            href="{{ route('backup.csv') }}"
            class="inline-block rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-primary-dark hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-2"
        >
            تحميل نسخة CSV شاملة
        </a>
    </div>
</x-app-layout>
