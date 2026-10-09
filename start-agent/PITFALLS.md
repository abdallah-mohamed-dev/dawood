# أخطاء حقيقية حصلت واتصلحت — لا تكررها

كل بند هنا حصل فعلاً أثناء بناء المشروع، واتصلح بعد مراجعة كود (`/code-review`) أو اختبار فشل. القراءة دي **إلزامية** قبل كتابة أي كود جديد — الهدف إنك متضيّعش وقت في تكرار نفس الغلطة.

## 1. استدعاء `toScaledInt()` خارج `try/catch`

**المشكلة:** `ScaledIntegerCast::toScaledInt()` بيرفض قيم بحجم غير آمن (`InvalidArgumentException`) — لو الاستدعاء كان قبل بداية الـ`try` في الـController، الاستثناء بيطلع كـ500 خام بدل رسالة عربية واضحة.

**الحل الثابت:** أي استدعاء لـ`MoneyCast::toScaledInt()` أو `QuantityCast::toScaledInt()` لازم يكون **جوه** `try/catch (InvalidArgumentException)` من أول سطر، مش بعد أي كود تاني.

```php
// غلط
$material = Material::query()->findOrFail(...);
$quantity = QuantityCast::toScaledInt($request->string('quantity')->toString()); // لو فشل هنا = 500

// صح
try {
    $quantity = QuantityCast::toScaledInt($request->string('quantity')->toString());
} catch (InvalidArgumentException) {
    return back()->withInput()->withErrors(['quantity' => 'قيمة الكمية غير صالحة.']);
}
```

## 2. الثقة في نسخة الموديل اللي جاية من الـController (TOCTOU / stale in-memory)

**المشكلة:** لو الـService استلم `Model $x` من الـController واستخدمه مباشرة جوه `DB::transaction()`، ممكن نسخة تانية من نفس الصف تتغير في نفس اللحظة (طلب متزامن) والـService يشتغل على بيانات قديمة.

**الحل الثابت:** كل Service method بتعدّل بيانات لازم **تعيد جلب** الموديل بـ`lockForUpdate()` من جوه الـtransaction، مش تستخدم النسخة اللي دخلت بيها:

```php
DB::transaction(function () use ($room) {
    $room = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail(); // مش $room مباشرة
    // ...
});
```

راجع `RoomMaterialService::issue()`, `InventoryService::deletePurchase()`, `CustomerPaymentService::create()`, `RoomService::deleteRoom()` كأمثلة حية.

## 3. نسبة الخطأ لحقل غلط (misattribution) لما بيبقى فيه شرطين في `try/catch` واحد

**المشكلة:** لو `quantity` و`unit_cost` بيتحولوا جوه نفس الـ`try` block، أي خطأ في `unit_cost` بيتنسب غلط لـ`quantity` (لأن الرسالة بتكون واحدة عامة).

**الحل الثابت:** كل حقل رقمي (مبلغ/كمية) له `try/catch` **منفصل** برسالة مرتبطة بيه هو بس:

```php
try {
    $quantity = QuantityCast::toScaledInt(...);
} catch (InvalidArgumentException) {
    return back()->withErrors(['quantity' => '...']);
}

try {
    $unitCost = MoneyCast::toScaledInt(...);
} catch (InvalidArgumentException) {
    return back()->withErrors(['unit_cost' => '...']);
}
```

## 4. ~~نسيان القسمة على 1000 عند ضرب كمية في سعر~~ — اتحلّت من الجذر (specs/023)

**الغلطة اللي كانت بتحصل:** الكمية كانت مخزّنة ×1000 والمبلغ بالجنيه، فضربهم من غير قسمة على 1000 كان بيطلع رقم أكبر 1000 مرة.

**ليه مابقتش ممكنة:** الكميات بقت أعداد صحيحة بالوحدة (`QuantityCast` scale=1) زي الفلوس بالظبط، فـ`InventoryService::cost()` بقت ضرب عادي بلا قسمة ولا تقريب.

**القاعدة اللي فضلت:** أي ضرب كمية × سعر لسه بيمر من `cost()` (private) — متكتبش نسخة تانية منها.

## 5. `?: null` بيمسح القيمة `"0"` الحقيقية

**المشكلة:** `$request->string('note')->toString() ?: null` — PHP بيعتبر `"0"` قيمة falsy، فلو المستخدم كتب ملاحظة نصها حرفيًا "0" بتتمسح وتتخزن `null`.

**الحل الثابت:** استخدم `$request->filled('note') ? $request->string('note')->toString() : null` بدل `?:`.

## 6. ~~قص الأصفار العشرية~~ — مابقى له محل (specs/023)

