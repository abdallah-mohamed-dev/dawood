# التاسكات الجارية — تتبع التنفيذ

> علّم ✔ **بند بند** فور ما يخلّص فعلًا. آخر تحديث: 2026-09-01.

| # | التاسك | الحالة |
|---|---|---|
| 1 | صفحة البروفايل (اسم + إيميل + كلمة مرور) | ✅ **مكتمل** (235/235 اختبار) |
| 2 | حذف فكرة تصنيفات المواد بالكامل | ✅ **مكتمل** (228/228 اختبار) |
| 3 | صفحة المخزون: إعادة تسمية + شريط بحث | ✅ **مكتمل** (233/233 اختبار) |

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

## التاسك 3 — صفحة المخزون: إعادة تسمية + بحث ✅

**الغرض:** الصفحة تتسمى "المخزون" بدل "المواد"، وفيها شريط بحث بالاسم.

- [x] **3.1** `resources/views/components/app-layout.blade.php` — تسمية عنصر `inventory.materials.*` تبقى "المخزون"
- [x] **3.2** `resources/views/inventory/materials/index.blade.php` — `title` و`<h1>` يبقوا "المخزون" (الرابط ما يتغيرش)
- [x] **3.3** فورم بحث `GET` أعلى الجدول: input اسمه `q` + زرار `{{ __('Search') }}` (موجود في `ar.json`) + رابط "إلغاء البحث" لما يكون فيه بحث نشط
- [x] **3.4** `MaterialController::index()` — فلترة `when($request->filled('q'), fn($q) => $q->where('name','like','%'.$term.'%'))` مع الإبقاء على قيمة البحث في الفورم
- [x] **3.5** رسالة "لا توجد نتائج" الحالية تفضل شغالة لما البحث ميرجّعش حاجة
- [x] **3.6** اختبارات: البحث بيرجّع المطابق ويخفي غير المطابق، والبحث الفاضي بيرجّع الكل
- [x] **3.7** `php artisan test --compact` + `pint` + `npm run build`
- [x] **3.8** `USER-GUIDE.md` القسم 2 — ذكر البحث والاسم الجديد
- [x] **3.9** إدخال في `AGENT_LOG.md`

---
---

# الدفعة الثانية — تاسكات 4 → 9

**قرارات المستخدم المعتمدة (لكل الدفعة دي):**

- **تكاليف الغرفة:** جدول واحد `room_costs` بعمود `type` • تتحسب في الربح للغرف المكتملة بس (زي الخامات) • **الغرف الملغاة: تكاليفها خسارة فورية** لأن الفلوس خرجت ومش هترجع • حذف غرفة فيها تكاليف **ممنوع** لحد ما تتمسح بإيد المستخدم • مفيش حقل "مصنعية متفق عليها" — دفعات بس • المصنعية وصف حر، مفيش حقل عامل.
- **ربح الغرفة:** يظهر لكل الحالات، اسمه "الربح المتوقع" لغير المكتملة • **بدون** المصروفات الإدارية.
- **طريقة الدفع:** على الداخل **والخارج** • عمود واحد على `cashbox_transactions` بس (مفيش تكرار على 5 جداول) • رصيد واحد + كروت تقسيم للعرض • الشيك زي الكاش فورًا.
- **الخزنة:** جدولين منفصلين جنب بعض، مفيش عرض مدمج • عمود البند تفصيلي عبر `morphWith` مش عبر تخزين نص.
- **الباجينيشن:** صفحات الفهارس بس • المخزون 50، الباقي 25 • الجداول الداخلية (غرف العميل، سحوبات الشريك، جداول الغرفة) تفضل من غير باجينيشن.
- **الإضافة السريعة:** فورم ظاهر **دايمًا** فوق الجدول في كل الصفحات • صفحات `create` **تتلغي خالص** • إضافة الغرفة تبقى فورم سريع في صفحة العميل.
- **المشتريات:** بحث باسم المادة + نطاق تاريخ + حالة الدفعة • شريط ملخص (عدد + إجمالي).
- **فواصل الشهور:** Blade بحت (مفيش JS) + إجمالي الشهر كامل من استعلام مستقل.

