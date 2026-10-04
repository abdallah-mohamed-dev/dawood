# 014 — الدفعة الخامسة: المخزن (إلغاء FIFO)

| | |
|---|---|
| **الحالة** | معتمد — جاهز للتنفيذ |
| **اتكتب في** | 2026-10-04 |
| **اتقفل في** | — |
| **بيغيّر منطق أعمال؟** | **نعم — ده بيغيّر قلب نظام التكلفة** → بند `USER-GUIDE.md` إلزامي |
| **يعتمد على** | `specs/013` (مكتمل) |

---

## ⚠️ اقرا ده الأول

**ده أخطر تاسك في المشروع كله.** بيستبدل نظام تكلفة المخزون بالكامل.

1. **لو أي حاجة مش واضحة — قف واسأل المستخدم. متجتهدش.**
2. اقرا `CLAUDE.md` و`start-agent/PITFALLS.md` و`docs/inventory-costing.md` قبل أي كود.
3. **دالة `InventoryService::cost()` متتغيرش ولا حرف.** هي الصيغة الوحيدة لضرب كمية × سعر في السيستم كله، وهتفضل مستخدمة في كل مكان هنا.
4. **`CashboxService` هو الكاتب الوحيد** لجدول الخزنة.
5. نفّذ بالترتيب: migrations → models → service → requests → controllers → views → tests.
6. بعد كل تاسك: `php artisan test --compact` + `vendor/bin/pint --dirty --format agent`.

### الداتا الحالية تجريبية — بس الخزنة لأ

الداتا التجريبية مسموح تتحوّل بأي شكل. **لكن ممنوع أي migration تغيّر رصيد الخزنة** — الحركات القديمة بتتنقل، ما بتتحذفش. فيه خطوة مخصصة لده في 14.1.

---

## الوضع الحالي vs المطلوب

| | دلوقتي | المطلوب |
|---|---|---|
| تخزين الكمية | جدول `inventory_batches`، كل شراء = صف بسعره | عمود `quantity` على `materials` |
| السعر | لكل دفعة سعرها الخاص | عمود `unit_price` واحد على `materials` |
| الصرف | FIFO عبر الدفعات بأسعارها | الكمية × السعر الحالي |
| إضافة مخزون | عملية "شراء" في صفحة منفصلة | المستخدم يزوّد الكمية من صفحة المخزن |
| تنقيص الكمية | مش موجود | يخصم الكمية **ويزوّد الخزنة** (الخامة اتباعت زي ما هي) |
| تغيير السعر | مستحيل بعد الصرف | حر في أي وقت، **بلا أي حركة فلوس** |
| تكلفة خامة اتصرفت | محسوبة من الدفعات | **اتسجلت لحظة الصرف وما بتتغيرش أبدًا** |

### القواعد الحاكمة (قرارات معتمدة من المستخدم)

1. **السعر موحد للخامة الواحدة.** مفيش طبقات ومفيش دفعات.
2. **تغيير السعر لوحده ما بيحركش فلوس خالص** — بس قيمة المخزن بتتحدّث، لأن السوق غلي والبضاعة اللي عندك بقت تساوي أكتر. ده مقصود.
3. **الكمية بتزيد** → يتخصم من الخزنة (الزيادة × السعر).
4. **الكمية بتقل يدويًا** → يتزوّد في الخزنة (النقص × السعر)، على أساس إن الخامة اتباعت زي ما هي.
5. **بيع الخامة مش ربح.** فلوس بتدخل الخزنة وبس. **ممنوع تدخل في `ProfitService` بأي شكل.**
6. **اللي اتصرف خلاص اتصرف.** تكلفة الخامة في الغرفة اتسجلت في `room_materials.cost` لحظة الصرف، وأي تغيير سعر بعد كده **ما بيلمسهاش نهائيًا**.

---

# التاسك 14.1 — قاعدة البيانات

> ⚠️ **SQLite:** كل `dropForeign` / `dropIndex` / `dropColumn` في **`Schema::table` منفصلة**. راجع `PITFALLS.md`.
> الترتيب تحت **إلزامي** — نقل حركات الخزنة لازم يحصل **قبل** حذف جدول الدفعات.

