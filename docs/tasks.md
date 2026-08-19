# خطة المهام (Tasks) — تنفيذ MVP نظام إدارة وحسابات الورشة

مرجع: `furniture_workshop_mvp_implementation_plan(1).md`

## قرارات تم اعتمادها

- قاعدة البيانات: **SQLite** (كما هي معدة حاليًا في `.env`، `DB_CONNECTION=sqlite`).
- Auth Starter Kit: **Laravel Breeze (Blade + Tailwind)**. لا حاجة لصفحة تسجيل (register) — يوجد Admin واحد فقط يُنشأ عبر Seeder، وتُحذف/تُعطَّل مسارات `register`.
- لغة الواجهة: **عربي (RTL)**.
- العملة: **جنيه مصري (EGP)** — تنسيق عرض فقط، بدون تحويل عملات.

قواعد عامة على كل Task:

- Task واحد فقط ينفَّذ وتُختبر نتيجته قبل الانتقال للي بعده.
- ممنوع تعديل ملفات/موديولات خارج نطاق الـTask إلا إذا كان ضروريًا ومبررًا (ويُذكر السبب).
- كل Task ينتهي بتشغيل `php artisan test` (Pest) و `vendor/bin/pint --dirty --format agent`.
- أي قرار غير واضح في الـlogic يُكتب في `docs/*.md` بدل تخمينه في الكود.

---

## Task 0 — تجهيز بيئة العمل (تكملة Phase 0)

**الحالة الحالية:** المشروع Laravel 13 فارغ، SQLite migrated (users/cache/jobs فقط)، Tailwind v4 مثبت، لا يوجد Auth.

**المطلوب:**

1. تثبيت Laravel Breeze (Blade stack).
2. إزالة/تعطيل مسار وصفحة التسجيل (register) — الدخول فقط عبر Login.
3. إنشاء Seeder لإداري واحد (Admin) بإيميل وباسورد من `.env` أو قيم seed ثابتة تُذكر للمستخدم.
4. حماية كل صفحات النظام بـ middleware `auth` (باستثناء `/login`).
5. ضبط اتجاه الصفحة RTL (`dir="rtl"` + `lang="ar"`) في الـlayout الأساسي لـ Breeze.
6. تعديل `/` بحيث يوجّه لـ `/login` أو `/dashboard` حسب حالة تسجيل الدخول.

**Acceptance Criteria:**

- `php artisan migrate:fresh --seed` يعمل بدون أخطاء وينشئ الـAdmin.
- زيارة أي صفحة نظام وأنت غير مسجل → تحويل لصفحة Login.
- تسجيل دخول بالـAdmin يعمل، تسجيل خروج يعمل.
- لا يوجد مسار `/register` متاح.
- الصفحات تظهر RTL بشكل صحيح.

**Test Scenarios:**

1. زيارة `/dashboard` بدون تسجيل دخول → redirect لـ `/login`.
2. تسجيل دخول ببيانات خاطئة → رسالة خطأ validation.
3. تسجيل دخول صحيح → دخول ناجح لصفحة رئيسية.
4. تسجيل خروج → رجوع لصفحة `/login` وعدم القدرة على الرجوع بالـback button لصفحة محمية.

---

## Task 1 — توثيق الـBusiness Logic (Phase 1)

**المطلوب:** إنشاء ملفات التوثيق التالية في `docs/` قبل أي كود إضافي:

```text
docs/
├── system-overview.md
├── business-rules.md
├── customers-and-rooms.md
├── inventory.md
├── inventory-costing.md
├── customer-payments.md
├── expenses.md
├── cashbox.md
├── partners.md
├── profit-calculation.md
└── mvp-scope.md
```

كل ملف يوثّق: التعريفات، الحقول المطلوبة، القواعد (مثال: هل يمكن صرف كمية أكبر من المتاح؟ هل يمكن حذف غرفة بعد صرف خامات فيها؟ هل الدفعة يمكن أن تتجاوز المتبقي؟)، وحالات الحافة (edge cases).