| # | التاسك | الحالة |
|---|---|---|
| 4 | تكاليف الغرفة (المصنعية + مصروفات إضافية + ربح الغرفة) | ✅ **مكتمل** (262/262 اختبار) |
| 5 | صفحة الخزنة (طريقة الدفع + جدولين + البند التفصيلي) | ✅ **مكتمل** (285/285 اختبار) |
| 6 | الباجينيشن الناقص | ✅ **مكتمل** (289/289 اختبار) |
| 7 | الإضافة السريعة من نفس الصفحة | ✅ **مكتمل** (312/312 اختبار) |
| 8 | فلاتر وبحث المشتريات | ⬜ لم يبدأ |
| 9 | فواصل الشهور في المصروفات | ⬜ لم يبدأ |

**الترتيب إلزامي:** 4 → 5 → 6 → 7 → 8 → 9.
4 و5 بيحطوا الأساس (kinds جديدة + وسيلة دفع) اللي فورمات تاسك 7 محتاجاه. و7 قبل 8 لأن الاتنين بيعيدوا كتابة صفحة المشتريات.

---

## التاسك 4 — تكاليف الغرفة: المصنعية + المصروفات الإضافية + ربح الغرفة ✅

**الغرض:** الغرفة يبقى فيها قسمين جداد — "المصنعية" (دفعات للنجار بالتواريخ) و"مصروفات إضافية" (سبب + مبلغ + تاريخ). الاتنين فلوس بتخرج من الخزنة فعلاً وبتدخل في تكلفة الغرفة وربحها.

**⚠️ الفخاخ المتوقعة:**
1. صفحة الغرفة هيبقى فيها **4 فورمات** (خامة، دفعة، مصنعية، مصروف إضافي). فورم الدفعة وفورم التكلفة الاتنين فيهم حقل اسمه `amount` → **من غير named error bags، خطأ في فورم التكلفة هيظهر جوه فورم الدفعة**. الفورمات القديمة تفضل على الـbag الافتراضي، الجداد بس هياخدوا bags مسماة.
2. الغرفة الملغاة: خاماتها بتختفي من الربح ومن WIP. تكاليفها **لأ** — لازم تتطرح فورًا وإلا فلوس هتخرج من الخزنة ومتظهرش في التقرير أبدًا.
3. كل جنيه لازم يبقى محسوب في مكان واحد بالظبط: مكتملة → تكلفة • مسودة/تحت التنفيذ → WIP • ملغاة → خسارة فورية.

### قاعدة البيانات والكيانات
- [x] **4.1** Migration `create_room_costs_table`: `id` · `room_id` foreignId constrained **cascadeOnDelete** · `type` string · `description` string nullable · `amount` bigInteger · `occurred_at` date · timestamps. **فهرس صريح** على `room_id` (SQLite مبيعملهوش تلقائيًا) + فهرس مركّب `['room_id','type']`
- [x] **4.2** `app/Enums/RoomCostType.php` — `Labor = 'labor'` / `Other = 'other'` + `label()`: "مصنعية" / "مصروف إضافي" + `cashboxKind()` بترجّع الـkind المقابل
- [x] **4.3** `app/Enums/CashboxTransactionKind.php` — حالتين جداد: `RoomLabor = 'room_labor'` → "مصنعية غرفة" · `RoomExpense = 'room_expense'` → "مصروف غرفة"
- [x] **4.4** `app/Models/RoomCost.php` — `#[Fillable([...])]`، casts: `amount` → `MoneyCast`، `type` → `RoomCostType`، `occurred_at` → `'date'`، علاقة `room()`
- [x] **4.5** `database/factories/RoomCostFactory.php`
- [x] **4.6** `app/Exceptions/RoomHasCostsException.php`

