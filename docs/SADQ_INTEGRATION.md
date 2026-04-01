# Sadq Tasheel — Integration Nafath (Hash Sign)

## Flow

1. **`SadqService::initiateNafath`** — `POST {SADQ_BASE_URL}{SADQ_NAFATH_AUTH_PATH}`  
   Default path: `/Authentication/Authority/IntegrationNafathAuth`

2. **Headers only** (no `Authorization`, no OAuth):  
   `thumbPrint`, `accountId`, `Content-Type: application/json`, `Accept: application/json`

3. **JSON body:**  
   `nationalIds`, `requestId` (UUID from `Str::uuid()`), `accountId`, `webHookUrl` → `{APP_URL}/api/webhook/sadq`

4. **Webhook:** `POST /api/webhook/sadq` — updates `sadq_nafath_requests` by `requestId` + `status`.

5. **App polling:** `GET /api/portallogistice/nafath/checkStatus` reads **local DB** (updated by webhook), not Sadq directly.

## Env

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Must be a **public** URL Sadq can call for webhooks |
| `SADQ_BASE_URL` | e.g. `https://tasheel-api.sadq.sa` |
| `SADQ_ACCOUNT_ID` | Header + body `accountId` |
| `SADQ_THUMBPRINT` | Header `thumbPrint` |
| `SADQ_NAFATH_AUTH_PATH` | Optional override |
| `SADQ_HTTP_TIMEOUT` | Seconds |

## Test endpoint

`POST /api/test/nafath` — body `{ "national_id": "..." }` (Postman; protect in production).

## Migrate

```bash
php artisan migrate
php artisan config:clear
```
