# التاسكات الجارية — تتبع التنفيذ

> علّم ✔ **بند بند** فور ما يخلّص فعلًا. آخر تحديث: 2026-09-01.

| # | التاسك | الحالة |
|---|---|---|
| 1 | صفحة البروفايل (اسم + إيميل + كلمة مرور) | ✅ **مكتمل** (235/235 اختبار) |
| 2 | حذف فكرة تصنيفات المواد بالكامل | ✅ **مكتمل** (228/228 اختبار) |
| 3 | صفحة المخزون: إعادة تسمية + شريط بحث | ⬜ لم يبدأ |

**قرارات المستخدم المعتمدة (لكل التاسكات):**
- الإيميل قابل للتعديل من البروفايل • كلمة المرور الحالية إلزامية لتغيير الباسورد • `AdminUserSeeder` ينشئ فقط لو مفيش أي مستخدم.
- اسم المادة يبقى فريد على مستوى النظام كله • البيانات الحالية تجريبية، الحذف مسموح بلا قيود.
- إعادة التسمية للنص فقط، الرابط `/inventory/materials` يفضل زي ما هو • البحث من السيرفر، بالاسم فقط.

---

## التاسك 1 — صفحة البروفايل ✅

**الغرض:** المستخدم النهائي يقدر يغيّر اسمه وإيميله وكلمة مروره من الواجهة، من غير ما يحتاج التيرمينال.

**⚠️ الفخ المحلول:** `AdminUserSeeder` الحالي بيعمل `updateOrCreate` وبيكتب فوق الاسم/الباسورد من `.env` — يعني أي `db:seed` كان هيضيّع التعديلات، ولو الإيميل اتغيّر كان هيعمل أدمن **تاني**. العلاج: يفحص وجود **أي** مستخدم ويخرج لو لقى.

- [x] **1.1** `database/seeders/AdminUserSeeder.php` — لو `User::query()->exists()` يخرج فورًا؛ غير كده `create()` عادي. شيل import الـ`Hash` (الـcast `'password' => 'hashed'` بيتولاها)
- [x] **1.2** `app/Http/Requests/UpdateProfileRequest.php` — `name` مطلوب، `email` مطلوب + `Rule::unique('users','email')->ignore($this->user()->id)`
- [x] **1.3** `app/Http/Requests/UpdatePasswordRequest.php` — `current_password` (قاعدة Laravel المدمجة) + `password` بـ`min:8` و`confirmed`
- [x] **1.4** `app/Http/Controllers/ProfileController.php` — `edit` / `update` / `updatePassword`. **بدون `Hash::make`** + `session()->regenerate()` بعد تغيير الباسورد
- [x] **1.5** `routes/web.php` — `GET/PUT /profile` + `PUT /profile/password` جوه مجموعة `auth`
- [x] **1.6** `resources/views/profile/edit.blade.php` — كارتين منفصلين (بيانات أساسية / تغيير كلمة المرور)، بـ`<x-field>` وclasses منسوخة من الموجود
- [x] **1.7** رابط البروفايل في **فوتر الـSidebar** فوق زرار الخروج (باسم المستخدم) — مش في `$navItems`
- [x] **1.8** `lang/ar/validation.php` — إضافة `'current_password' => 'كلمة المرور الحالية'` في `attributes`
- [x] **1.9** `tests/Feature/ProfileTest.php` — 11 سيناريو (وصول الزائر، العرض، تعديل ناجح، إيميل مكرر، نفس الإيميل، اسم فاضي، تغيير باسورد ناجح، باسورد حالي غلط، تأكيد مش مطابق، أقل من 8، البقاء مسجَّل دخول)
- [x] **1.10** `tests/Feature/AdminUserSeederTest.php` — الاختبار الثالث (`updates the password when it changes`) **يتشال** (بيوثّق سلوك اتلغى بطلب المستخدم) ويتحط مكانه اتنين: عدم الكتابة فوق التعديلات + عدم إنشاء أدمن تاني بعد تغيير الإيميل
- [x] **1.11** `php artisan test --compact` — كل الاختبارات ناجحة
- [x] **1.12** `vendor/bin/pint --dirty --format agent` نظيف + `npm run build`
- [x] **1.13** `docs/business-rules.md` قاعدة 1 — تحديث وصف سلوك الـseeder
- [x] **1.14** `USER-GUIDE.md` — قسم "الملف الشخصي" بلغة المستخدم
- [x] **1.15** إدخال في `start-agent/AGENT_LOG.md`

**ممنوع لمس:** `app/Services/**`، `app/Casts/**`، أي migration، أي موديل، أي اختبار تاني غير المذكورين.

---

## التاسك 2 — حذف تصنيفات المواد ✅

**الغرض:** إلغاء طبقة تصنيف المواد الخام بالكامل. المادة تبقى: اسم + وحدة قياس بس.
**⚠️ ده مش بيمس `expense_categories` (بنود المصروفات) — دي حاجة تانية وهتفضل زي ما هي.**

