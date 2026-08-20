# قائمة التاسكات التفصيلية — تتبع التنفيذ

> مرجع: `docs/tasks.md`. كل تاسك هنا سطر أو اتنين. علّم ✔ فور الانتهاء.

## Task 0 — الأساس والمصادقة

- [x] LoginController::create() — عرض نموذج الدخول
- [x] LoginController::store() — تحقق + Auth::attempt + regenerate session
- [x] LoginController::destroy() — logout + invalidate session
- [x] Routes: GET/POST /login, POST /logout
- [x] throttle:5,1 على POST /login
- [x] View: auth/login.blade.php
- [x] Admin Seeder من ADMIN_EMAIL/ADMIN_PASSWORD (idempotent)
- [x] html lang="ar" dir="rtl" في الـlayout
- [x] APP_LOCALE=ar في .env وconfig/app.php
- [x] lang/ar/validation.php + auth.php
- [x] lang/ar.json لنصوص الواجهة
- [x] Design Tokens: ألوان النظام في app.css عبر @theme
- [x] Design Tokens: خط Cairo عبر bunny() في vite.config.js
- [x] config/app.php: timezone = Africa/Cairo
- [x] config/database.php: WAL + busy_timeout + transaction_mode=IMMEDIATE
- [x] App\Casts\MoneyCast + اختبارات
- [x] App\Casts\QuantityCast + اختبارات
- [x] Blade component <x-money>
- [x] middleware('auth') على كل المسارات + توجيه "/"
- [x] صفحة /dashboard بسيطة

## Task 1 — توثيق قواعد الأعمال

- [x] docs/system-overview.md
- [x] docs/business-rules.md
- [x] docs/customers-and-rooms.md
- [x] docs/inventory.md
- [x] docs/inventory-costing.md
- [x] docs/customer-payments.md
- [x] docs/expenses.md
- [x] docs/cashbox.md
- [x] docs/partners.md
- [x] docs/profit-calculation.md
- [x] docs/mvp-scope.md

## Task 2 — أساس الخزنة

- [x] Migration: cashbox_transactions + الفهارس
- [x] CashboxService::recordIn / recordOut
- [x] CashboxService::removeFor / updateFor
- [x] CashboxService::balance / totalIn / totalOut
- [x] CashboxService::summary() (استعلام مجمّع واحد)
- [x] CashboxService::setOpeningBalance (إنشاء أو تحديث، غير مكرر)
- [x] UI: /cashbox — ملخص + جدول الحركات
- [x] UI: نموذج تعيين/تعديل الرصيد الافتتاحي
- [x] اختبارات Task 2 (5 سيناريوهات)

## Task 3 — التصنيفات والمواد

- [x] Migration: categories (unique name)
- [x] Migration: materials (unique category_id+name)
- [x] Category model + factory
- [x] Material model + factory
- [x] Inventory\CategoryController (CRUD)
- [x] Inventory\MaterialController (CRUD)
- [x] Form Requests للتصنيفات والمواد
- [x] UI: /inventory/categories (index/create/edit/delete)
- [x] UI: /inventory/materials (index/create/edit/delete)
- [x] منع حذف تصنيف مرتبط بمواد
- [x] منع حذف مادة لها دفعات/احتياجات
- [x] اختبارات Task 3

## Task 4 — دفعات المخزون (Batches) + FIFO

- [x] Migration: inventory_batches
- [x] Migration: inventory_movements + الفهارس
- [x] InventoryService::purchase()
- [x] InventoryService::currentStock()
- [x] InventoryService::stockByMaterialIds()
- [x] InventoryService::issue() — FIFO ذرّي (all-or-nothing)
- [x] InventoryService::deletePurchase() — مقفول ضد race
- [x] InventoryService::returnIssued()
- [x] Inventory\PurchaseController (index/create/store/destroy)
- [x] عمود "الكمية الحالية" في /inventory/materials
- [x] اختبارات السيناريو المرجعي (540 ج.م، مخزون 8)
- [x] اختبار رفض الصرف الزائد بدون صرف جزئي

## Task 5 — العملاء والغرف وصرف الخامات