- [ ] **14.1.1** Migration `add_stock_columns_to_materials_table`:
  ```php
  $table->bigInteger('quantity')->default(0)->after('unit');      // ×1000
  $table->bigInteger('unit_price')->default(0)->after('quantity'); // قروش
  ```

- [ ] **14.1.2** نفس الـmigration (أو واحدة بعدها) — **تعبئة من الدفعات**:
  - `quantity` = `SUM(remaining_quantity)` لكل دفعات المادة
  - `unit_price` = `unit_cost` بتاع **أحدث** دفعة (بـ`purchase_date` ثم `id`)، و`0` لو مفيش دفعات

- [ ] **14.1.3** Migration `repoint_inventory_cashbox_sources` — **الخطوة دي بتحمي رصيد الخزنة**:
  حركات الخزنة بتاعة الشراء مصدرها `App\Models\InventoryBatch`. الجدول ده هيتحذف، فلازم تتنقل الأول.
  - لكل دفعة: هات حركة المخزون `in` بتاعتها (`inventory_movements.batch_id = batch.id AND type = 'in'`)
  - حدّث `cashbox_transactions` اللي `source_type = 'App\Models\InventoryBatch'` و`source_id = batch.id` →
    `source_type = 'App\Models\InventoryMovement'`, `source_id = movement.id`
  - **ممنوع تحذف أي صف خزنة. ممنوع تغيّر أي مبلغ.**
  - لو دفعة مالهاش حركة `in` (مفروض مستحيل) — سيب صف الخزنة زي ما هو واكتب ده في سجل الانحرافات

- [ ] **14.1.4** Migration `drop_batch_id_from_inventory_movements` — بالترتيب ده في `Schema::table` منفصلة:
  1. `dropForeign(['batch_id'])`
  2. `dropIndex(['batch_id'])`
  3. `dropColumn('batch_id')`

- [ ] **14.1.5** Migration `drop_inventory_batches_table` — `Schema::dropIfExists('inventory_batches')`

- [ ] **14.1.6** حذف الملفات: `app/Models/InventoryBatch.php`، `database/factories/InventoryBatchFactory.php`

- [ ] **14.1.7** `app/Enums/InventoryMovementType.php` — ضيف `case Sold = 'sold';` بـ`label()` = **`'بيع'`**

- [ ] **14.1.8** `app/Enums/CashboxTransactionKind.php` — ضيف `case MaterialSale = 'material_sale';` بـ`label()` = **`'بيع خامة'`**

- [ ] **14.1.9** `app/Http/Controllers/BackupController.php` — شيل `InventoryBatch::class` من `EXPORTABLE_MODELS`

### الاختبارات

- [ ] **14.1.10** `tests/Feature/Inventory/InventoryMigrationTest.php`:
  - بعد الـmigrations، كمية المادة = مجموع المتبقي من دفعاتها (رقم مرجعي محسوب يدويًا)
  - سعر المادة = سعر أحدث دفعة
  - **رصيد الخزنة قبل الـmigration = رصيد الخزنة بعدها** ← أهم اختبار في التاسك ده
  - عدد صفوف `cashbox_transactions` ما اتغيرش
  - مادة من غير دفعات → كمية 0 وسعر 0

---

# التاسك 14.2 — إعادة كتابة `InventoryService`

**الهدف:** الخدمة تشتغل على `materials.quantity` و`materials.unit_price` بدل الدفعات.

### القواعد

- **كل عملية بتلمس الكمية = `DB::transaction` واحدة + `lockForUpdate()` على صف المادة من جوه الترانزاكشن.**
- **كل عملية بتحرك فلوس بتعدي على `CashboxService` حصرًا**، ومصدر الحركة (`source`) = صف `InventoryMovement` اللي اتعمل.
- **`cost()` متتغيرش.**

### الدوال