**المشكلة اللي كانت:** `toDecimalString()` لازم تطلّع القيمة الكاملة (`2.500`) لتعبئة نماذج التعديل، بينما `toDisplayString()` بتقص الأصفار — وخلط الاتنين كان بيكسر الفورم.

**ليه مابقتش ممكنة:** مفيش كسور في النظام خالص. الفلوس بالجنيه (specs/022) والكميات بالوحدة (specs/023)، و`trimTrailingZeroDecimals()` مابقاش متفعّل في أي cast.

**القاعدة اللي فضلت:** لو حد رجّع كسور لأي `cast` في المستقبل، التقصير يبقى في `toDisplayString()` **بس** — `toDecimalString()` بتغذّي الـinputs ولازم تفضل دقيقة.

## 7. SQLite ما بيفهرسش أعمدة الـForeign Key تلقائيًا

**المشكلة:** على عكس MySQL/InnoDB، SQLite مش بيعمل index تلقائي على أعمدة الـFK — استعلامات `WHERE material_id = ?` أو `WHERE source_type = ? AND source_id = ?` بطيئة بدون فهرس صريح.

**الحل الثابت:** أي migration فيها FK أو عمود بيتصفّى عليه كتير (زي `occurred_at`, `kind`) لازم تضيف `->index()` صراحة.

## 8. `lockForUpdate()` مالوش تأثير من غير `transaction_mode = IMMEDIATE`

مضبوط بالفعل في `config/database.php` (اتصال sqlite) — **متلمسهاش**. لو لقيت سلوك غريب في التزامن، دي أول حاجة تتأكد إنها لسه مضبوطة.

## 9. حذف Model من غير عكس أثره المالي/المخزني

**المشكلة:** حذف دفعة/مصروف/عملية شراء من غير حذف حركة الخزنة المرتبطة بيها بيسيب حركة "يتيمة" (orphaned) في الخزنة.

**الحل الثابت:** أي حذف لعملية ليها أثر مالي لازم يمر عبر `CashboxService::removeFor($source)` جوه نفس الـtransaction. **ممنوع نهائيًا** إنشاء أو حذف `CashboxTransaction` مباشرة من أي مكان غير `CashboxService`.

## 10. الثقة في IDE diagnostics للـ"unused import" أو "undefined property"

**المشكلة:** في اختبارات Pest، خصائص زي `$this->admin` المعرَّفة في `beforeEach()` بتظهر كـ"Undefined property" من أدوات التحليل الساكن (false positive) — دي مش أخطاء حقيقية. تأكد دايمًا بتشغيل الاختبار الفعلي (`php artisan test`) مش بالاعتماد على تحذيرات الـIDE وحدها.

## 11. اختبار الطبقة الغلط (route model binding vs service logic)

**المشكلة:** محاولة اختبار race condition عبر HTTP request كاملة ممكن يفشل الاختبار على مستوى الـrouting (404 قبل ما يوصل للـController) بدل ما يختبر المنطق المقصود فعلاً.

**الحل:** لو الاختبار بيفشل "لسبب غلط" (رسالة الخطأ مش متعلقة بالحاجة اللي بتختبرها)، انقل الاختبار لمستوى الـService مباشرة (`tests/Feature/Services/`) بدل الـHTTP.

## 12. إضافة أبستراكشن (Blade component/trait) قبل ما تتأكد إنها مطلوبة فعلاً

فلسفة المشروع صراحة: **3 أسطر متشابهة أحسن من تجريد مبكر (premature abstraction)**. راجعات الكود بتقترح أحيانًا دمج كذا Controller في trait مشترك أو استخراج FormRequest boilerplate — دي اقتراحات أسلوبية (stylistic) مش أخطاء حقيقية، وتم تجاهل معظمها عمدًا. **لا تنفذها إلا لو المستخدم طلبها صراحة أو فيها إصلاح حقيقي لباگ.**

## 13. `config:cache` بيكسر الاختبارات ويمسح داتابيز التطوير

**المشكلة (حصلت فعلاً في 2026-10-09):** `php artisan optimize` أو `config:cache`
بيثبّت الكونفيج من `.env`، فمتغيّرات `phpunit.xml` — وأهمها
`DB_DATABASE=:memory:` — **بتتجاهل بالكامل**. أي اختبار بيستخدم `RefreshDatabase`
بعد كده بيشتغل على `database/database.sqlite` **الحقيقية ويمسحها**.

**العرض:** عدد كبير من الاختبارات بيفشل بـ`419` (CSRF) بدل أي رسالة مفهومة.

**القاعدة الثابتة:** **ممنوع `config:cache` في بيئة التطوير.** ولو اتعمل لأي سبب،
`php artisan optimize:clear` **قبل** أي `php artisan test`.
