# DAWOOD — نقطة الدخول الرسمية لأي موديل/Agent

> **الملف ده أول حاجة تتقرا في أي جلسة جديدة، مهما كان الموديل (Claude / GPT / Gemini / أي حاجة تانية).**
> لو انت موديل اتفتحت على المشروع ده دلوقتي: اقرا الملف ده كامل، وبعدين اتبع "ترتيب القراءة الإلزامي" تحت **قبل** ما تكتب أي سطر كود.

---

## 1. المشروع في سطرين

نظام إدارة وحسابات ورشة أثاث خشبي (Laravel 13 / PHP 8.4 / SQLite / Blade + Tailwind v4 / Pest 5)، عربي RTL بالكامل، بأدمن واحد فقط.
الدورة: عميل → غرفة → شراء خامات → صرف (FIFO) → تكلفة → دفعات العميل → مصروفات → خزنة → صافي ربح → نصيب الشركاء وسحوباتهم.

**الحالة الحالية:** الـMVP مكتمل بالكامل (Tasks 0–11). الاختبارات **223/223 ناجحة**، Pint نظيف. آخر commit وقت كتابة الملف: `83b885d`.

---

## 2. خريطة الملفات — مين بيقول إيه

### أ. ملفات التوجيه (اقراها، متعدلهاش إلا لو التاسك عنها)

| الملف | بيقول إيه | امتى تقراه |
|---|---|---|
| **`CLAUDE.md`** (ده) | الخريطة ونقطة الدخول | أول حاجة، كل جلسة |
| **`start-agent/README.md`** | ترتيب القراءة + دورة العمل الإلزامية لأي تاسك | كل جلسة، بعد ده مباشرة |
| **`start-agent/PROJECT.md`** | القرارات المعمارية الحرجة + خريطة الكود | كل جلسة |
| **`start-agent/PITFALLS.md`** | 12 غلطة حصلت فعلاً واتصلحت — **إلزامي** | كل جلسة، قبل أي كود |
| **`start-agent/AGENT_LOG.md`** | سجل كل جلسة سابقة (append-only) | آخر 3–4 إدخالات |
| **`AGENTS.md`** | قواعد Laravel/Pint/Pest التقنية (مولّد من Laravel Boost) | كل جلسة |

### ب. ملفات المرجعية (قواعد الأعمال — ارجعلها وقت الحاجة)

| الملف | بيقول إيه |
|---|---|
| `docs/tasks.md` | **الخطة المعتمدة للـMVP — مغلقة.** القسم أ = قرارات لا تُناقَش. القسم ب = قواعد تنفيذ. القسم ج = تفاصيل Tasks 0–11 |
| `docs/tasks-checklist.md` | **أرشيف** تتبع الـMVP (كله ✔ دلوقتي) — الشغل الجديد مش بيتكتب هنا |
| `docs/system-overview.md` | الكيانات والعلاقات ومخطط قاعدة البيانات |
| `docs/business-rules.md` | القواعد العامة الحاكمة (ذرّية، حذف/تعديل، FIFO، نقدي مقابل استحقاقي) |
| `docs/inventory.md` / `docs/inventory-costing.md` | المخزون وحساب التكلفة FIFO |
| `docs/customers-and-rooms.md` | العملاء والغرف وحالاتها وصرف الخامات |
| `docs/customer-payments.md` | دفعات العملاء |
| `docs/expenses.md` | المصروفات الإدارية |
| `docs/cashbox.md` | الخزنة والرصيد الافتتاحي |
| `docs/partners.md` | الشركاء والنسب والسحوبات |
| `docs/profit-calculation.md` | حساب الربح (بالمثال الرقمي) |
| `docs/mvp-scope.md` | إيه داخل النطاق وإيه مؤجَّل |

### ج. ملف المستخدم النهائي (له قاعدة خاصة — راجع القسم 5)

| الملف | بيقول إيه |
|---|---|
| **`USER-GUIDE.md`** | شرح النظام بلغة المستخدم النهائي (مش تقني) — **لازم يتحدّث مع أي تغيير في المنطق** |

