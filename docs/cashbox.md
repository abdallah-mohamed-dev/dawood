# الخزنة (Cashbox)

## حركة الخزنة (CashboxTransaction)

| الحقل | النوع | ملاحظات |
|---|---|---|
| `type` | enum | `in` (دخول) / `out` (خروج) |
| `amount` | مبلغ (قروش) | دائمًا **موجب** — الاتجاه يُحدَّد من `type` لا من إشارة الرقم |
| `source_type` / `source_id` | polymorphic nullable | العملية الأصلية؛ `null` للرصيد الافتتاحي فقط |
| `kind` | نص | `opening_balance` / `customer_payment` / `inventory_purchase` / `expense` / `partner_withdrawal` |
| `occurred_at` | تاريخ | تاريخ العملية الفعلي (منفصل عن `created_at`) |

## القاعدة الذهبية: لا إدخال يدوي

**لا يوجد أي مسار في التطبيق ينشئ `CashboxTransaction` مباشرة من واجهة الخزنة.** كل حركة تأتي حصريًا من `CashboxService` استدعاها كود العملية الأصلية:

```text
دفعة عميل        → CashboxService::recordIn(kind: customer_payment)
شراء مخزون       → CashboxService::recordOut(kind: inventory_purchase)
مصروف إداري      → CashboxService::recordOut(kind: expense)
سحب شريك         → CashboxService::recordOut(kind: partner_withdrawal)
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