**Acceptance Criteria:** كل قرار غير محسوم في الخطة الأصلية (مثال: طريقة تقريب الأرقام، هل الصرف الجزئي مسموح، هل يمكن تعديل غرفة بعد الدفع) يكون له إجابة صريحة موثقة قبل البدء في Task 2.

**ملاحظة:** هذا الـTask نص/توثيق فقط، بدون كود.

---

## Task 2 — تأسيس المخزون (Phase 3): Categories + Materials

**Scope:** الجداول الأساسية فقط، بدون صرف بعد.

**DB:**

- `categories` (id, name, timestamps)
- `materials` (id, category_id, name, unit [قطعة/متر/لوح...], timestamps)

**Backend:** Model + Migration + Controller (Resource) + Form Requests (validation) لكل من Category وMaterial.

**UI:** صفحات بسيطة: `/inventory/materials` (index + create + edit).

**Acceptance Criteria:**

- إضافة صنف وتصنيف يعملان مع validation (name required, unique عند الحاجة).
- عرض قائمة المواد مع تصنيفها.

**Test Scenarios:**

1. إضافة تصنيف "أخشاب" ثم مادة "لوح MDF" مرتبطة به → تظهر في القائمة.
2. محاولة إضافة مادة بدون اسم → رسالة validation.

---

## Task 3 — Batches والمخزون (Phase 3 تكملة)

**DB:**

- `inventory_batches` (id, material_id, quantity, remaining_quantity, unit_cost, purchase_date, timestamps)
- `inventory_movements` (id, material_id, batch_id, type [in/out], quantity, related_type/related_id [polymorphic لاحقًا لربطها بالغرفة], timestamps)

**Backend:**

- عند إضافة كمية شراء جديدة → إنشاء Batch جديد (لا يُدمج مع batch قديم بسعر مختلف).
- حساب الكمية الحالية للمادة = مجموع `remaining_quantity` لكل الـbatches الخاصة بها.
- Service class مسؤول عن الصرف بمنطق FIFO على الـbatches (`InventoryService` أو مشابه).

**UI:** `/inventory/purchases` (تسجيل عملية شراء تنشئ Batch)، وعرض الكمية الحالية في `/inventory/materials`.

**Acceptance Criteria + Test Scenario (مطابق للخطة الأصلية):**

1. إضافة 3 ألواح بسعر 100 → Batch A.
2. إضافة 10 ألواح بسعر 120 → Batch B.
3. الكمية الكلية = 13.
4. صرف 5 (عبر tinker/test مباشر مؤقتًا لحين وجود الغرف في Task 4) → 3 من Batch A (تصبح 0) + 2 من Batch B (تصبح 8)، تكلفة الصرف = 540.
5. لا تُحذف بيانات الـBatch بعد نفاذها (remaining_quantity = 0 لكنها تبقى موجودة للتتبع).

---

## Task 4 — العملاء والغرف (Phase 4)

**DB:**

- `customers` (id, name, phone, address?, timestamps)
- `rooms` (id, customer_id, room_type, sale_price, status, timestamps)
- `room_materials` (id, room_id, material_id, required_quantity, issued_quantity, cost, timestamps)

**Backend:**

- إنشاء عميل، إنشاء غرفة مرتبطة به.
- إضافة احتياجات المادة للغرفة (`room_materials`) بدون صرف فوري.
- عملية "صرف" منفصلة تستدعي `InventoryService` من Task 3 وتُسجّل `inventory_movements` مرتبطة بالغرفة، وتحسب `cost` الفعلية حسب الـbatches المستخدمة.
- حساب `materials_cost` الإجمالي للغرفة = مجموع تكاليف المواد المصروفة.

**UI:** `/customers`, `/customers/create`, `/customers/{customer}` (تعرض غرفه)، `/rooms/create`, `/rooms/{room}` (تعرض المواد المطلوبة/المصروفة والتكلفة).

**Acceptance Criteria:**

