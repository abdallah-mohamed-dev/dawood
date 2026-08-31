# SOP — مشروع Dawood (نظام إدارة ورشة أثاث)

مرجع تشغيلي واحد للمشروع: نظرة عامة، تثبيت، بنية، تشغيل يومي، اختبارات، ونشر.

---

## 1. نظرة عامة (Project Overview)

تطبيق **Laravel** لإدارة عمليات ورشة تصنيع/تجهيز أثاث (غرف/طلبات عملاء)، ويغطي:

- **الخزنة (Cashbox)** — رصيد افتتاحي وحركات دخول/خروج.
- **المخزون (Inventory)** — تصنيفات، مواد، دفعات شراء بنظام **FIFO**.
- **العملاء والغرف (Customers & Rooms)** — طلبات العملاء وحالتها ومواد كل غرفة.
- **دفعات العملاء (Customer Payments)**.
- **المصروفات (Expenses)** — تصنيفات ومصروفات.
- **الشركاء (Partners)** — نسبة كل شريك وسحوباته من الأرباح.
- **الأرباح (Profit)** — تقرير ربح صافي وأعمال تحت التنفيذ (WIP).

الواجهة بالكامل **عربية (RTL)** — `APP_LOCALE=ar`. يوجد توثيق تفصيلي لقواعد العمل في مجلد [`docs/`](docs/) (مثل `docs/business-rules.md`, `docs/partners.md`, `docs/profit-calculation.md`, `docs/cashbox.md`, إلخ) — راجعه لتفاصيل منطق كل دومين، فهذا الملف يغطي الجانب التشغيلي فقط.

حالة المشروع: **MVP مكتمل** (Tasks 0–11 في [`docs/tasks-checklist.md`](docs/tasks-checklist.md)).

## 2. البنية التقنية (Tech Stack)

من `composer.json` و`package.json`:

| الطبقة | التفاصيل |
|---|---|
| PHP | `^8.3` |
| Laravel Framework | `^13.17` |
| قاعدة البيانات (dev) | SQLite (`DB_CONNECTION=sqlite`) |
| الاختبارات | Pest `^5.1` + `pestphp/pest-plugin-laravel` |
| التنسيق (Code Style) | Laravel Pint `^1.27` |
| أدوات AI | Laravel Boost `^2.2` (MCP server) |
| Frontend build | Vite `^8.0` + `laravel-vite-plugin ^3.1` |
| CSS | Tailwind CSS `^4.0` (عبر `@tailwindcss/vite`) |
| قوالب | Blade |

لا يوجد Livewire/Inertia/Vue/React مثبت — الواجهة Blade تقليدية + Tailwind.

## 3. هيكل المشروع (Project Structure)

```
app/
  Http/Controllers/       - Controllers (منها Inventory/ فرعي)
  Models/                 - Eloquent models (14 موديل)
  Services/               - منطق الأعمال (Cashbox, Inventory, Room, Partner...)
  Casts/                  - MoneyCast, QuantityCast
routes/web.php            - كل المسارات (auth-only تقريبًا)
database/migrations/      - جداول قاعدة البيانات
resources/views/          - Blade views
resources/views/components/ - مكونات مشتركة (انظر قسم 8)
docs/                     - توثيق قواعد العمل لكل دومين
lang/ar/                  - نصوص وترجمات عربية
tests/                    - اختبارات Pest (Feature غالبًا)
```

## 4. التثبيت والتشغيل (Setup & Installation)

يوجد سكربت جاهز في `composer.json`:

```bash
composer install
cp .env.example .env      # composer setup script يعمل هذا تلقائيًا لو مفيش .env
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts
npm run build
```

أو ببساطة: `composer run setup` (ينفذ كل الخطوات أعلاه بالترتيب).

### مستخدم أدمن أولي
يتم تعريفه من متغيرات `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` في `.env` (seeder idempotent — راجع `database/seeders/`).

## 5. متغيرات البيئة (Environment Variables)