### الخدمات
- [x] **4.7** `app/Services/RoomCostService.php`:
      - `create(Room $room, RoomCostType $type, int $amount, $date, ?string $description): RoomCost` — `DB::transaction` واحدة، **إعادة جلب الغرفة بـ`lockForUpdate()` من جوه الترانزاكشن**، إنشاء الصف، ثم `cashbox->recordOut($cost, $amount, $type->cashboxKind(), $date)`
      - `delete(RoomCost $cost): void` — `DB::transaction`: `cashbox->removeFor($cost)` ثم `$cost->delete()`
      - guard: `$amount <= 0` يرمي `InvalidArgumentException`
      - **ممنوع** إنشاء `CashboxTransaction` مباشرة (قاعدة الكاتب الوحيد)
- [x] **4.8** `app/Models/Room.php` — `roomCosts(): HasMany` + `laborCost()` · `otherCost()` · `costsTotal()` · `hasCosts()`، كلها بنفس نمط `materialsCost()` الموجود
- [x] **4.9** `app/Services/RoomService::deleteRoom()` — من جوه الترانزاكشن بعد الـ`lockForUpdate`: لو `$room->roomCosts()->exists()` يرمي `RoomHasCostsException` **قبل** أي حذف
- [x] **4.10** `app/Services/ProfitService.php`:
      - `roomCosts(): int` — مجموع `room_costs` للغرف المكتملة
      - `cancelledRoomCosts(): int` — مجموع `room_costs` للغرف الملغاة (خسارة فورية)
      - `netProfit()` = الإيراد − الخامات − المصروفات الإدارية − `roomCosts()` − `cancelledRoomCosts()`
      - `workInProgress()` — يضيف تكاليف الغرف اللي `countsTowardWorkInProgress()`
      - `forRoom(Room $room): array{sale_price, materials, labor, other, total_cost, profit}` — **بدون المصروفات الإدارية**
      - `summary()` — تضيف `room_costs` و `cancelled_room_costs`

### الطلبات والمسارات والكونترولر
- [x] **4.11** `app/Http/Requests/StoreRoomCostRequest.php` — `type` (`Rule::enum`) · `amount` required · `occurred_at` required date · `description` nullable string max 255. **`prepareForValidation()` بتحدد `$this->errorBag`** حسب النوع عشان الأخطاء ما تسربش لفورم الدفعة
- [x] **4.12** `app/Http/Controllers/RoomController.php` — `storeCost()` و `destroyCost()`. `MoneyCast::toScaledInt()` جوه `try/catch` من أول سطر برسالة عربية على حقل `amount`. و`destroy()` يـcatch الـ`RoomHasCostsException` ويرجّع رسالة عربية واضحة
- [x] **4.13** `routes/web.php` — `POST /rooms/{room}/costs` (`rooms.costs.store`) + `DELETE /rooms/{room}/costs/{roomCost}` (`rooms.costs.destroy`)
- [x] **4.14** `RoomController::show()` — `load('roomCosts')` وتمرير تفصيل الربح من `ProfitService::forRoom()`

### الواجهة
- [x] **4.15** `resources/views/rooms/show.blade.php` — الكروت فوق تبقى **8** في `sm:grid-cols-4` (صفين طبيعيين): سعر البيع · تكلفة الخامات · المصنعية · مصروفات أخرى ‖ إجمالي التكلفة · الربح · المدفوع · المتبقي
- [x] **4.16** كارت الربح: العنوان `الربح` للمكتملة و`الربح المتوقع` لغيرها، وتحته سطر صغير: "لا يشمل المصروفات الإدارية" + للغير مكتملة "الخامات غير المصروفة لم تُحسب بعد"
- [x] **4.17** قسم "المصنعية": فورم أفقي (وصف · مبلغ · تاريخ · زر) + جدول (التاريخ · الوصف · المبلغ · حذف) + سطر إجمالي، بـerror bag خاص
- [x] **4.18** قسم "مصروفات إضافية": نفس الشكل بالظبط، `type=other`، الحقل اسمه "السبب"، بـerror bag خاص
- [x] **4.19** `resources/views/reports/profit.blade.php` — كارتين جداد: "تكاليف الغرف المكتملة (مصنعية + أخرى)" و"تكاليف غرف ملغاة (خسارة)" مع شرح صغير
- [x] **4.20** `lang/ar/validation.php` — إضافة الـ`attributes` الناقصة للحقول الجديدة

