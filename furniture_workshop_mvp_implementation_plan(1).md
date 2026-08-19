# خطة تنفيذ MVP — نظام إدارة وحسابات ورشة أثاث خشبي

## 1. الهدف من هذه الخطة

هذه الوثيقة هي خطة تنفيذ عملية لنظام إدارة وحسابات ورشة أثاث خشبي باستخدام Laravel.

الهدف ليس بناء النظام الكامل من أول مرة، وإنما بناء **MVP صغير، مستقر، قابل للاختبار والتوسعة**.

الأولوية بالترتيب:

1. صحة الـ business logic.
2. صحة قاعدة البيانات والعلاقات.
3. صحة الحركات المالية والمخزنية.
4. اختبار كل جزء قبل الانتقال للجزء التالي.
5. واجهة بسيطة ولطيفة باستخدام Tailwind.
6. عدم إضافة أي تعقيد غير ضروري في نسخة الـMVP.

لا يجب تنفيذ النظام بالكامل في خطوة واحدة.

---

# 2. التقنيات المقترحة

- Laravel
- PHP
- MySQL
- Laravel Starter Kit / Authentication
- Laravel Sanctum إذا كان هناك احتياج فعلي للـ API/authentication token
- Tailwind CSS
- Blade في نسخة الـMVP
- Elasticsearch فقط إذا ظهر احتياج حقيقي للبحث المتقدم؛ لا يجب أن يكون جزءًا أساسيًا من الـMVP بدون حاجة واضحة.
- Laravel basics قدر الإمكان: Eloquent, Migrations, Form Requests, Policies عند الحاجة, Services عند الحاجة, Validation, Blade.

## ملاحظة مهمة

النظام في البداية Admin واحد فقط.

لا نحتاج إلى:

- نظام Roles معقد.
- Permissions system معقد.
- Multi-tenancy.
- API architecture كبيرة.
- Microservices.
- Event-driven architecture معقدة.

نريد Laravel application بسيطة ونظيفة وقابلة للتوسعة.

---

# 3. شكل الـMVP

الـMVP المقترح يحتوي على الأجزاء الأساسية فقط:

### Authentication

- Login
- Logout
- Admin واحد
- حماية صفحات النظام من المستخدم غير المسجل.

### العملاء

- إضافة عميل.
- تعديل عميل.
- عرض العملاء.
- فتح حساب/طلب جديد للعميل.
- عرض غرف/طلبات العميل.

### الغرف / الطلبات

- إنشاء غرفة.
- تحديد نوع الغرفة.
- تحديد سعر بيع الغرفة.
- إضافة الخامات المطلوبة.
- صرف الخامات.
- حساب تكلفة الخامات.
- حساب المدفوع والمتبقي.

### المخزون

- إضافة صنف.
- إضافة كمية للمخزون.
- تسجيل سعر الشراء.
- إنشاء Batch/دفعة لكل عملية شراء.
- عرض الكمية الحالية.
- صرف الخامات للغرف.
- تسجيل تكلفة الصرف حسب الدفعات.

### مدفوعات العملاء

- إضافة دفعة.
- ربط الدفعة بالغرفة.
- تحديث إجمالي المدفوع والمتبقي.
- تسجيل الحركة في الخزنة.

### المصروفات الإدارية

- إضافة مصروف.
- تحديد البند.
- القيمة.
- التاريخ.
- تسجيل خروج المبلغ من الخزنة.

### الخزنة

- تسجيل الحركات الداخلة والخارجة الناتجة عن العمليات.
- حساب الرصيد الحالي.
- عرض سجل الحركات.

### الشركاء

- إضافة شريك.
- تحديد النسبة.
- حساب نصيبه من الربح.
- تسجيل السحوبات.
- حساب المتبقي له.

---

# 4. ما الذي لا نحتاجه في الـMVP؟

لا نريد في البداية:

- Dashboard متقدم.
- Charts كثيرة.
- Notifications.
- PDF generation.
- Excel export.
- Advanced search.
- Elasticsearch integration إذا لم يكن ضروريًا.
- Supplier management كامل.
- Purchase orders كاملة.
- Manufacturing workflow متقدم.
- Accounting standards كاملة.
- Multiple branches.
- Multiple users.
- Roles & permissions.
- Audit system متقدم.
- Mobile app.
- API كاملة.
- تصميم UI معقد.