أهم المفاتيح من `.env.example` (بدون قيم حقيقية):

| المفتاح | الغرض |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG` | إعدادات عامة للتطبيق |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | لغة الواجهة (عربي افتراضيًا) |
| `APP_TIMEZONE` | `Africa/Cairo` |
| `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` | بيانات المستخدم الأدمن الأولي (seeder) |
| `DB_CONNECTION` وما يتبعها | إعدادات قاعدة البيانات (SQLite افتراضيًا محليًا) |
| `SESSION_DRIVER` / `SESSION_LIFETIME` | إعدادات الجلسة (تخزين في DB) |
| `QUEUE_CONNECTION` | قائمة الانتظار (database) |
| `CACHE_STORE` | الكاش (database) |
| `MAIL_*` | إعدادات البريد (log driver محليًا) |
| `AWS_*` | بيانات S3 (فاضية افتراضيًا — تُستخدم لو `FILESYSTEM_DISK` اتغيرت) |
| `VITE_APP_NAME` | يُمرَّر لـ Vite/الواجهة الأمامية |

**لا تضع أي قيم حقيقية لهذه المفاتيح في هذا الملف أو في أي مكان غير `.env` المحلي.**

## 6. قاعدة البيانات (Database)

الموديلات (`app/Models/`) والجداول المقابلة من `database/migrations/`:

| Model | الوصف |
|---|---|
| `User` | مستخدمو النظام (أدمن) |
| `Category`, `Material` | تصنيفات ومواد المخزون |
| `InventoryBatch`, `InventoryMovement` | دفعات الشراء وحركات المخزون (FIFO) |
| `Customer`, `Room`, `RoomMaterial` | العملاء والغرف/الطلبات ومواد كل غرفة |
| `CustomerPayment` | دفعات العملاء |
| `ExpenseCategory`, `Expense` | تصنيفات ومصروفات |
| `CashboxTransaction` | حركات الخزنة (دخول/خروج) |
| `Partner`, `PartnerWithdrawal` | الشركاء ونسبهم وسحوباتهم |

ترتيب الـ migrations الزمني (`2026_08_19` → `2026_08_20`) يعكس تسلسل بناء الميزات: المخزون أولاً، ثم العملاء/الغرف، ثم المصروفات، ثم الشركاء أخيرًا.

## 7. الأدوار والصلاحيات (Roles & Permissions)

**لا يوجد نظام أدوار/صلاحيات متعدد المستويات حاليًا.** يوجد `middleware('auth')` فقط على كل المسارات الداخلية (باستثناء `/login`)، ومستخدم واحد (أدمن) يُنشأ عبر seeder. لا حزمة صلاحيات (مثل spatie/permission) مثبتة في `composer.json`.

## 8. الميزات الرئيسية والمكونات المشتركة (Key Features & Shared Components)

### المسارات الرئيسية (`routes/web.php`)
- `/cashbox` — الخزنة والرصيد الافتتاحي
- `/inventory/categories`, `/inventory/materials`, `/inventory/purchases`
- `/customers` (CRUD كامل)
- `/rooms` — إنشاء/عرض/حذف + تحديث الحالة + إدارة مواد الغرفة
- `/payments` — دفعات العملاء
- `/expenses`, `/expenses/categories`
- `/partners` (CRUD) + `/partners/{partner}/withdrawals` (سحوبات)
- `/reports/profit` — تقرير الأرباح

### طبقة Services (`app/Services/`)
منطق الأعمال منفصل عن الـ Controllers: `CashboxService`, `InventoryService`, `RoomService`, `RoomMaterialService`, `CustomerPaymentService`, `ExpenseService`, `PartnerService`, `ProfitService`.

### المكونات المشتركة (`resources/views/components/`)
تم استخلاصها في Task 10 لتوحيد الجداول والحقول عبر الشاشات المختلفة (commit `45758cd`):
- `data-table.blade.php` — جدول بيانات عام
- `field.blade.php` — حقل نموذج موحّد
- `delete-button.blade.php` — زر حذف موحّد (مع تأكيد)
- `money.blade.php` / `quantity.blade.php` — عرض القيم المالية/الكميات
- `status-badge.blade.php` — شارة حالة
- `app-layout.blade.php` / `guest-layout.blade.php` — التخطيطات الأساسية

استخدم هذه المكونات بدل تكرار HTML عند إضافة شاشة جديدة.

## 9. أوامر التشغيل اليومية (Daily Dev Commands)

```bash
composer run dev          # يشغّل server + queue:listen + vite dev سوا (الطريقة الموصى بها محليًا)
php artisan serve         # السيرفر لوحده
npm run dev                # Vite dev لوحده
npm run build               # بناء الأصول للإنتاج
php artisan migrate         # تشغيل الـ migrations
php artisan test --compact  # تشغيل الاختبارات (Pest)
vendor/bin/pint --dirty --format agent   # تنسيق الكود المُعدَّل فقط
```

## 10. الاختبارات (Testing)

- إطار الاختبار: **Pest**. إنشاء اختبار جديد: `php artisan make:test --pest {Name}`.
- التشغيل: `php artisan test --compact` أو مع فلتر: `php artisan test --compact --filter=testName`.
- اختبار تكاملي شامل (end-to-end للدورة المالية كاملة: رصيد افتتاحي → شراء مخزون → غرفة → دفعة عميل → مصروف → إتمام الغرفة → سحب شريك) موجود في:
  [`tests/Feature/Services/FullCycleIntegrationTest.php`](tests/Feature/Services/FullCycleIntegrationTest.php) — أُضيف في commit `47eb56d` (Task 11)، وهو مرجع جيد لفهم تفاعل كل الـ Services مع بعض.
- لا تحذف اختبارات موجودة إلا بموافقة صريحة (قاعدة من `AGENTS.md`).

## 11. إجراءات النشر (Deployment)

**غير موثّق داخل هذا المشروع** — لا يوجد ملف CI/CD (GitHub Actions/إلخ) ولا سكربت نشر مخصص في الريبو وقت كتابة هذا الملف.

مرجع عام (Laravel القياسي — وليس تعليمات خاصة بهذا المشروع):
1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. ضبط `.env` للإنتاج (`APP_ENV=production`, `APP_DEBUG=false`, قاعدة بيانات حقيقية بدل SQLite لو لزم)
4. `php artisan migrate --force`
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. تشغيل queue worker منفصل لو فيه Jobs فعلية (`QUEUE_CONNECTION` غير `sync`)

الخيار المذكور في `AGENTS.md` هو [Laravel Cloud](https://cloud.laravel.com/) كأسرع طريقة نشر، لكن مفيش إعداد فعلي له في الريبو.

## 12. استكشاف الأخطاء الشائعة (Troubleshooting)

| المشكلة | الحل |
|---|---|
| `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest` | شغّل `npm run build` أو `npm run dev` أو `composer run dev` |
| تغييرات الواجهة مش ظاهرة | تأكد إن `npm run dev`/`build` شغالة، أو امسح الكاش (تحت) |
| مشاكل كاش عامة | `php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear` |
| فشل migration على SQLite | تأكد من وجود `database/database.sqlite` (`composer run setup` بينشئه) |
| خطأ صلاحيات على `storage/` أو `bootstrap/cache/` | تأكد إن المجلدين قابلين للكتابة من السيرفر |

## 13. جهات الاتصال ومسؤوليات الفريق (Team Contacts & Ownership)

> **غير موثّق حاليًا في الريبو — يحتاج تعبئة من الفريق.**

| المجال | المسؤول | جهة التواصل |
|---|---|---|
| Backend / Laravel | — | — |
| Frontend / Blade-Tailwind | — | — |
| قاعدة البيانات والنشر | — | — |
| منتج / قواعد العمل | — | — |

---

*آخر تحديث لهذا الملف: بناءً على حالة الريبو عند commit `9bdae82` (فرع `main`).*