### د. الشغل الجديد (من هنا ورايح)

| المسار | بيقول إيه |
|---|---|
| **`specs/`** | **كل تاسك كبير جديد = ملف واحد هنا** فيه المطلوب + الخطة + الـchecklist + المراجعة |
| `specs/README.md` | فهرس كل التاسكات وحالتها + شرح طريقة الشغل |
| `specs/_TEMPLATE.md` | القالب اللي بيتنسخ لكل تاسك جديد |

### هـ. ملفات متجاهَلة حاليًا (متقراهاش، متعدلهاش)

`SOP.md` و `SOP-UI.md` — بقرار من المستخدم، خارج الخدمة حاليًا. متعتمدش عليها في أي حاجة.

---

## 3. خريطة الكود — بعدّل فين

```text
app/
  Casts/              MoneyCast, QuantityCast, Concerns/ScaledIntegerCast
                      ← أي تحويل رقمي (فلوس/كميات) بيمر من هنا حصرًا
  Enums/              RoomStatus, CashboxTransactionType, CashboxTransactionKind,
                      InventoryMovementType
  Exceptions/         استثناء مخصص لكل قاعدة عمل (InsufficientStockException...)
  Http/Controllers/   ← Controllers رفيعة فقط: تحويل مدخلات + نداء Service + رسالة عربية
  Http/Requests/      ← كل الـvalidation هنا (Form Requests)
  Models/             14 موديل + العلاقات + دوال مشتقة (materialsCost, paidAmount...)
  Services/           ← كل منطق الأعمال هنا. 8 خدمات:
                      CashboxService ......... الكاتب الوحيد لجدول الخزنة
                      InventoryService ....... شراء / صرف FIFO / إرجاع / قيمة المخزون
                      RoomMaterialService .... احتياجات الغرفة والصرف الفعلي
                      RoomService ............ حذف غرفة (قفل + إرجاع/استهلاك)
                      CustomerPaymentService . دفعات العملاء
                      ExpenseService ......... المصروفات
                      PartnerService ......... النصيب والسحوبات
                      ProfitService .......... كل حسابات الربح (تجميع بحت، بلا جداول)

database/
  migrations/         ← فهرس صريح على كل FK (SQLite مبيعملهوش تلقائيًا)
  factories/          Factory لكل موديل
  seeders/            AdminUserSeeder (idempotent، من .env)

routes/web.php        ← كل المسارات، كلها تحت middleware('auth') عدا /login

resources/
  css/app.css         ← **المصدر الوحيد للألوان والخطوط** (@theme) — ممنوع أي hex في Blade
  views/              كل صفحة بتستخدم <x-app-layout>
  views/components/   app-layout, guest-layout, data-table, field, delete-button,
                      money, quantity, status-badge
                      ← استخدمهم بدل تكرار markup

lang/ar/, lang/ar.json  ← كل نص واجهة عربي

tests/
  Feature/            اختبارات HTTP لكل Controller
  Feature/Services/   اختبارات كل Service + FullCycleIntegrationTest (الدورة الكاملة)
  Unit/Casts/         اختبارات MoneyCast/QuantityCast

.claude/skills/, .agents/skills/   مهارات مساعدة (frontend-design, laravel-best-practices,
                                   pest-testing, tailwindcss-development, infer-conventions)
```

---

## 4. القواعد اللي مش بتتفاوض عليها