- [x] **2.1** Migration جديدة: `dropUnique(['category_id','name'])` → `dropForeign` + `dropColumn('category_id')` → `unique('name')` → `dropIfExists('categories')` — **كل خطوة في `Schema::table` منفصلة** (SQLite بيرفض حذف عمود عليه فهرس)
- [x] **2.2** حذف: `app/Models/Category.php`، `database/factories/CategoryFactory.php`، `app/Http/Controllers/Inventory/CategoryController.php`، `app/Http/Requests/Inventory/{Store,Update}CategoryRequest.php`، `resources/views/inventory/categories/` كامل، `tests/Feature/Inventory/CategoryTest.php`
- [x] **2.3** `app/Models/Material.php` — شيل `category()` و`category_id` من `#[Fillable]`
- [x] **2.4** `database/factories/MaterialFactory.php` — شيل `category_id`
- [x] **2.5** `app/Http/Requests/Inventory/{Store,Update}MaterialRequest.php` — شيل `category_id`، وخلّي `unique('materials','name')` عام (مع `ignore` في Update)
- [x] **2.6** `app/Http/Controllers/Inventory/MaterialController.php` — شيل الـ`join` على `categories` والـ`with('category')` ودالة `categories()` الخاصة، ورتّب بالاسم بس
- [x] **2.7** `resources/views/inventory/materials/index.blade.php` — شيل عمود "التصنيف"
- [x] **2.8** `resources/views/inventory/materials/_fields.blade.php` — شيل الـ`<select>` بالكامل
- [x] **2.9** `resources/views/inventory/purchases/create.blade.php` + `resources/views/rooms/show.blade.php` — شيل `{{ $material->category->name }} — ` من نص الـoption
- [x] **2.10** `app/Http/Controllers/Inventory/PurchaseController.php` + `app/Http/Controllers/RoomController.php` — شيل `with('category')` و`material.category` من الـeager loading
- [x] **2.11** `routes/web.php` — شيل مسار `categories` وimport الـ`CategoryController`
- [x] **2.12** `resources/views/components/app-layout.blade.php` — شيل عنصر "التصنيفات" من `$navItems`
- [x] **2.13** `lang/ar/validation.php` — شيل `'category_id' => 'التصنيف'` من `attributes`
- [x] **2.14** `tests/Feature/Inventory/MaterialTest.php` — تحديث كل الاختبارات؛ اختبار "نفس الاسم في تصنيفين" **يتقلب** لاختبار "اسم مكرر مرفوض على مستوى النظام"
- [x] **2.15** `php artisan test --compact` + `pint` + `npm run build`
- [x] **2.16** `php artisan migrate` على قاعدة البيانات الحقيقية والتأكد إن الـ7 مواد لسه موجودة
- [x] **2.17** `docs/inventory.md` (قسم التصنيف + القيد الفريد)، `docs/system-overview.md` (صف `Category` + مخطط العلاقات)، `docs/mvp-scope.md` (سطر 7)
- [x] **2.18** `USER-GUIDE.md` القسم 2 — شيل كلام الأصناف
- [x] **2.19** إدخال في `AGENT_LOG.md`

**ملاحظة:** `docs/tasks.md` و`docs/tasks-checklist.md` **أرشيف — ممنوع تعديلهم** حتى لو فيهم كلام عن التصنيفات.

---

## التاسك 3 — صفحة المخزون: إعادة تسمية + بحث ⬜

**الغرض:** الصفحة تتسمى "المخزون" بدل "المواد"، وفيها شريط بحث بالاسم.

- [ ] **3.1** `resources/views/components/app-layout.blade.php` — تسمية عنصر `inventory.materials.*` تبقى "المخزون"
- [ ] **3.2** `resources/views/inventory/materials/index.blade.php` — `title` و`<h1>` يبقوا "المخزون" (الرابط ما يتغيرش)
- [ ] **3.3** فورم بحث `GET` أعلى الجدول: input اسمه `q` + زرار `{{ __('Search') }}` (موجود في `ar.json`) + رابط "إلغاء البحث" لما يكون فيه بحث نشط
- [ ] **3.4** `MaterialController::index()` — فلترة `when($request->filled('q'), fn($q) => $q->where('name','like','%'.$term.'%'))` مع الإبقاء على قيمة البحث في الفورم
- [ ] **3.5** رسالة "لا توجد نتائج" الحالية تفضل شغالة لما البحث ميرجّعش حاجة
- [ ] **3.6** اختبارات: البحث بيرجّع المطابق ويخفي غير المطابق، والبحث الفاضي بيرجّع الكل
- [ ] **3.7** `php artisan test --compact` + `pint` + `npm run build`
- [ ] **3.8** `USER-GUIDE.md` القسم 2 — ذكر البحث والاسم الجديد
- [ ] **3.9** إدخال في `AGENT_LOG.md`
