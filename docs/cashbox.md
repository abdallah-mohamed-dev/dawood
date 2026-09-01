# الخزنة (Cashbox)

## حركة الخزنة (CashboxTransaction)

| الحقل | النوع | ملاحظات |
|---|---|---|
| `type` | enum | `in` (دخول) / `out` (خروج) |
| `amount` | مبلغ (قروش) | دائمًا **موجب** — الاتجاه يُحدَّد من `type` لا من إشارة الرقم |
| `source_type` / `source_id` | polymorphic nullable | العملية الأصلية؛ `null` للرصيد الافتتاحي فقط |
| `kind` | نص | `opening_balance` / `customer_payment` / `inventory_purchase` / `expense` / `partner_withdrawal` / `room_labor` / `room_expense` |
| `payment_method` | enum nullable | كاش / محفظة / انستاباي / شيك / فيزا — انظر أدناه |
| `occurred_at` | تاريخ | تاريخ العملية الفعلي (منفصل عن `created_at`) |

## طريقة الدفع (payment_method)

كل حركة تحمل **كيف** تحرّكت الفلوس: `cash` · `wallet` · `instapay` · `cheque` · `card`. الافتراضي `cash`.

### لماذا على `cashbox_transactions` وحدها؟

الوسيلة صفة **الحركة النقدية** لا صفة العملية التجارية، وكل عملية تلمس فلوس تكتب صفًا واحدًا بالضبط في الخزنة. تكرار العمود على الجداول الخمسة المصدر (`customer_payments` · `inventory_batches` · `expenses` · `partner_withdrawals` · `room_costs`) كان سيعني خمس migrations وخمس فرص لانحراف البيانات، بلا أي مكسب. صفحات التعديل تقرأ القيمة الحالية عبر علاقة `morphOne` اسمها `cashboxTransaction()`.

العمود **nullable** لأن الصفوف المسجَّلة قبل وجوده لا وسيلة لها؛ العرض والتجميع يتعاملان معها تحت مفتاح `unknown`.

### الشيك

الشيك وسيلة دفع عادية تدخل الخزنة **بتاريخ تسجيلها**. النظام لا يمثّل حالة "تحت التحصيل" عمدًا — قرار معتمد للـMVP.

### التقسيم للعرض فقط

`CashboxService::breakdownByMethod()` ترجّع الداخل والخارج لكل وسيلة في استعلام `GROUP BY` واحد.

**الرصيد يبقى رقمًا واحدًا.** التقسيم عرض فقط، وهذه **ليست محافظ منفصلة** بأرصدة مستقلة: لا توجد عمليات تحويل بينها، ولا رصيد افتتاحي لكل واحدة، ولا منع للسحب من وسيلة "فارغة". لهذا لا يُعرض صافٍ لكل وسيلة — رقم مثل "فيزا −3,000" كان سيبدو دَينًا وهو ليس كذلك.

## الصفحة: جدولان منفصلان

`/cashbox` تعرض الداخل والخارج في **جدولين متجاورين** (تحت بعضهما على الموبايل). لا يوجد عرض زمني مدمج.

كل جدول له **باجينيشن مستقل** (`in_page` / `out_page`) مع `withQueryString()` — بمعامل صفحة واحد كان الجدولان سيتحركان معًا.

## عمود "البند" — التفصيلي لا العام

`CashboxTransaction::detailedLabel()` تقرأ من **سجل المصدر الحي** لا من نص مخزَّن:

| المصدر | ما يُعرض |
|---|---|
| `Expense` | اسم بند المصروف (كهرباء، إيجار) |
| `CustomerPayment` | اسم العميل — نوع الغرفة |
| `InventoryBatch` | اسم المادة |
| `PartnerWithdrawal` | اسم الشريك |
| `RoomCost` | مصنعية/مصروف إضافي — نوع الغرفة |
| رصيد افتتاحي أو مصدر مفقود | `kind->label()` |

**لماذا القراءة الحية بدل تخزين النص؟** لأن تغيير اسم عميل أو مادة كان سيترك في الخزنة نصًا قديمًا لا يطابق أي شيء في النظام، وكان سيحتاج backfill للصفوف الموجودة. الثمن: `CashboxController` **ملزم** بعمل eager loading بـ`morphWith` على العلاقة `source` — بدونه الصفحة تصبح N+1 ثقيلًا (استعلام لكل صف + استعلام لعلاقة كل مصدر). يوجد اختبار يثبت أن عدد الاستعلامات لا ينمو مع عدد الصفوف.

## القاعدة الذهبية: لا إدخال يدوي

**لا يوجد أي مسار في التطبيق ينشئ `CashboxTransaction` مباشرة من واجهة الخزنة.** كل حركة تأتي حصريًا من `CashboxService` استدعاها كود العملية الأصلية:

```text
دفعة عميل        → CashboxService::recordIn(kind: customer_payment)
شراء مخزون       → CashboxService::recordOut(kind: inventory_purchase)
مصروف إداري      → CashboxService::recordOut(kind: expense)
سحب شريك         → CashboxService::recordOut(kind: partner_withdrawal)
مصنعية/مصروف غرفة → CashboxService::recordOut(kind: room_labor | room_expense)
```

صفحة `/cashbox` **للقراءة فقط**.

## الرصيد الافتتاحي (Opening Balance)

استثناء وحيد لقاعدة "كل حركة لها مصدر": الرصيد الافتتاحي، `source_id = null`، `kind = opening_balance`.

- يُدخَل يدويًا مرة واحدة عبر نموذج مستقل.
- **قابل للتعديل، غير قابل للتكرار** — محاولة تعيينه مرة ثانية **تُحدِّث** الصف الموجود بدل إنشاء صف جديد. `CashboxService::setOpeningBalance()` تضمن هذا (`updateOrCreate` على نوع `opening_balance`).

### مثال

```text
تعيين رصيد افتتاحي 5,000 → balance = 5,000، عدد صفوف opening_balance = 1
إعادة تعيينه إلى 7,000    → balance = 7,000، عدد صفوف opening_balance ما زال 1 (تحديث لا إضافة)
```

## حساب الرصيد

```text
balance()  = SUM(amount WHERE type = in) - SUM(amount WHERE type = out)
totalIn()  = SUM(amount WHERE type = in)
totalOut() = SUM(amount WHERE type = out)
```

## الحذف والتعديل

عند حذف/تعديل العملية الأصلية (دفعة، مصروف، سحب)، حركة الخزنة المرتبطة تُحذف/تُحدَّث في نفس الـtransaction عبر `CashboxService::removeFor()` / `updateFor()`. لا تبقى أبدًا حركة خزنة بلا مصدر حي (باستثناء الرصيد الافتتاحي).

## مثال متكامل

```text
رصيد افتتاحي:        10,000  → balance = 10,000
شراء 3 @ 100:         -300   → balance = 9,700
شراء 10 @ 120:       -1,200  → balance = 8,500
دفعة عميل:           +10,000 → balance = 18,500
مصروف كهرباء:         -2,000 → balance = 16,500
```

كل رقم في هذا المثال له مصدر واحد فقط، ولا يُحسب مرتين.