يمكن إضافة هذه الأشياء لاحقًا.

---

# 5. قاعدة مهمة جدًا في التنفيذ

## لا يتم تنفيذ أكثر من Module كبير في نفس المرحلة.

كل مرحلة يجب أن تمر بالدورة التالية:

1. فهم الـlogic.
2. تحديد الـdata المطلوبة.
3. تصميم/مراجعة قاعدة البيانات.
4. تنفيذ Backend.
5. تنفيذ أبسط UI ممكن.
6. تشغيل migrations.
7. إنشاء test data.
8. اختبار الحالات الطبيعية.
9. اختبار الحالات الخطأ.
10. إصلاح المشاكل.
11. التأكد أن الموديولات السابقة لم تتأثر.
12. فقط بعد ذلك الانتقال للمرحلة التالية.

---

# 6. Phase 0 — تجهيز المشروع

قبل بناء أي business logic:

- إنشاء Laravel project.
- إعداد قاعدة البيانات.
- إعداد `.env`.
- تشغيل Laravel.
- إعداد Tailwind.
- تثبيت Laravel Starter Kit المناسب.
- تفعيل Login/Logout.
- إنشاء Admin user.
- حماية routes.
- التأكد أن Authentication يعمل.

## اختبار Phase 0

يجب التأكد من:

- المشروع يعمل.
- Database connection تعمل.
- Login يعمل.
- Logout يعمل.
- المستخدم غير المسجل لا يستطيع دخول صفحات النظام.
- Admin يستطيع دخول النظام.

لا ننتقل للمرحلة التالية قبل نجاح هذه الاختبارات.

---

# 7. Phase 1 — Business Logic Documentation

قبل كتابة أغلب الكود، يتم إنشاء ملفات توثيق للـlogic.

يفضل إنشاء:

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

الهدف أن تكون هذه الملفات هي المرجع الأساسي قبل تنفيذ الـbackend.

أي قرار غير واضح يتم وضعه في documentation بدل تخمينه داخل الكود.

---

# 8. Phase 2 — Database Design

بعد تثبيت الـbusiness rules:

يتم تصميم Database schema.

العلاقات الأساسية المتوقعة:

```text
users

customers
    └── rooms
          ├── room_materials
          └── customer_payments

products/materials
    └── inventory_batches
          └── inventory_movements

expenses
expense_categories

cashbox_transactions

partners
    └── partner_withdrawals
```

قد تتغير أسماء الجداول أو العلاقات بعد مراجعة التفاصيل النهائية.

## أهم قاعدة

لا نبدأ بكتابة كل migrations مرة واحدة ثم نفترض أنها صحيحة.

يتم بناء Database incrementally.

---

# 9. Phase 3 — Inventory Foundation

المخزون يجب أن يكون من أول الأجزاء المهمة لأن تكلفة الغرفة تعتمد عليه.

نبدأ بـ:

1. Categories.
2. Materials/Products.
3. Inventory batches.
4. Stock additions.
5. Stock movements.
6. Current stock calculation.

## Batch costing

كل عملية شراء تنشئ Batch مستقلة.

مثال:

Batch A:

```text
quantity = 3
unit_cost = 100
```

Batch B:

```text
quantity = 10
unit_cost = 120
```

إذا تم صرف 5:

```text
3 × 100 = 300
2 × 120 = 240

total cost = 540
```

يجب ألا يتم فقدان بيانات الـBatch بعد الصرف.

---

# 10. Phase 4 — Customers + Rooms

بعد التأكد من المخزون:

- إنشاء Customer.
- إنشاء Room/Order.
- ربط الغرفة بالعميل.
- إضافة المواد المطلوبة.
- تنفيذ صرف المواد.
- حساب تكلفة الغرفة.

## اختبار أساسي

إنشاء عميل:

```text
أحمد
```

إنشاء غرفة:

```text
غرفة نوم
```

إضافة احتياج:

```text
5 ألواح
```

ثم التأكد من:

- نقص المخزون.
- تسجيل الصرف.
- حساب تكلفة الغرفة.
- حفظ تفاصيل الـBatches المستخدمة.

---

# 11. Phase 5 — Customer Payments

بعد أن تصبح الغرفة وتكلفتها تعمل:

- إضافة customer payment.
- تحديث المدفوع.
- حساب المتبقي.
- تسجيل دخول المال للخزنة.

