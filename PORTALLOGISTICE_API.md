# Portallogistice API - توافق مع الفرونتند React

## المسارات (كلها تحت `/api/portallogistice`)

| Method | Endpoint | الوصف |
|--------|----------|--------|
| POST | `/api/portallogistice/login` | تسجيل دخول مستخدم (login + password) |
| POST | `/api/portallogistice/admin/login` | تسجيل دخول مدير (email + password) |
| POST | `/api/portallogistice/send-otp` | إرسال OTP (phone أو national_id) |
| POST | `/api/portallogistice/verify-otp` | تحقق OTP (phone + otp أو national_id + otp) |
| POST | `/api/portallogistice/reset-password` | إعادة تعيين كلمة المرور |
| POST | `/api/portallogistice/logout` | تسجيل خروج مستخدم (Bearer) |
| POST | `/api/portallogistice/admin/register` | إنشاء حساب مدير (عام - لأول مرة) |
| POST | `/api/portallogistice/admin/logout` | تسجيل خروج مدير (Bearer) |
| POST | `/api/portallogistice/admin/users` | إنشاء مستخدم (مدير فقط، Bearer) |

## حسابات تجريبية (بعد تشغيل الـ Seeder)

- **مدير:** `admin@tasheel.test` / `password`
- **مستخدم (أول دخول → OTP):** `user@tasheel.test` أو `0500000000` أو `1234567890` / `password`

## تشغيل الـ Seeder

```bash
php artisan db:seed --class=PortallogisticeSeeder
```

## ملاحظات

- تسجيل الدخول للمستخدم: البحث بـ `phone` أو `national_id` أو `email`.
- إذا `is_first_login = true` لا يُرجع توكن؛ يُرجع `requiresOTP: true` و `data.user` ثم الفرونتند يطلب OTP.
- بعد `verify-otp` يتم تعيين `is_first_login = false` و `is_verified = true` ثم إعادة تسجيل الدخول يعطي التوكن.
- `send-otp` يرجّع `otp_code` في الـ response للتجربة فقط (يمكن حذفه في الإنتاج).