### التحقق
- [x] **4.21** `tests/Feature/Services/RoomCostServiceTest.php` — الإضافة بتنشئ صف خزنة خارج بالـkind الصح • الحذف بيشيل صف الخزنة • مبلغ صفر/سالب مرفوض • رصيد الخزنة بينقص بالمبلغ
- [x] **4.22** `tests/Feature/Services/ProfitServiceTest.php` — تكاليف غرفة مكتملة بتنقص الربح • غرفة تحت التنفيذ بتروح WIP مش الربح • **غرفة ملغاة بتنقص الربح فورًا** • مجموع (ربح + WIP) متسق
- [x] **4.23** `tests/Feature/RoomTest.php` — حذف غرفة فيها تكاليف مرفوض برسالة عربية • حذفها بينجح بعد مسح التكاليف • خطأ في فورم المصنعية **مايظهرش** في فورم الدفعة (اختبار الـerror bag)
- [x] **4.24** `tests/Feature/Services/FullCycleIntegrationTest.php` — إضافة مصنعية للدورة الكاملة والتأكد إن الربح والخزنة الاتنين صح
- [x] **4.25** `php artisan test --compact` — كل الاختبارات (القديمة والجديدة) ناجحة
- [x] **4.26** `vendor/bin/pint --dirty --format agent` نظيف + `npm run build`

### التوثيق
- [x] **4.27** `docs/profit-calculation.md` — المعادلة الجديدة بالمثال الرقمي + قاعدة الغرفة الملغاة
- [x] **4.28** `docs/system-overview.md` — كيان `RoomCost` والعلاقة
- [x] **4.29** `docs/customers-and-rooms.md` — قاعدة منع حذف غرفة فيها تكاليف
- [x] **4.30** `USER-GUIDE.md` — بلغة المستخدم: القسمين الجداد، إن الفلوس بتخرج من الخزنة، معنى "الربح المتوقع"، وإن الغرفة مش هتتحذف قبل مسح تكاليفها
- [x] **4.31** إدخال في `start-agent/AGENT_LOG.md`

**ممنوع لمس:** `app/Casts/**` · `InventoryService` · `CustomerPaymentService` · `ExpenseService` · أي حاجة في صفحة الخزنة (دي تاسك 5).

---

## التاسك 5 — صفحة الخزنة: طريقة الدفع + جدولين + البند التفصيلي ✅

**الغرض:** تعرف كل جنيه دخل أو خرج **إزاي** (كاش/محفظة/انستا/شيك/فيزا)، وتشوف الداخل والخارج في جدولين منفصلين، وعمود "البند" يقول الحاجة التفصيلية مش النوع العام.

**⚠️ الفخاخ المتوقعة:**
1. جدولين في صفحة واحدة بباجينيشن واحد = الاتنين هيتحركوا مع بعض. **لازم `pageName` مختلف لكل واحد.**
2. `setOpeningBalance()` بيستخدم `updateOrCreate` ومابيمرش على `record()` → لازم ياخد وسيلة الدفع بنفسه.
3. صفوف الخزنة القديمة مالهاش وسيلة دفع → العمود **nullable** والعرض بيتعامل مع `null`.
4. `morphWith` من غيره = N+1 مرعب (4 استعلامات × 25 صف).

