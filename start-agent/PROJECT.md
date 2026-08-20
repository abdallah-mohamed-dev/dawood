# نظرة عامة على المشروع

## إيه هو التطبيق دا

نظام إدارة وحسابات داخلي (MVP) لورشة أثاث واحدة، بأدمن واحد فقط. بيغطي:

- عملاء وغرف (مشاريع/طلبات) لكل عميل
- تصنيفات ومواد خام + مخزون بنظام FIFO للتكلفة
- صرف خامات للغرف وربطها بتكلفتها الفعلية
- دفعات العملاء والمصروفات الإدارية
- خزنة مركزية (كل حركة مالية بتعدي منها)
- حساب ربح دقيق (أساس استحقاق، منفصل تمامًا عن رصيد الخزنة النقدي)
- (لسه هيتضاف) شركاء ونسب أرباح وسحوبات

المرجع الكامل والمعتمد نهائيًا: [`../docs/tasks.md`](../docs/tasks.md). أي قرار موجود هناك **لا يُعاد فتحه أو مناقشته** إلا لو المستخدم نفسه طلب كدا صراحة.

## التقنيات

| البند | القيمة |
|---|---|
| Backend | Laravel 13، PHP 8.4 |
| قاعدة البيانات | SQLite (`DB_CONNECTION=sqlite`) |
| Auth | يدوي (Controller + middleware + throttle) — **بدون Breeze** |
| CSS | Tailwind v4 (`@tailwindcss/vite`, CSS-first config عبر `@theme`) |
| الاختبارات | Pest 5 |
| اللغة | عربي RTL بالكامل (`APP_LOCALE=ar`) |
| العملة | جنيه مصري (عرض فقط) |
| التوقيت | `Africa/Cairo` |
| الخط | Cairo (عبر Bunny Fonts) |

## القرارات المعمارية الحرجة (لازم تُفهم قبل أي تعديل)

### 1. كل الأرقام أعداد صحيحة مقيّسة — لا `float` أبدًا

SQLite مفيهوش نوع `DECIMAL` حقيقي — أي كسر عشري بيتخزن كـ`REAL` عائم وبيسبب أخطاء تراكمية (زي `539.9999999999999` بدل `540`). الحل:

- **المبالغ المالية**: `bigInteger` بالقروش (×100). مثال: 540.00 ج.م → `54000`.
- **الكميات**: `bigInteger` بأجزاء الألف (×1000). مثال: 2.5 متر → `2500`.
- التحويل عبر `App\Casts\MoneyCast` و `App\Casts\QuantityCast` (يرثوا من `App\Casts\Concerns\ScaledIntegerCast`).
- **قاعدة التقريب**: round half up، في دالة واحدة مشتركة (`InventoryService::cost()` الخاصة، و`ScaledIntegerCast::toScaledInt()`).
- **⚠️ تنبيه ضرب بين نطاقين مختلفين**: الكمية ×1000 والمبلغ ×100 — أي ضرب بينهما (تكلفة FIFO مثلاً) لازم يُقسَّم على 1000:
  ```text
  التكلفة (بالقروش) = round( (الكمية_المخزّنة × سعر_الوحدة_بالقروش) / 1000 )
  ```
- العرض في الواجهة عبر `<x-money :amount="..." />` و `<x-quantity :amount="..." :unit="..." />` فقط — لا تكتب تنسيق أرقام يدوي في أي Blade file.
- **فرق مهم بين المكونين**: `<x-money>` دايمًا بيعرض رقمين عشريين ثابتين (`20,000.00 ج.م`). `<x-quantity>` بيشيل الأصفار الزيادة بعد العلامة العشرية (`3.000` → `3`، `2.500` تفضل `2.5`). القرار دا اتاخد صراحة من المستخدم — لا تغيّره بدون طلب صريح.

### 2. القواعد المحاسبية

```text
Revenue          = Σ sale_price للغرف "completed" فقط
Net Profit       = Revenue − Σ(تكلفة خامات الغرف المكتملة) − Σ(كل المصروفات الإدارية)
Work In Progress = تكلفة خامات مصروفة لغرف draft/in_progress (أصل، مش تكلفة بعد)
Stock Value      = كمية متبقية × سعر الوحدة لكل دفعات المخزون غير المصروفة (أصل)
```