- [x] Migration: customers
- [x] Migration: rooms (+ RoomStatus enum)
- [x] Migration: room_materials (unique room_id+material_id)
- [x] Customer model + factory
- [x] Room model + factory (+ completed() state)
- [x] RoomMaterial model + factory
- [x] CustomerController (CRUD كامل)
- [x] RoomController: create/store/show/destroy
- [x] RoomMaterialService::addRequirement()
- [x] RoomMaterialService::issue()
- [x] RoomMaterialService::removeRequirement()
- [x] RoomService::deleteRoom() (قفل + إرجاع/استهلاك اختياري)
- [x] Room::materialsCost/paidAmount/remainingAmount/hasIssuedMaterials
- [x] DestroyRoomRequest (return_materials إلزامي عند وجود صرف)
- [x] UI: /customers (index/create/show/edit)
- [x] UI: /rooms/create و/rooms/{room} مع Modal الحذف
- [x] منع حذف عميل له غرف
- [x] RoomController::updateStatus (تغيير حالة الغرفة)
- [x] اختبارات Task 5 (7 سيناريوهات)

## Task 6 — مدفوعات العملاء

- [x] Migration: customer_payments
- [x] CustomerPaymentService::create() (+ منع تجاوز المتبقي)
- [x] CustomerPaymentService::update()
- [x] CustomerPaymentService::delete()
- [x] منع دفعة لغرفة cancelled
- [x] PaymentController (index/store/edit/update/destroy)
- [x] UI: /payments (سجل عام)
- [x] UI: نموذج إضافة دفعة داخل صفحة الغرفة
- [x] UI: تعديل دفعة
- [x] اختبارات Task 6 (كل السيناريوهات + التعديل والحذف)

## Task 7 — المصروفات الإدارية

- [x] Migration: expense_categories
- [x] Migration: expenses
- [x] ExpenseService (create/update/delete)
- [x] ExpenseCategoryController (CRUD)
- [x] ExpenseController (index/create/store/edit/update/destroy)
- [x] UI: /expenses, /expenses/create, /expenses/categories
- [x] منع حذف تصنيف مصروف مستخدم
- [x] اختبارات Task 7

## Task 8 — حساب الربح

- [x] ProfitService::revenue()
- [x] ProfitService::costOfMaterials()
- [x] ProfitService::adminExpenses()
- [x] ProfitService::netProfit()
- [x] ProfitService::workInProgress()
- [x] InventoryService::stockValue()
- [x] ProfitService::summary() (تجميع دون تكرار استعلامات)
- [x] RoomStatus::countsTowardProfit / countsTowardWorkInProgress
- [x] ProfitController + route /reports/profit
- [x] UI: reports/profit.blade.php (5 أرقام + الفرق عن الخزنة)
- [x] رابط "تقرير الربح" في شريط التنقل
- [x] اختبارات Task 8 (4 سيناريوهات المرجعية)

## Task 8.5 — واجهة Sidebar والعلامة التجارية (طلب مباشر من المستخدم — قبل Task 9)

- [x] التأكد من توفر Skill تصميم الفرونت إند (غير متاحة — تم الاتفاق مع المستخدم على المتابعة بدونها)
- [x] استبدال شريط التنقل العلوي (Header) بـSidebar جانبي ثابت
- [x] نقل كل روابط الصفحات (العملاء، التصنيفات، المواد، المشتريات، المدفوعات، المصروفات، الخزنة، تقرير الربح) داخل الـSidebar
- [x] مكان مخصص للوجو أعلى الـSidebar (شارة "D" + مكان جاهز لاستبدالها بلوجو حقيقي)
- [x] استبدال اسم "Laravel" بـ"DAWOOD" في كل الواجهة (APP_NAME + شاشة الدخول)
- [x] تعديل تخطيط الصفحة الرئيسي ليتوافق مع الـSidebar (padding/margin للمحتوى)
- [x] تحسينات عامة على مظهر الفرونت (شارة اللوجو، حالة الرابط النشط، هيدر موبايل، تحسين شاشة الدخول)
- [x] فحص عدم وجود أخطاء سيرفر بعد التغيير (202/202 اختبار ناجح تغطي كل الصفحات عبر الـlayout الجديد)
- [x] عرض الشكل على المستخدم والتوقف لأخذ الملاحظات قبل أي تعديل إضافي

## Task 8.6 — خطة تصميم شاملة للـStyle (Skill: frontend-design)

- [x] نقل frontend-design/SKILL.md إلى .claude/skills/frontend-design/ ليصير قابل للاستخدام
- [x] تحديد هوية بصرية مرتبطة بموضوع التطبيق (ورشة أثاث/خشب) بدل الأزرق العام
- [x] لوحة ألوان جديدة دافئة (نحاس/خشب جوز/كتان) بأسماء hex محددة
- [x] عنصر "توقيع" مميز (Sidebar خشبي غامق كـ"طاولة عمل" مقابل محتوى فاتح)
- [x] مراجعة الخطة ذاتيًا للتأكد أنها ليست القالب الافتراضي (أزرق SaaS عام)