- [ ] **14.2.1** `addStock(Material $material, int $quantity, int $unitPrice, DateTimeInterface|string $date, PaymentMethod $method): InventoryMovement`
  - `$quantity <= 0` → `InvalidArgumentException`
  - `$unitPrice <= 0` → `InvalidArgumentException`
  - `$cost = $this->cost($quantity, $unitPrice)`; لو `<= 0` → `InvalidArgumentException` برسالة إن التكلفة بتقرّب لصفر *(نفس حماية `purchase()` القديمة — سيبها)*
  - جوه الترانزاكشن: اقفل المادة → **`unit_price = $unitPrice`** (السعر الجديد بيبقى السعر الحالي) → `quantity += $quantity` → أنشئ حركة `In` بالكمية والتكلفة → `cashbox->recordOut($movement, $cost, InventoryPurchase, $date, method: $method)`

- [ ] **14.2.2** `reduceStock(Material $material, int $quantity, DateTimeInterface|string $date, PaymentMethod $method): InventoryMovement`
  - `$quantity <= 0` → `InvalidArgumentException`
  - جوه الترانزاكشن: اقفل المادة → لو `quantity` المتاحة أقل من المطلوب → `InsufficientStockException`
  - `$amount = $this->cost($quantity, $material->unit_price)`; لو `<= 0` → `InvalidArgumentException`
  - `quantity -= $quantity` → حركة `Sold` → **`cashbox->recordIn($movement, $amount, MaterialSale, $date, method: $method)`**
  - ⚠️ **ممنوع تلمس `ProfitService`.** البيع ده فلوس داخلة بس، مش إيراد.

- [ ] **14.2.3** `changePrice(Material $material, int $unitPrice): void`
  - `$unitPrice <= 0` → `InvalidArgumentException`
  - تحديث `unit_price` بس. **ولا حركة مخزون، ولا حركة خزنة، ولا أي أثر مالي.** التغيير بيتسجل تلقائي في سجل العمليات (من الدفعة 013).

- [ ] **14.2.4** `issue(Material $material, int $quantity, Model $related, DateTimeInterface|string $date): array`
  - التوقيع يفضل زي ما هو بس المرجَّع يبقى `['cost' => int]` بس — **`allocations` تتشال**
  - جوه الترانزاكشن: اقفل المادة → لو المتاح أقل من المطلوب → `InsufficientStockException` (بنفس الباراميترات الحالية)
  - `$cost = $this->cost($quantity, $material->unit_price)` → `quantity -= $quantity` → حركة `Out` بالتكلفة دي ومربوطة بـ`$related`
  - **ولا حركة خزنة** — الفلوس خرجت وقت الإضافة، والصرف نقل داخلي من أصل لأصل

- [ ] **14.2.5** `returnIssued(Model $related): void`
  - لكل حركة `Out` مربوطة بـ`$related`: اقفل المادة → `quantity += movement.quantity` → أنشئ حركة `ReturnedToStock` **بنفس كمية وتكلفة الحركة الأصلية**
  - الحركات الأصلية `Out` **ما بتتحذفش** (سجل)
  - **ولا حركة خزنة**

- [ ] **14.2.6** `currentStock(Material $material): int` → `$material->getRawOriginal('quantity')`

- [ ] **14.2.7** `stockByMaterialIds(?array $materialIds = null): Collection` → من `materials` مباشرة (`pluck('quantity', 'id')`)، مع `whereIn` لما تتبعت IDs

- [ ] **14.2.8** `stockValue(): int` → مجموع `cost(quantity, unit_price)` لكل مادة كميتها > 0

- [ ] **14.2.9** `stockValueByType(): array` — **جديد**. يرجّع:
  ```php
  ['by_type' => [<material_type_id> => ['label' => string, 'value' => int], ...], 'total' => int]
  ```
  كل نوع وقيمته، + الإجمالي. **الإجمالي = مجموع الأنواع بالظبط** (نفس دالة `cost()`، مفيش جمع تاني بطريقة مختلفة).

- [ ] **14.2.10** **احذف** `deletePurchase()` و`purchasesSummary()` — مالهمش معنى من غير دفعات

### الاختبارات