**رصيد الخزنة ≠ صافي الربح.** الخزنة أساس نقدي (كل جنيه دخل/خرج فعليًا)، الربح أساس استحقاق (بس الغرف المكتملة). دفعات العميل **لا تؤثر إطلاقًا** على صافي الربح. التفاصيل الكاملة: [`../docs/profit-calculation.md`](../docs/profit-calculation.md).

### 3. طبقة Services — Controllers خفيفة فقط

كل منطق الأعمال في `app/Services/`، الـControllers بس بتستدعيها وتتعامل مع الأخطاء:

- `CashboxService` — الكاتب الوحيد لجدول `cashbox_transactions`. الربط بأي عملية عبر polymorphic (`source_type`/`source_id`).
- `InventoryService` — الشراء، الصرف (FIFO)، الإرجاع، حذف عملية شراء، قيمة المخزون.
- `RoomMaterialService` — ربط احتياجات الغرفة بالصرف الفعلي عبر `InventoryService`.
- `RoomService` — حذف غرفة (مع قفل + إرجاع/استهلاك اختياري للخامات).
- `CustomerPaymentService` — دفعات العملاء (منع تجاوز المتبقي، منع الدفع لغرفة ملغاة).
- `ExpenseService` — المصروفات الإدارية.
- `ProfitService` — كل حسابات الربح (لا جداول جديدة، تجميع بحت).

### 4. التزامن والقفل

SQLite: `lockForUpdate()` لوحده مبيعملش حاجة إلا مع `transaction_mode = IMMEDIATE` (مضبوط في `config/database.php`). النمط المتكرر في كل الـServices: **أعد جلب النسخة الطازة بقفل داخل الـtransaction، لا تثق في النسخة اللي جاية من الكولر** — راجع `PITFALLS.md`.

### 5. الواجهة

- `resources/views/components/app-layout.blade.php` — الـlayout الأساسي (Sidebar غامق + محتوى فاتح، هوية "ورشة/خشب" مش SaaS أزرق عام).
- كل الألوان/الخطوط في `resources/css/app.css` عبر `@theme` — تغيير القيم هناك بينعكس على كل الصفحات فورًا بعد `npm run build`.
- Blade components مشتركة: `<x-money>`, `<x-quantity>`, `<x-status-badge>`.

## خريطة الملفات المهمة

```text
app/
  Casts/                    MoneyCast, QuantityCast, ScaledIntegerCast (المشترك)
  Enums/                    RoomStatus, CashboxTransactionType/Kind, InventoryMovementType
  Exceptions/               استثناءات مخصصة لكل قاعدة عمل (InsufficientStockException...)
  Http/Controllers/         Controllers رفيعة — تحويل مدخلات + استدعاء Service + رسائل عربية
  Http/Requests/            Form Requests للتحقق
  Models/                   Eloquent Models + العلاقات + دوال مشتقة (materialsCost, paidAmount...)
  Services/                 كل منطق الأعمال (راجع القسم أعلاه)

database/
  migrations/                جداول مع فهارس صريحة على كل FK (SQLite لا يفهرسها تلقائيًا)
  factories/                 Factory لكل Model

docs/
  tasks.md                   الخطة المعتمدة الكاملة (المرجع الأعلى)
  tasks-checklist.md          checklist تفصيلي لكل تاسك فرعي — المصدر الوحيد لمعرفة "احنا فين"
  *.md                        توثيق منطق كل جزء (inventory-costing, customer-payments, profit-calculation...)

resources/
  css/app.css                 Design Tokens (الألوان/الخط) — المصدر الوحيد المسموح
  views/                       Blade views، كل صفحة تستخدم <x-app-layout>

tests/
  Feature/                    اختبارات HTTP end-to-end لكل Controller
  Feature/Services/           اختبارات مباشرة لكل Service (أرقام مرجعية محسوبة يدويًا)
  Unit/Casts/                 اختبارات MoneyCast/QuantityCast

start-agent/                  هذا المجلد — نقطة دخول أي Agent جديد
```

## بيانات دخول التطوير المحلي

- URL: `http://localhost:8000` (عبر Herd أو `php artisan serve`)
- Email: `admin@dawood.test`
- Password: `password`
- (من `.env` / `.env.example` — بيئة تطوير محلية فقط، لا علاقة لها بالإنتاج)