## Task 8.7 — تنفيذ الـStyle الجديد (بدون أي تغيير في المنطق أو البنية)

- [x] تحديث Design Tokens في app.css باللوحة الجديدة (تنعكس على كل الصفحات فورًا)
- [x] تحسين شكل الأزرار (Primary/Danger) وحالات hover/focus/shadow
- [x] تحسين شكل حقول الإدخال والـlabels
- [x] تحسين شكل البطاقات (ظلال، حواف أوسع، تباعد)
- [x] تحسين شكل الجداول (رأس الجدول بأحرف كبيرة، hover على الصفوف)
- [x] تحسين الـSidebar (خشبي غامق + نحاسي للعنصر النشط)
- [x] Badges ملونة لحالة الغرفة (مسودة/تحت التنفيذ/مكتملة/ملغاة)
- [x] تحسين بطاقات الإحصائيات (الخزنة، تقرير الربح) + لوحة تحكم بروابط سريعة
- [x] تحسين شاشة تسجيل الدخول
- [x] التأكد أن كل الاختبارات لسه ناجحة (202/202، لا تغيير منطقي) + Pint نظيف
- [x] npm run build وفحص عدم وجود أخطاء
- [ ] عرض الشكل النهائي على المستخدم والتوقف لأخذ الملاحظات

## Task 9 — الشركاء والسحوبات

- [ ] Migration: partners (name, percentage ×100)
- [ ] Migration: partner_withdrawals
- [ ] Partner model + factory
- [ ] PartnerWithdrawal model + factory
- [ ] PartnerService::share() — صيغة القسمة على 10000
- [ ] PartnerService::totalWithdrawn()
- [ ] PartnerService::remaining()
- [ ] PartnerService::withdraw() (+ CashboxService::recordOut)
- [ ] PartnerService::deleteWithdrawal() (+ عكس حركة الخزنة)
- [ ] Validation: مجموع نسب الشركاء ≤ 100%
- [ ] PartnerController (CRUD)
- [ ] Withdrawal store/destroy (مسارات فرعية)
- [ ] UI: /partners (index)
- [ ] UI: /partners/{partner} (نصيب/سحوبات/متبقي)
- [ ] تحذير واضح عند صافي ربح سالب أو سحب زائد
- [ ] اختبارات Task 9 (6 سيناريوهات)

## Task 10 — تجانس الواجهة والتنقل

- [x] Layout أساسي موحد (app-layout.blade.php)
- [ ] رابط الشركاء في شريط التنقل (بعد Task 9)
- [x] توحيد رسائل النجاح/الخطأ (flash) بشكل أساسي
- [ ] توحيد شكل عرض أخطاء الـvalidation في كل الصفحات
- [ ] Blade components مشتركة (جدول/نموذج/زر حذف)
- [ ] مراجعة أن كل المبالغ تُعرض حصرًا عبر <x-money>
- [ ] تنسيق موحد لعرض كل التواريخ
- [ ] Empty states واضحة لكل جدول
- [ ] تدقيق Design Tokens (بحث hex/font-family خارج app.css)
- [ ] تصحيح أي مخالفات يكشفها التدقيق

## Task 11 — اختبار الدورة الكاملة (Integration Test)

- [ ] اختبار Pest شامل: رصيد افتتاحي → شراء → مخزون
- [ ] نفس الاختبار: غرفة → صرف خامات → تكلفة
- [ ] نفس الاختبار: دفعة عميل → مصروف → إكمال الغرفة
- [ ] نفس الاختبار: حساب الربح → نصيب شريك → سحب
- [ ] التحقق أن كل أرقام الجدول المرجعي مطابقة (خزنة 14,500 / ربح 27,460)
- [ ] التحقق: لا حركة خزنة بلا مصدر (عدا الرصيد الافتتاحي)
- [ ] التحقق: لا حركة مالية محسوبة مرتين (مقارنة يدوية)

---

**الإجمالي:** Tasks 0–8 و8.5 مكتملة بالكامل (اختبارات: 202/202 ناجحة). في انتظار ملاحظات المستخدم على شكل الـSidebar الجديد قبل المتابعة. Tasks 9، 11 لم يبدأ فيهما. Task 10 بدأ جزئيًا فقط (Layout موحد وflash موحد، الـSidebar الجديد يغطي جزءًا من متطلباته)، والباقي (مكونات مشتركة، تدقيق Design Tokens) لم يُنفَّذ.