- [ ] **14.2.11** `tests/Feature/Services/InventoryServiceTest.php` — اتكتب من أول وجديد:
  - `addStock` بيزوّد الكمية، بيحدّث السعر، وبيخصم من الخزنة **(رقم مرجعي: 5 × 550.00 ج.م = 275000 قرش)**
  - `addStock` بسعر مختلف عن الحالي بيخلي السعر الجديد هو الحالي
  - `addStock` بكمية أو سعر ≤ 0 → استثناء
  - `addStock` بتكلفة بتقرّب لصفر → استثناء برسالة واضحة
  - `reduceStock` بيقلل الكمية **وبيزوّد الخزنة** برقم مرجعي
  - `reduceStock` بكمية أكبر من المتاح → `InsufficientStockException`
  - **`reduceStock` ما بيغيّرش `ProfitService::netProfit()` ولا `revenue()`** ← اختبار إلزامي
  - `changePrice` بيغيّر السعر **وما بيعملش ولا حركة خزنة ولا حركة مخزون** (عدّ الصفوف قبل وبعد)
  - **`changePrice` بيغيّر `stockValue()` من غير ما يغيّر رصيد الخزنة** ← اختبار إلزامي، ده جوهر القرار
  - **`changePrice` ما بيغيّرش `room_materials.cost` لخامة اتصرفت قبل كده** ← أهم اختبار في الدفعة كلها
  - `issue` بيخصم الكمية وبيسجل التكلفة بالسعر الحالي (رقم مرجعي)
  - `issue` بكمية أكبر من المتاح → `InsufficientStockException`
  - `issue` ما بيعملش حركة خزنة
  - `returnIssued` بيرجّع الكمية بالظبط وبيسجل حركة مرتجع، وما بيعملش حركة خزنة
  - `returnIssued` بعد تغيير السعر بيرجّع **الكمية** الصح (التكلفة في الحركة بسعر الصرف الأصلي)
  - `stockValue` برقم مرجعي
  - `stockValueByType` — الإجمالي = مجموع الأنواع بالظبط

---

# التاسك 14.3 — تبسيط `RoomMaterialService`

- [ ] **14.3.1** `issue()` — `$result['cost']` زي ما هو. **شيل أي استخدام لـ`allocations`.**
- [ ] **14.3.2** راجع إن `room_materials.cost` لسه بيتراكم صح عبر أكتر من صرف
- [ ] **14.3.3** باقي الدوال زي ما هي — **التاسك ده مش بيغيّر قواعد الغرفة** (التعديل بعد الصرف جاي في `specs/016`)

### الاختبارات

- [ ] **14.3.4** في `tests/Feature/Services/RoomMaterialServiceTest.php`:
  - صرف على مرتين بأسعار مختلفة بين المرتين → `cost` = مجموع التكلفتين **كل واحدة بسعر وقتها** (رقم مرجعي محسوب يدويًا)
  - صرف أكتر من المطلوب → الاستثناء الموجود زي ما هو

---

# التاسك 14.4 — صفحة المخزن

**الهدف:** صفحة المخزن تبقى المكان الوحيد لكل شغل المخزن.

### 14.4.1 كروت الإجماليات

- [ ] **14.4.1** أعلى الصفحة: **تلات كروت** — قيمة الخامات · قيمة الاكسسوارات · الإجمالي المجمّع. من `stockValueByType()`.
  لو فيه نوع تالت بعدين، الكروت تتولد من الـloop تلقائيًا (متعملهاش hard-code بنوعين).

### 14.4.2 فلتر النوع

- [ ] **14.4.2** فلتر "النوع" جنب البحث الموجود — الكل / خامة / اكسسوار (من جدول `material_types`)
- [ ] **14.4.3** الفلتر والبحث بيشتغلوا مع بعض، ومع الباجينيشن
- [ ] **14.4.4** الكروت **بتعكس الكل دايمًا، مش المفلتر** — وتحتها سطر صغير يقول كده بالعربي

### 14.4.3 أعمدة الجدول

- [ ] **14.4.5** الأعمدة: الاسم · النوع · الكمية الحالية · سعر الوحدة · **القيمة** (الكمية × السعر) · إجراءات
- [ ] **14.4.6** استخدم `<x-money>` و`<x-quantity>` — **ممنوع تنسيق أرقام يدوي**

### 14.4.4 فورم التعديل