### وسيلة الدفع
- [x] **5.1** `app/Enums/PaymentMethod.php` — `Cash` · `Wallet` · `Instapay` · `Cheque` · `Card` + `label()` عربي (كاش · محفظة · انستاباي · شيك · فيزا)
- [x] **5.2** Migration `add_payment_method_to_cashbox_transactions` — عمود `payment_method` string **nullable** + فهرس
- [x] **5.3** `app/Models/CashboxTransaction.php` — إضافته لـ`#[Fillable]` وcast لـ`PaymentMethod::class`
- [x] **5.4** `app/Services/CashboxService.php` — `recordIn`/`recordOut`/`record` تاخد `PaymentMethod $method = PaymentMethod::Cash` • `updateFor` تحدّثها لو اتبعتت • `setOpeningBalance` تاخدها كمان • **ميثود جديدة `breakdownByMethod()`** بترجّع الداخل والخارج لكل وسيلة في استعلام `GROUP BY` واحد
- [x] **5.5** الخدمات اللي بتنادي الخزنة تمرّر الوسيلة: `CustomerPaymentService` (create + update) · `InventoryService::purchase` · `ExpenseService` (create + update) · `PartnerService::withdraw` · `RoomCostService::create`
- [x] **5.6** علاقة `morphOne` اسمها `cashboxTransaction()` على `CustomerPayment` و`Expense` — عشان صفحات التعديل تعرض الوسيلة الحالية

### الفورمات
- [x] **5.7** `<select>` طريقة الدفع (الافتراضي "كاش") في: فورم دفعة الغرفة · تعديل الدفعة · إضافة/تعديل مصروف · إضافة مشتريات · سحب شريك · المصنعية والمصروف الإضافي · الرصيد الافتتاحي
- [x] **5.8** الـForm Requests المقابلة: `payment_method` required + `Rule::enum(PaymentMethod::class)`
- [x] **5.9** `lang/ar/validation.php` — `'payment_method' => 'طريقة الدفع'`

### الصفحة
- [x] **5.10** `CashboxController::index()` — استعلامين منفصلين بـ`pageName` مختلف (`in_page` / `out_page`)، الاتنين بـ`->withQueryString()`
- [x] **5.11** eager loading بـ`morphWith` على العلاقة `source` للجدولين: `Expense => ['category']` · `CustomerPayment => ['room.customer']` · `InventoryBatch => ['material']` · `PartnerWithdrawal => ['partner']` · `RoomCost => ['room.customer']`
- [x] **5.12** `CashboxTransaction::detailedLabel(): string` — `match` على `source_type`: مصروف → اسم البند • دفعة → "اسم العميل — نوع الغرفة" • شراء → اسم المادة • سحب → اسم الشريك • تكلفة غرفة → "مصنعية/مصروف — نوع الغرفة" • رصيد افتتاحي أو مصدر مفقود → `kind->label()`
- [x] **5.13** كروت التقسيم فوق: الرصيد + إجمالي الداخل + إجمالي الخارج (زي ما هم) ثم **صف كروت تقسيم بالوسيلة** (داخل وخارج لكل وسيلة) — **بدون صافي لكل وسيلة** عشان مايبقاش رقم مضلل
- [x] **5.14** `resources/views/cashbox/index.blade.php` — `grid gap-6 lg:grid-cols-2` (تحت بعض على الموبايل، الداخل على اليمين في RTL)، كل جدول بعنوان وباجينيشن مستقل، أعمدة: التاريخ · البند · طريقة الدفع · المبلغ
- [x] **5.15** لافتة تحذيرية صغيرة تحت كروت التقسيم: "التقسيم للعرض فقط — الرصيد رقم واحد، وليست محافظ منفصلة"

### التحقق والتوثيق
- [x] **5.16** `tests/Feature/CashboxTest.php` — الجدولين منفصلين • الباجينيشن مستقل • التقسيم بالوسيلة صح • البند التفصيلي بيظهر لكل نوع مصدر • صف قديم بدون وسيلة مابيكسرش الصفحة
- [x] **5.17** `tests/Feature/Services/CashboxServiceTest.php` — الوسيلة بتتخزن • الافتراضي كاش • `updateFor` بتغيّرها
- [x] **5.18** اختبار عدد الاستعلامات ثابت مع زيادة الصفوف (N+1)
- [x] **5.19** `php artisan test --compact` + `pint` + `npm run build`
- [x] **5.20** `docs/cashbox.md` + `USER-GUIDE.md`
- [x] **5.21** إدخال في `AGENT_LOG.md`