مثال:

سعر الغرفة:

```text
30,000
```

العميل دفع:

```text
10,000
```

يجب أن يكون:

```text
paid = 10,000
remaining = 20,000
```

وفي نفس الوقت يجب إنشاء Cashbox transaction بقيمة:

```text
+10,000
```

---

# 12. Phase 6 — Administrative Expenses

بعد تشغيل دخل العملاء:

- إنشاء Expense Category.
- إضافة Expense.
- تسجيل قيمة المصروف.
- تسجيل خروج المبلغ من الخزنة.

مثال:

```text
Electricity
amount = 2,000
```

ينتج عنه:

```text
Expense = 2,000
Cashbox = -2,000
```

---

# 13. Phase 7 — Cashbox

بعد وجود أكثر من نوع من الحركات:

- Customer payments.
- Inventory purchases.
- Administrative expenses.

يتم بناء صفحة الخزنة.

يجب أن تعرض:

- Opening/starting balance إذا كان مطلوبًا.
- Total income.
- Total expenses.
- Current balance.
- Transaction history.

## قاعدة مهمة

الخزنة لا يجب أن تحتوي على أرقام يتم إدخالها يدويًا لتصحيح العمليات الطبيعية.

الأفضل أن تأتي حركاتها من العمليات الأصلية.

مثال:

Customer Payment → Cashbox transaction.

Inventory Purchase → Cashbox transaction.

Expense → Cashbox transaction.

Partner Withdrawal → Cashbox transaction.

---

# 14. Phase 8 — Partners

بعد التأكد أن الربح يمكن حسابه:

- إضافة partners.
- تحديد percentage.
- حساب نصيب كل شريك.
- تسجيل withdrawals.
- حساب remaining balance.

مثال:

```text
Net Profit = 25,000

Partner A = 20%

Share = 5,000
```

إذا سحب:

```text
2,000
```

يصبح:

```text
Share = 5,000
Withdrawn = 2,000
Remaining = 3,000
```

والـ2,000 تسجل أيضًا كخروج من الخزنة.

---

# 15. Phase 9 — Profit Calculation

بعد اكتمال العمليات السابقة يتم بناء الحساب النهائي.

الـMVP يجب أن يستطيع حساب:

```text
Revenue
- Cost of used materials
- Administrative expenses
= Net Profit
```

ويجب الانتباه إلى الفرق بين:

- Cash balance.
- Revenue.
- Material purchases.
- Material consumption cost.
- Administrative expenses.
- Net profit.

شراء الخامة لا يعني أن كامل قيمة الشراء أصبحت تكلفة إنتاج فورًا.

تكلفة الإنتاج تظهر عند استخدام الخامة في الغرفة.

---

# 16. Phase 10 — Minimal UI

بعد التأكد من الـlogic والـdatabase:

يتم عمل UI بسيط باستخدام Tailwind.

الأولوية:

- Forms واضحة.
- Tables واضحة.
- أزرار Add/Edit/View.
- رسائل نجاح وخطأ.
- Validation errors.
- Navigation بسيطة.

لا نحتاج تصميمًا مبهرًا في الـMVP.

المهم أن المستخدم يستطيع تشغيل النظام بسهولة.

---

# 17. الصفحات الأساسية للـMVP

يفضل أن تكون الصفحات تقريبًا:

```text
/login

/customers
/customers/create
/customers/{customer}

/rooms/create
/rooms/{room}

/inventory
/inventory/materials
/inventory/materials/create
/inventory/purchases

/payments

/expenses
/expenses/create

/cashbox

/partners
/partners/{partner}
```

الـDashboard يمكن أن تكون بسيطة جدًا أو يتم تأجيلها.

---

# 18. طريقة الاختبار

بعد كل Phase يجب عمل Test Scenario واضح.

مثال:

## Scenario 1 — Inventory

1. إضافة 3 ألواح بسعر 100.
2. إضافة 10 ألواح بسعر 120.
3. التأكد أن الكمية = 13.
4. إنشاء غرفة تحتاج 5.
5. صرف 5.
6. التأكد أن:
   - 3 خرجت من Batch الأولى.
   - 2 خرجت من Batch الثانية.
   - تكلفة الصرف = 540.
   - المتبقي = 8 ألواح.
   - Batch الأولى = 0.
   - Batch الثانية = 8.