- [ ] **14.4.7** `resources/views/inventory/materials/edit.blade.php` — الحقول: الاسم · النوع · **سعر الوحدة** · **الكمية**
- [ ] **14.4.8** `MaterialController::update` — **ده أهم كونترولر في الدفعة. المنطق بالظبط:**
  1. حوّل السعر والكمية بـ`toScaledInt` كل واحد في `try/catch` منفصل برسالته العربية
  2. حدّث الاسم والنوع عاديًا
  3. **لو السعر اتغيّر** → `changePrice()` **الأول**
  4. **بعدين** احسب فرق الكمية `$delta = الجديدة − الحالية`:
     - `$delta > 0` → `addStock($material, $delta, السعر الجديد, today, المدفوع بيه)`
     - `$delta < 0` → `reduceStock($material, -$delta, today, المدفوع بيه)`
     - `$delta === 0` → مفيش حاجة
  5. **الترتيب ده إلزامي:** السعر الجديد هو اللي بتتحاسب بيه الزيادة أو النقص، مش القديم.
- [ ] **14.4.9** الفورم فيه `<x-payment-method-select>` — الزيادة والنقص بيحركوا فلوس فلازم طريقة الدفع
- [ ] **14.4.10** الفورم يعرض تنبيه عربي واضح بيشرح إن تغيير الكمية هيحرّك فلوس من/إلى الخزنة
- [ ] **14.4.11** فورم الإضافة السريعة للمادة الجديدة: الاسم · النوع · **سعر الوحدة** · **الكمية المبدئية** (اختيارية). لو الكمية المبدئية > 0 → `addStock` بعد الإنشاء في نفس الطلب.

### 14.4.5 التحقق

- [ ] **14.4.12** `StoreMaterialRequest` / `UpdateMaterialRequest`:
  - `unit_price` → `required` + `regex:MoneyCast::validationPattern()` + أكبر من صفر
  - `quantity` → `required` + `regex:QuantityCast::validationPattern()` + **مش سالبة** (الصفر مسموح)
  - `payment_method` → `required` + `Rule::enum(PaymentMethod::class)` *(في التعديل بس، والإضافة لو فيه كمية مبدئية)*
  - كل الرسايل عربية

### 14.4.6 حذف المادة

- [ ] **14.4.13** حذف مادة ليها كمية في المخزن أو اتصرفت لغرفة **ممنوع** برسالة عربية واضحة. راجع السلوك الموجود وحافظ عليه.

### الاختبارات

- [ ] **14.4.14** `tests/Feature/Inventory/MaterialStockTest.php`:
  - زيادة الكمية من فورم التعديل بتخصم من الخزنة برقم مرجعي
  - تنقيص الكمية بيزوّد الخزنة برقم مرجعي
  - **تغيير السعر والكمية في نفس الحفظ → الزيادة بتتحاسب بالسعر الجديد** ← اختبار إلزامي، برقم مرجعي
  - تغيير السعر لوحده: الخزنة ما اتغيرتش، وقيمة المخزن اتغيرت
  - تنقيص لكمية أكبر من المتاح → خطأ عربي والكمية ما اتغيرتش والخزنة ما اتغيرتش
  - سعر أو كمية بشكل غلط → خطأ فاليديشن عربي
  - كمية سالبة → خطأ
  - التلات كروت بتعرض أرقام صح، والإجمالي = مجموع النوعين
  - الفلتر بالنوع بيرجّع المواد الصح
  - الفلتر + البحث مع بعض بيشتغلوا
  - الكروت **ما بتتأثرش** بالفلتر

---

# التاسك 14.5 — صفحة سجل حركة المخزن

**الهدف:** المستخدم يشوف كل حركة دخلت أو خرجت من المخزن. ده طلب صريح ("logs كامل للمخزن").

- [ ] **14.5.1** `app/Http/Controllers/Inventory/MovementController.php` — `index(): View`
  فلاتر: المادة (`q` بالاسم) · النوع (وارد/صادر/مرتجع/بيع) · من تاريخ · إلى تاريخ. `paginate(25)` مع `with('material')`.