---

## التاسك 6 — الباجينيشن الناقص ✅

**الغرض:** الصفحات اللي جداولها هتطول مع الوقت مايبقاش فيها `get()` مفتوح.
**الموجود خلاص (متلمسوش):** الخزنة · العملاء · المصروفات · المشتريات · الشركاء · المدفوعات.

- [x] **6.1** `MaterialController::index()` — `paginate(50)` + `->withQueryString()` (عشان البحث `q` مايضيعش في الصفحة التانية)
- [x] **6.2** **⚠️ مهم:** `stockByMaterialIds()` دلوقتي بتجيب مخزون **كل** المواد — تتقيّد بمواد الصفحة الحالية بس
- [x] **6.3** `resources/views/inventory/materials/index.blade.php` — `{{ $materials->links() }}`
- [x] **6.4** `ExpenseCategoryController::index()` — `paginate(25)` + links
- [x] **6.5** اختبارات: الصفحة التانية شغالة • البحث بيفضل شغال مع الباجينيشن • مخزون الصفحة الحالية بس بيتجاب
- [x] **6.6** `php artisan test --compact` + `pint` + `npm run build`
- [x] **6.7** إدخال في `AGENT_LOG.md`

**قرار معتمد:** الجداول الداخلية (غرف العميل · سحوبات الشريك · خامات/دفعات/تكاليف الغرفة) **تفضل من غير باجينيشن** — قصيرة بطبيعتها، وصفحة الغرفة فيها 5 جداول فأشرطة ترقيم كتير هتبقى فوضى.

---

## التاسك 7 — الإضافة السريعة من نفس الصفحة ✅

**الغرض:** الإضافة تبقى فورم أفقي **ظاهر دايمًا** فوق الجدول، وصفحات الإضافة المنفصلة تتلغي خالص.

**⚠️ الفخاخ المتوقعة:**
1. صفحات `edit` بتـ`@include` نفس ملف `_fields` بتاع الإضافة — لو مسحته هتكسر التعديل. **راجع كل `_fields.blade.php` قبل ما تمسح.**
2. لو الفورم رجع بخطأ، لازم `old()` تشتغل والأخطاء تظهر فوق الجدول — و**كل صفحة فيها أكتر من فورم لازم named error bags**.
3. الاختبارات الحالية بتضرب على راوتات `create` — هتتكسر كلها.

- [x] **7.1** `resources/views/components/quick-add.blade.php` — كومبوننت واحد للفورم الأفقي (**تجريد مبرر: 6 استخدامات فعلية**)
- [x] **7.2** المخزون — فورم: اسم المادة · وحدة القياس · زر. حذف `create` (كونترولر + راوت + فيو)
- [x] **7.3** العملاء — فورم: الاسم · الهاتف · العنوان · زر. حذف `create`
- [x] **7.4** بنود المصروفات — فورم: اسم البند · زر. حذف `create`
- [x] **7.5** المشتريات — فورم: المادة · الكمية · سعر الوحدة · التاريخ · طريقة الدفع · زر. حذف `create`
- [x] **7.6** المصروفات — فورم: البند · المبلغ · التاريخ · الوصف · طريقة الدفع · زر. حذف `create`
- [x] **7.7** **صفحة العميل** — فورم إضافة غرفة: نوع الغرفة · سعر البيع · زر (العميل معروف من الصفحة). حذف `rooms.create`، و`RoomController::store` تاخد الـ`customer_id` من المسار
- [x] **7.8** `routes/web.php` — تنضيف كل راوتات `create` الملغاة
- [x] **7.9** تحديث كل الاختبارات اللي بتضرب على `*.create` — تتحوّل لاختبار إن الفورم ظاهر في `index`
- [x] **7.10** اختبارات جديدة: خطأ validation بيرجّع للصفحة نفسها بالقيم والأخطاء • مايسربش لفورم تاني في نفس الصفحة
- [x] **7.11** `php artisan test --compact` + `pint` + `npm run build`
- [x] **7.12** `USER-GUIDE.md` — الإضافة بقت من نفس الصفحة
- [x] **7.13** إدخال في `AGENT_LOG.md`