- إنشاء عميل "أحمد" ثم غرفة "غرفة نوم" له.
- إضافة احتياج 5 ألواح، ثم صرف → نقص المخزون فعليًا (باستخدام سيناريو Task 3)، وتُحسب تكلفة الغرفة = 540 بنفس المثال.
- محاولة صرف كمية أكبر من المتاح في المخزون → خطأ واضح، بدون صرف جزئي غير متوقع (القرار يُوثَّق في Task 1 / `inventory.md`).

**Test Scenarios:** نفس سيناريو Task 3 لكن من خلال واجهة/منطق الغرفة الفعلي بدلاً من استدعاء مباشر.

---

## Task 5 — مدفوعات العملاء (Phase 5)

**DB:**

- `customer_payments` (id, room_id, amount, paid_at, timestamps)
- `cashbox_transactions` (id, type [in/out], amount, source_type, source_id [polymorphic]، description, timestamps) — الجدول الأساسي للخزنة، يُنشأ في هذا الـTask ويُستخدم من باقي الموديولات.

**Backend:**

- إضافة دفعة لغرفة → تحديث `paid_amount` (محسوب أو مخزّن) و`remaining` = `sale_price - paid_amount`.
- كل دفعة تُنشئ تلقائيًا `cashbox_transaction` من نوع `in` بنفس القيمة (لا إدخال يدوي منفصل للخزنة).
- منع الدفع بقيمة تجعل المدفوع أكبر من سعر الغرفة (تُوثَّق القاعدة في `customer-payments.md`).

**UI:** `/payments` (أو صفحة دفعة مرتبطة بالغرفة من `/rooms/{room}`).

**Acceptance Criteria + Test Scenario:**

- سعر غرفة = 30,000، دفعة = 10,000 → `paid = 10,000`, `remaining = 20,000`, وتظهر حركة خزنة `+10,000` مرتبطة بنفس الدفعة.

---

## Task 6 — المصروفات الإدارية (Phase 6)

**DB:**

- `expense_categories` (id, name, timestamps)
- `expenses` (id, expense_category_id, amount, date, description?, timestamps)

**Backend:** إضافة مصروف → إنشاء `cashbox_transaction` نوع `out` تلقائيًا (نفس آلية Task 5، بدون تكرار منطق الخزنة).

**UI:** `/expenses`, `/expenses/create`.

**Test Scenario:** مصروف كهرباء 2,000 → `Expense = 2,000` و `cashbox transaction = -2,000`.

---

## Task 7 — صفحة الخزنة (Phase 7)

**Scope:** لا جداول جديدة — `cashbox_transactions` موجود من Task 5. هذا الـTask للعرض والتجميع فقط.

**Backend:** حساب: إجمالي الداخل، إجمالي الخارج، الرصيد الحالي = مجموع كل الحركات (in - out).

**UI:** `/cashbox` — جدول الحركات (مرتبة بالتاريخ) + ملخص علوي (رصيد حالي، إجمالي دخل، إجمالي خرج).

**Acceptance Criteria:**

- الرصيد = مجموع كل حركات الدفعات (+) والمشتريات (-) والمصروفات (-) المسجلة من Tasks 3, 5, 6.
- لا يوجد أي إدخال يدوي لحركة خزنة من هذه الصفحة (read-only على الحركات نفسها).

**Test Scenario:** تنفيذ سيناريوهات Task 3+5+6 معًا والتأكد أن الرصيد النهائي = مجموعها الجبري الصحيح.

**ملاحظة:** عند تنفيذ Task 3 (شراء المخزون) لازم يُضاف هنا أيضًا: إنشاء `cashbox_transaction` نوع `out` عند كل عملية شراء مخزون (كانت مؤجلة في Task 3 لعدم وجود جدول الخزنة وقتها) — إما بالرجوع لـTask 3 وإضافتها الآن، أو تنفيذها كجزء من هذا الـTask مع إعادة اختبار Task 3.

---

## Task 8 — الشركاء (Phase 8)

**DB:**

- `partners` (id, name, percentage, timestamps)
- `partner_withdrawals` (id, partner_id, amount, date, timestamps)

**Backend:**