---

# 19. Integration Test

بعد بناء عدة Modules، يجب اختبار دورة كاملة:

```text
شراء خامات
      ↓
المخزون
      ↓
إنشاء غرفة
      ↓
صرف خامات
      ↓
حساب تكلفة الغرفة
      ↓
دفع العميل
      ↓
الخزنة
      ↓
مصروف إداري
      ↓
الخزنة
      ↓
حساب الربح
      ↓
حساب نصيب الشركاء
      ↓
سحب شريك
      ↓
الخزنة + حساب الشريك
```

يجب تنفيذ هذه الدورة كاملة قبل اعتبار الـMVP جاهزًا.

---

# 20. أسلوب التعامل مع الأخطاء

إذا ظهر Error أثناء تنفيذ Phase معينة:

لا يتم القفز مباشرة إلى تعديل عشوائي في أجزاء أخرى من المشروع.

يجب:

1. تحديد الـPhase التي ظهر فيها الخطأ.
2. تحديد الـroot cause.
3. إصلاح السبب.
4. إعادة اختبار الـPhase.
5. إعادة اختبار الـPhases السابقة.
6. التأكد من عدم وجود regression.
7. ثم الانتقال للمرحلة التالية.

---

# 21. طريقة إعطاء المهام للـCoding Agent

لا يتم إرسال Prompt واحد مثل:

> Build the entire system.

بدل ذلك يتم إعطاء Agent مهمة واحدة في كل مرة.

مثال:

### Task 1

> Setup Laravel authentication and Admin access only.

بعد الانتهاء والاختبار:

### Task 2

> Create and implement the inventory database and logic.

ثم الاختبار.

ثم:

### Task 3

> Implement customers and rooms.

وهكذا.

كل Task يجب أن يكون:

- محدد.
- مستقل قدر الإمكان.
- له Acceptance Criteria.
- له Test Scenarios.
- ممنوع عليه تعديل أجزاء غير مطلوبة إلا إذا كان ذلك ضروريًا ومبررًا.

---

# 22. Acceptance Criteria

كل Phase لا تعتبر مكتملة بمجرد أن الكود يعمل بدون Syntax Error.

يجب أن:

- Migration تعمل.
- CRUD يعمل عند الحاجة.
- Validation تعمل.
- Business rules تعمل.
- العلاقات صحيحة.
- الحالات الخطأ تعمل.
- البيانات لا تتكرر بدون سبب.
- الحسابات صحيحة.
- العمليات المرتبطة بالخزنة صحيحة.
- العمليات المرتبطة بالمخزون صحيحة.
- الاختبارات اليدوية/الآلية المحددة للمرحلة تنجح.

---

# 23. فلسفة بناء الـMVP

الهدف ليس بناء أقل عدد ممكن من الصفحات.

الهدف هو بناء **أقل نسخة كاملة منطقيًا**.

أي أن الـMVP يجب أن يستطيع تنفيذ دورة حقيقية:

```text
شراء خامة
→ دخول المخزون
→ إنشاء غرفة
→ صرف الخامة
→ حساب تكلفة الغرفة
→ تحصيل أموال من العميل
→ تسجيل المصروفات
→ حساب الخزنة
→ حساب الربح
→ حساب نصيب الشركاء
→ تسجيل السحب
```

إذا استطاع النظام تنفيذ هذه الدورة بشكل صحيح ومستقر، فلدينا MVP حقيقي قابل للعرض على العميل والتطوير بعد ذلك.

---

# 24. النتيجة المطلوبة

في نهاية الـMVP نريد تطبيق Laravel:

- يعمل بشكل مستقر.
- يحتوي على Admin Login.
- يحتوي على العملاء.
- يحتوي على الغرف.
- يحتوي على المخزون.
- يدعم Batch costing.
- يحتوي على مدفوعات العملاء.
- يحتوي على المصروفات.
- يحتوي على الخزنة.
- يحتوي على الشركاء والسحوبات.
- يحسب تكلفة الغرف.
- يحسب الربح.
- لا يحسب نفس الحركة المالية مرتين.
- قابل للتوسع لاحقًا.
- UI بسيط باستخدام Tailwind.
- لا يحتوي على تعقيدات غير ضرورية.

**الأولوية دائمًا: Correctness > Business Logic > Data Integrity > Testing > UI polish.**
