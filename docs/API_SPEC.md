# مواصفات API — Portallogistice

جميع المسارات تحت البادئة: `POST/GET .../api/portallogistice/...`  
الفرونت يتوقع استجابة موحدة: `{ success: true|false, message?: string, data?: ... }`  
المسارات المحمية تتطلب هيدر: `Authorization: Bearer <token>`.

---

## 1. Auth (عامة — بدون توكن)

| Method | Path | Body / Query | ملاحظات |
|--------|------|--------------|---------|
| POST | `login` | `{ login, password }` | المستخدم العادي فقط؛ يرجع `data.token`, `data.user` أو `requiresOTP` |
| POST | `send-otp` | `{ phone }` أو `{ national_id }` | إرسال OTP |
| POST | `verify-otp` | `{ national_id, otp }` | التحقق من OTP |
| POST | `reset-password` | حسب التصميم الحالي | إعادة تعيين كلمة المرور |
| POST | `admin/login` | `{ email, password }` | دخول المدير؛ يرجع `data.token`, `data.admin` |
| POST | `admin/register` | بيانات إنشاء مدير | أول مرة — إنشاء حساب مدير |

---

## 2. Auth (محمية — توكن المستخدم)

| Method | Path | ملاحظات |
|--------|------|---------|
| POST | `logout` | middleware: auth.token |

---

## 3. Auth (محمية — توكن المدير)

| Method | Path | ملاحظات |
|--------|------|---------|
| POST | `admin/logout` | middleware: auth.token + admin |
| POST | `admin/users` | إنشاء مستخدم (store) |

---

## 4. المستخدم — Profile & Overview

| Method | Path | الاستجابة المتوقعة (data) |
|--------|------|---------------------------|
| GET | `profile` | `user` أو كائن المستخدم الكامل (national_id, first_name, family_name, father_name, grandfather_name, birth_date, region, bank_name, iban, phone, ...) |
| PUT | `profile` | نفس الحقول؛ تحديث الملف الشخصي |
| GET | `overview` | `user`, `quick_stats`, `pending_actions`, `recent_activity`, `next_payment`, `profile_completion_status`, `wire_receipt_status`؛ اختياري: حساب تفاصيل الحساب البنكي عند الحاجة |
| GET | `account-details` | تفاصيل الحساب البنكي للتحويل (يُستدعى عند وجود wire_receipt_status.requires_wire_transfer) |

---

## 5. العقود (المستخدم)

| Method | Path | Query | ملاحظات |
|--------|------|-------|---------|
| GET | `contracts` | اختياري: `_t` للكاش | قائمة عقود المستخدم؛ الفرونت يتوقع `data.contracts` أو `data` مصفوفة. كل عقد: id, tracking_id, status, receipt_upload_status (مثلاً 'uploaded' للموافقة)، ... |
| GET | `download-contract/{id}` | `national_id` | تحميل PDF العقد |
| GET | `contract-pdf` | استعلام حسب الفرونت (مع/بدون pdf=1) | إنشاء/عرض PDF العقد |
| POST | `register` | body: بيانات التسجيل/العقد | تسجيل عقد جديد |
| POST | `nafath/initiate` | body حسب الفرونت | بدء توثيق نفاذ |
| GET | `nafath/checkStatus` | `national_id`, `contract_type`, `_t` | التحقق من حالة نفاذ |

---

## 6. المستندات (المستخدم)

| Method | Path | ملاحظات |
|--------|------|---------|
| GET | `documents` | قائمة مستندات المستخدم |
| POST | `documents/upload` | multipart رفع مستند |
| GET | `documents/{id}/decision` | قرار على مستند (للإدارة لاحقاً أو للمستخدم) |
| GET | `my-contractor-info-pdf` | PDF معلومات المقاول (صفحة معلومات العقد) |

---

## 7. الإشعارات (المستخدم)

| Method | Path | Query | ملاحظات |
|--------|------|-------|---------|
| GET | `notifications` | `status`, `read`, `per_page` | قائمة إشعارات |
| GET | `notifications/count` | — | عدد غير المقروء أو حسب التصميم |
| PUT/PATCH | `notifications/{id}/read` | — | تعليم كمقروء |
| POST | `notifications/{id}/dismiss` | — | رفض/إغلاق |
| POST | `notifications/{id}/complete` | — | تعليم مكتمل |
| POST | `notifications/mark-all-read` | — | تعليم الكل كمقروء |