- حساب نصيب كل شريك = `net_profit * percentage / 100` (net_profit من Task 9، لذا هذا الـTask يعتمد جزئيًا على المنطق الحسابي فيه — يمكن تنفيذه بحساب مبسط هنا ثم ربطه الكامل بعد Task 9).
- تسجيل سحب شريك → `cashbox_transaction` نوع `out` تلقائيًا.
- حساب `remaining = share - total_withdrawn`.

**UI:** `/partners`, `/partners/{partner}`.

**Test Scenario:** Net Profit = 25,000، شريك بنسبة 20% → Share = 5,000. سحب 2,000 → Remaining = 3,000، وتُسجَّل -2,000 في الخزنة.

**قاعدة:** مجموع نسب الشركاء لا يتجاوز 100% (تُوثَّق في `partners.md`، وتُطبَّق كـvalidation).

---

## Task 9 — حساب الربح (Phase 9)

**Scope:** لا جداول جديدة — هذا الـTask حسابي بحت (Service/aggregation) يجمع بيانات من الموديولات السابقة.

**Backend:** `ProfitService` أو مشابه يحسب:

```text
Revenue = مجموع sale_price لكل الغرف (أو مجموع المدفوعات الفعلية — يُحسم القرار في docs/profit-calculation.md)
- Cost of used materials = مجموع room_materials.cost للمواد المصروفة فعليًا فقط
- Administrative expenses = مجموع expenses
= Net Profit
```

**UI:** صفحة ملخص بسيطة (يمكن أن تكون جزء من `/dashboard` أو صفحة منفصلة) تعرض الأرقام الثلاثة والنتيجة.

**Acceptance Criteria:**

- الفرق واضح بين قيمة شراء الخامة والمصروف الفعلي في تكلفة الإنتاج (خامة مُشتراة ولم تُصرف بعد لا تدخل في Cost of used materials).
- الربح لا يتأثر برصيد الخزنة مباشرة (توضيح الفرق بين Cash balance و Net Profit في التوثيق).

---

## Task 10 — تجميع الشركاء بالربح الفعلي + مراجعة نهائية (ربط Task 8 و Task 9)

**المطلوب:** ربط حساب نصيب الشركاء في Task 8 بـ`ProfitService` الحقيقي من Task 9 بدل الرقم المبسط، وإعادة اختبار Task 8 بالكامل.

---

## Task 11 — تجانس الواجهة (Phase 10)

**المطلوب:**

- مراجعة كل الصفحات المنفذة (Tasks 0–9): تنسيق موحد بـTailwind، رسائل نجاح/خطأ موحدة، Navigation بسيط يربط كل الصفحات (Customers, Inventory, Payments, Expenses, Cashbox, Partners).
- التأكد أن كل validation errors تظهر بشكل واضح بالعربي.
- لا تصميم جديد أو صفحات جديدة — فقط تجانس وربط الموجود.

**Acceptance Criteria:** إمكانية التنقل بين كل أجزاء النظام من قائمة تنقل واحدة، بدون الحاجة لكتابة URL يدويًا.

---

## Task 12 — اختبار الدورة الكاملة (Integration Test — Section 19)

**المطلوب:** Pest Feature Test واحد شامل (أو سلسلة تعمل بالتتابع) يغطي الدورة كاملة:

```text
شراء خامات → المخزون → إنشاء غرفة → صرف خامات → تكلفة الغرفة
→ دفع العميل → الخزنة → مصروف إداري → الخزنة
→ حساب الربح → نصيب الشركاء → سحب شريك → الخزنة + حساب الشريك
```

**Acceptance Criteria:** كل الأرقام النهائية (تكلفة، خزنة، ربح، أنصبة) صحيحة رياضيًا ومطابقة لتوقعات السيناريو المكتوب يدويًا قبل التنفيذ. هذا الـTest هو معيار اعتبار الـMVP جاهزًا (Section 24 من الخطة).

---

## ترتيب التنفيذ

Task 0 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12

لا يبدأ Task جديد قبل نجاح كل Acceptance Criteria وTest Scenarios الخاصة بالـTask الذي قبله، وإعادة تشغيل اختبارات المراحل السابقة للتأكد من عدم وجود regression (Section 20 من الخطة).
