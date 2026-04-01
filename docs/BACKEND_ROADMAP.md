# خارطة الباك اند — Portallogistice

## الحالة الحالية

- **Auth**: جاهز (login, admin/login, send-otp, verify-otp, reset-password, logout, admin/logout, admin/register).
- **Middleware**: `auth.token` (AuthByToken), `admin` (EnsureAdmin).
- **المتبقي**: كل مسارات المستخدم والمدير الخاصة بالـ profile, overview, contracts, documents, notifications, payments, analytics, وإدارات المدير.

---

## ترتيب التنفيذ المقترح

### المرحلة 1 — ربط الفرونت بالداشبورد (أول ما يفتح المستخدم لوحته)

1. **GET /profile**  
   - إرجاع بيانات المستخدم الحالي من الجدول/كاش.  
   - الفرونت يتوقع `data.user` أو `data` = كائن المستخدم.

2. **GET /overview**  
   - تجميع: user, quick_stats, pending_actions, recent_activity, next_payment, profile_completion_status, wire_receipt_status.  
   - يمكن البدء بقيم ثابتة أو من جداول موجودة ثم ربطها لاحقاً.

3. **GET /contracts**  
   - قائمة عقود المستخدم مع الحقول التي يستخدمها الفرونت (مثل receipt_upload_status).

4. **GET /documents**  
   - قائمة مستندات المستخدم (حتى لو فارغة).

5. **GET /account-details** (اختياري في البداية)  
   - تفاصيل التحويل البنكي عند الحاجة.

بعد هذه النقاط يمكن تشغيل الفرونت وفتح الداشبورد مع بيانات حقيقية (أو stub).

---

### المرحلة 2 — الإشعارات والمهام

6. **GET /notifications** مع query (status, read, per_page).  
7. **GET /notifications/count**.  
8. **PUT/POST notifications/{id}/read**, **dismiss**, **complete**, **mark-all-read**.

---

### المرحلة 3 — الملف الشخصي والمستندات

9. **PUT /profile** — تحديث الملف.  
10. **POST /documents/upload** — رفع مستند.  
11. **GET /documents/{id}/decision** إن كان مطلوباً للفرونت.

---

### المرحلة 4 — العقود والتحميل

12. **GET /download-contract/{id}**, **GET /contract-pdf** (وفق الاستدعاءات الحالية في الفرونت).  
13. **POST /register** (تسجيل عقد جديد).  
14. **POST /nafath/initiate**, **GET /nafath/checkStatus** — ربط أو stub حسب نفاذ.

---

### المرحلة 5 — المدفوعات والتحليلات

15. **GET /payments**, **GET /payments/summary**, **POST /payments/report-missing**.  
16. **GET /analytics/summary**, **GET /analytics/payments**.  
17. إرجاع بيانات العقود (used, ended, renewed, renewalNotifications) في overview أو endpoint منفصل حسب التصميم؛ تطبيق قاعدة: إجمالي 10، متاحة = 10 − (مستخدمة − منتهية)، منتهية = عقود تصفية.

---

### المرحلة 6 — لوحة المدير

18. **GET /admin/dashboard/stats** و **GET /admin/contracts** (للشريط الجانبي والقوائم).  
19. إدارة العقود: عرض، تحديث حالة، رفض.  
20. إدارة المستندات: قائمة، approve، reject.  
21. **admin/contract-settings** و **admin/contracts/template-settings**.  
22. **admin/users** (قائمة، تفاصيل، تحديث، حالة).  
23. **admin/payments** و **admin/analytics/***.  
24. **admin/activity-logs**, **admin/email-templates**, **admin/send-custom-email**.  
25. **GET /my-contractor-info-pdf** إذا كان مستخدماً في الواجهة.

---

## تشغيل الباك اند محلياً

```bash
cd tasheel-backend
cp .env.example .env
php artisan key:generate
# إذا SQLite:
touch database/database.sqlite
php artisan migrate
php artisan serve
```

الـ API يكون على: `http://127.0.0.1:8000` والمسارات تحت `/api` (مثلاً `/api/portallogistice/login`).

---

## ربط الفرونت

في جذر المشروع (الفرونت):

- نسخ `.env.example` إلى `.env`.
- تعيين:
  - `REACT_APP_API_BASE_URL=http://127.0.0.1:8000/api`
  - `REACT_APP_API_ORIGIN=http://127.0.0.1:8000`
- إعادة تشغيل `npm start`.

المواصفات الكاملة للـ endpoints في: [API_SPEC.md](./API_SPEC.md).