- [ ] **14.5.2** الراوت: `Route::get('movements', [MovementController::class, 'index'])->name('movements.index');` جوه مجموعة `inventory`
- [ ] **14.5.3** `resources/views/inventory/movements/index.blade.php` — شريط الفلاتر **بنسخ نمط `inventory/purchases/index.blade.php` الحالي** قبل ما يتحذف
  الأعمدة: التاريخ · المادة · نوع الحركة · الكمية · التكلفة · الجهة (اسم الغرفة لو الحركة مربوطة بغرفة، وإلا `—`)
- [ ] **14.5.4** **فواصل الشهور** بنفس نمط `expenses/index.blade.php` بالحرف
- [ ] **14.5.5** لينك "سجل الحركة" من صفحة المخزن
- [ ] **14.5.6** لينك مفلتر على المادة من كل صف في جدول المخزن

### الاختبارات

- [ ] **14.5.7** `tests/Feature/Inventory/MovementLogTest.php`:
  - إضافة كمية بتظهر كحركة "وارد" بالكمية والتكلفة الصح
  - الصرف لغرفة بيظهر كـ"صادر" ومعاه اسم الغرفة
  - الإرجاع بيظهر كـ"مرتجع"
  - التنقيص اليدوي بيظهر كـ"بيع"
  - الفلاتر كلها بتشتغل
  - فواصل الشهور بتظهر

---

# التاسك 14.6 — حذف صفحة المشتريات القديمة

**الهدف:** الصفحة القديمة كانت مبنية على الدفعات اللي اتحذفت. بتتشال دلوقتي، وبترجع كصفحة "الناقص" في `specs/018`.

- [ ] **14.6.1** احذف: `app/Http/Controllers/Inventory/PurchaseController.php`، `resources/views/inventory/purchases/` كامل، `app/Http/Requests/Inventory/StorePurchaseRequest.php` (لو موجود)
- [ ] **14.6.2** `routes/web.php` — شيل مسار `purchases` وimport الكونترولر
- [ ] **14.6.3** `resources/views/components/app-layout.blade.php` — شيل عنصر "المشتريات" من `$navItems` **مؤقتًا**، وحط مكانه "سجل المخزن" (`inventory.movements.index`)
- [ ] **14.6.4** احذف `tests/Feature/Inventory/PurchaseTest.php` وأي اختبار تاني على الصفحة دي
- [ ] **14.6.5** ⚠️ **اكتب في `AGENT_LOG.md` إن "المشتريات" اتشالت من القايمة مؤقتًا وهترجع في `specs/018`** — عشان محدش يفتكرها ضاعت

---

## التحقق النهائي

- [ ] **ن-1** `php artisan test --compact` — كل الاختبارات ناجحة
- [ ] **ن-2** `vendor/bin/pint --dirty --format agent` نظيف
- [ ] **ن-3** `npm run build` ناجح
- [ ] **ن-4** `php artisan migrate:fresh --seed` شغالة من الصفر
- [ ] **ن-5** **بحث شامل:** `grep -rn "InventoryBatch\|batch_id\|FIFO\|purchasesSummary\|deletePurchase" app/ resources/ tests/ docs/` — لازم يرجع **صفر** نتايج في `app/` و`resources/` و`tests/`
- [ ] **ن-6** تحديث `docs/inventory.md` و`docs/inventory-costing.md` — **دول بيوصفوا نظام FIFO اللي اتشال بالكامل. اتكتبوا من أول وجديد.**
- [ ] **ن-7** تحديث `docs/profit-calculation.md` — `stockValue` بقت (الكمية × السعر الحالي)، ونوّه إن **بيع الخامة مش إيراد**
- [ ] **ن-8** تحديث `docs/cashbox.md` — البند الجديد "بيع خامة"
- [ ] **ن-9** **تحديث `USER-GUIDE.md`** بلغة المستخدم: المخزن بقى كمية وسعر واحد، الزيادة بتخصم والنقص بيزوّد، تغيير السعر ما بيحركش فلوس، واللي اتصرف تكلفته اتثبتت
- [ ] **ن-10** إدخال في `start-agent/AGENT_LOG.md` + تحديث فهرس `specs/README.md`

---

## سجل الانحرافات أثناء التنفيذ

| التاريخ | اللي اتغير | السبب |
|---|---|---|

## النتيجة النهائية

**الاختبارات:** [قبل] → [بعد]
**ملاحظات لأي Agent جاي:**