1. **ممنوع `float` في طبقة الأعمال.** المبالغ integer بالقروش (×100)، الكميات integer بأجزاء الألف (×1000). التحويل عبر `MoneyCast` / `QuantityCast` فقط.
2. **ضرب كمية × سعر لازم يتقسم على 1000** — الصيغة الوحيدة موجودة في `InventoryService::cost()`، متكتبش نسخة تانية.
3. **`CashboxService` هو الكاتب الوحيد** لجدول `cashbox_transactions`. ممنوع إنشاء/حذف `CashboxTransaction` مباشرة من أي مكان.
4. **أي عملية بتلمس أكتر من جدول = `DB::transaction()` واحدة**، مع **إعادة جلب الموديل بـ`lockForUpdate()` من جوه الـtransaction** — متثقش في النسخة الجاية من الـController.
5. **أي `toScaledInt()` لازم يكون جوه `try/catch` من أول سطر**، وكل حقل رقمي له `try/catch` منفصل برسالته.
6. **رصيد الخزنة ≠ صافي الربح.** الخزنة نقدي، الربح استحقاقي (الغرف المكتملة فقط). ممنوع الخلط في أي واجهة.
7. **الألوان والخطوط في `resources/css/app.css` فقط** عبر `@theme`. ممنوع hex في أي Blade.
8. **الدليل الوحيد المقبول = اختبارات Pest.** ممنوع tinker أو verification scripts للتحقق.
9. **ممنوع التجريد المبكر.** 3 أسطر متشابهة أحسن من abstraction مش مطلوب.
10. **ممنوع تعديل ملفات خارج نطاق التاسك** إلا بضرورة تتكتب صراحة في `AGENT_LOG.md`.
11. كل نص واجهة **عربي**. ممنوع إنجليزي إلا لو موجود بالفعل.

---

## 5. قاعدة `USER-GUIDE.md` (إلزامية)

> **أي تاسك بيغيّر في منطق الأعمال لازم ينتهي بتاسك فرعي مخصص لتحديث `USER-GUIDE.md`.**

- **"تغيير في المنطق" يعني:** قاعدة حسابية اتغيرت، حالة/كيان جديد، قاعدة تحقق (validation) جديدة أو اتشالت، سلوك حذف/تعديل اتغير، شاشة جديدة المستخدم هيتعامل معاها، أو أي حاجة المستخدم النهائي هيلاحظها.
- **مش شرط لو:** refactor بحت، تغيير شكل/CSS، تحسين أداء، أو إضافة اختبارات — من غير أي أثر على سلوك المستخدم.
- البند ده **مكتوب أصلاً في قالب `specs/_TEMPLATE.md`**، وممنوع تعليمه ✔ من غير ما تكون فعلاً عدّلت الملف.
- التحديث يبقى **بلغة المستخدم** (زي باقي `USER-GUIDE.md`) — مش شرح تقني ولا أسماء كلاسات.

---

## 6. دورة العمل لأي تاسك جديد

```text
1. اقرا specs/README.md → شوف فيه تاسك شغال ولا لأ
2. انسخ specs/_TEMPLATE.md → specs/NNN-اسم-التاسك.md واملاه (المطلوب + الخطة)
3. اعرض الخطة على المستخدم واستنى موافقته — وراجعها بموديل تاني لو التاسك كبير
4. نفّذ بالترتيب: migrations → models → services → requests → controllers → routes → views
5. php artisan test --compact          ← كل الاختبارات لازم تنجح (القديمة والجديدة)
6. vendor/bin/pint --dirty --format agent
7. لو فيه واجهة: npm run build
8. حدّث USER-GUIDE.md لو فيه تغيير منطق (القسم 5 فوق)
9. علّم ✔ بند بند في ملف الـspec (مش دفعة واحدة)
10. أضف إدخال في start-agent/AGENT_LOG.md
```

**ممنوع البدء في تاسك تاني قبل ما الحالي يخلّص الـ10 خطوات دول.**

---

## 7. أوامر سريعة

```bash
composer run dev                        # server + queue + vite مع بعض
php artisan test --compact              # كل الاختبارات
php artisan test --compact --filter=X   # اختبار محدد
vendor/bin/pint --dirty --format agent  # تنسيق المعدَّل فقط
npm run build                           # بناء الأصول
php artisan migrate                     # الـmigrations
```

دخول التطوير المحلي: `admin@dawood.test` / `password` (من `.env.example` — محلي فقط).