---

## 8. المدفوعات (المستخدم)

| Method | Path | ملاحظات |
|--------|------|---------|
| GET | `payments` | قائمة المدفوعات |
| GET | `payments/summary` | ملخص (إجمالي، حالة، ...) |
| POST | `payments/report-missing` | إبلاغ عن دفعة ناقصة |

---

## 9. التحليلات (المستخدم)

| Method | Path | ملاحظات |
|--------|------|---------|
| GET | `analytics/summary` | ملخص للوحة التحليلات |
| GET | `analytics/payments` | بيانات مدفوعات للتحليل |

---

## 10. لوحة المدير (كلها تحت prefix `admin/` + middleware auth.token + admin)

| Method | Path | ملاحظات |
|--------|------|---------|
| GET | `admin/dashboard/stats` | إحصائيات لوحة المدير |
| GET | `admin/contracts` | قائمة العقود (query: status, per_page, ...) |
| GET | `admin/contracts/{id}` | تفاصيل عقد |
| PATCH/PUT | `admin/contracts/{id}/status` | تحديث حالة العقد (موافقة/رفض) |
| POST | `admin/contracts/{id}/status` | رفض عقد (مثلاً contractToReject) |
| GET | `admin/contract-settings` | إعدادات العقود |
| POST/PUT | `admin/contract-settings/update` | تحديث إعدادات العقود |
| GET | `admin/contracts/template-settings` | إعدادات قالب العقود |
| PUT | `admin/contracts/template-settings` | تحديث قالب العقود |
| GET | `admin/documents` | قائمة المستندات (type, contract_id, ...) |
| POST | `admin/documents/{id}/approve` | الموافقة على مستند (مثلاً إيصال) |
| POST | `admin/documents/{id}/reject` | رفض مستند |
| GET | `admin/users` | قائمة المستخدمين (query params) |
| GET | `admin/users/{nationalId}` | تفاصيل مستخدم |
| PUT | `admin/users/{nationalId}` | تحديث مستخدم |
| POST | `admin/users/{nationalId}/status` | تحديث حالة المستخدم (تفعيل/إيقاف) |
| GET | `admin/payments` | قائمة المدفوعات (مع فلاتر) |
| GET | `admin/payments/summary` | ملخص مدفوعات المدير |
| PATCH | `admin/payments/{id}/status` | تحديث حالة دفعة |
| GET | `admin/analytics/revenue` | إيرادات (period) |
| GET | `admin/analytics/users` | تحليلات المستخدمين (period) |
| GET | `admin/analytics/contracts` | تحليلات العقود (period) |
| GET | `admin/activity-logs` | سجلات النشاط (query params) |
| GET | `admin/email-templates` | قوالب البريد |
| GET | `admin/email-templates/notification-types` | أنواع الإشعارات |
| GET | `admin/email-templates/{id}` | قالب واحد |
| PUT | `admin/email-templates/{id}` | تحديث قالب |
| GET | `admin/email-templates/{id}/preview` | معاينة قالب |
| POST | `admin/send-custom-email` | إرسال بريد مخصص |

---

## قاعدة العقود (للاستخدام في Overview والتحليلات)

- **إجمالي العقود** = 10 (ثابت).
- **العقود المتاحة حالياً** = 10 − (العقود المستخدمة − العقود المنتهية).
- **العقود المنتهية** = عقود التصفية (نفس العدد).
- يُفضل أن يرجع الـ API: `used`, `ended`, `renewed`, `renewalNotifications` ويُحسب المتاحة في الفرونت أو في الباك اند بنفس القانون.

---

## CORS و base URL

- الفرونت: `REACT_APP_API_BASE_URL` (مثلاً `http://127.0.0.1:8000/api`) و `REACT_APP_API_ORIGIN` للروابط.
- الباك اند يجب أن يسمح بـ origin الفرونت في CORS.