---

## التاسك 8 — فلاتر وبحث المشتريات ⬜

**الغرض:** تلاقي عملية شراء بسرعة وتعرف إجمالي اللي اشتريته في فترة.

**⚠️ فخ:** حساب الإجمالي = `كمية × سعر ÷ 1000`. **قاعدة المشروع رقم 2: الصيغة دي مكانها الوحيد `InventoryService::cost()`** — ممنوع `selectRaw` في الكونترولر، لازم ميثود على الخدمة.

- [ ] **8.1** `PurchaseController::index()` — يقبل: `q` (اسم المادة) · `from` / `to` (تاريخ) · `status`، كلها `when()` وكلها `->withQueryString()`
- [ ] **8.2** تعريف الحالة: `available` = `remaining_quantity = quantity` • `partial` = بين الاتنين • `depleted` = `remaining_quantity = 0`
- [ ] **8.3** `InventoryService::purchasesSummary()` — تستخدم **نفس** صيغة `cost()` مش نسخة تانية
- [ ] **8.4** شريط فلاتر فوق الجدول (فورم `GET` واحد) + رابط "إلغاء الفلاتر" + زرين اختصار: "الشهر ده" / "الشهر اللي فات"
- [ ] **8.5** شريط ملخص: "N عملية شراء — إجمالي X ج.م"
- [ ] **8.6** رسالة `empty` مخصصة لما الفلترة مترجعش حاجة
- [ ] **8.7** اختبارات: كل فلتر لوحده • مع بعض • بيفضلوا مع الباجينيشن • الملخص بيتغير مع الفلترة • الإجمالي مطابق لمجموع `cost()`
- [ ] **8.8** `php artisan test --compact` + `pint` + `npm run build`
- [ ] **8.9** `USER-GUIDE.md` + إدخال في `AGENT_LOG.md`

---

## التاسك 9 — فواصل الشهور في المصروفات ⬜

**الغرض:** الجدول يبقى مقروء — فاصل بصري بين كل شهر والتاني.
**قرار معتمد:** **Blade بحت، صفر JS، صفر تغيير في الداتابيز.**

**⚠️ فخ:** مع الباجينيشن، الشهر ممكن يتقسم على صفحتين → **إجمالي الشهر لازم ييجي من استعلام مستقل على كل المصروفات، مش من صفوف الصفحة**، وإلا الرقم هيبقى غلط.

- [ ] **9.1** `ExpenseController::index()` — استعلام تجميعي مستقل: إجمالي كل شهر → مصفوفة مفتاحها `Y-m`
- [ ] **9.2** `resources/views/expenses/index.blade.php` — في اللوب: مقارنة `occurred_at->format('Y-m')` بالصف اللي قبله، ولو اختلف يطبع `<tr>` فاصل بـ`colspan` كامل
- [ ] **9.3** الفاصل: اسم الشهر بالعربي + السنة + "إجمالي الشهر: X ج.م" (**ألوان من الـtokens بس، ممنوع hex**)
- [ ] **9.4** أسماء الشهور العربية من `lang/ar/` مش hardcoded في الـBlade
- [ ] **9.5** اختبارات: الفاصل بيظهر عند تغيير الشهر • مابيظهرش بين صفين في نفس الشهر • إجمالي الشهر صح حتى لما الشهر مقسوم على صفحتين
- [ ] **9.6** `php artisan test --compact` + `pint` + `npm run build`
- [ ] **9.7** إدخال في `AGENT_LOG.md` (**مفيش تحديث لـ`USER-GUIDE.md`** — تغيير عرض بحت بدون أثر على المنطق)
