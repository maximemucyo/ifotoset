# ifotoset - Decoupled Photography SaaS Platform

**ifotoset** is a production-grade, highly scalable SaaS platform for photographers to manage portfolios, organize client galleries, and collect payments. 

This platform is a **Laravel 11 (PHP 8.3)** full-stack application (Blade, Tailwind CSS v4, Alpine.js) with a versioned REST API, backed by **MySQL**, **Redis**, and S3-compatible **Backblaze B2** storage proxied through **Cloudflare CDN** for zero egress costs.

---

## 1. Directory Structure

```
ifotoset/
├── backend/                  # Laravel 11 app (Blade + Tailwind v4 + Alpine.js, built with Vite)
│   ├── app/
│   │   ├── Actions/          # Billing, studio and admin use cases
│   │   ├── Enums/            # Backed enums (PhotoStatus, NotificationPreference, …)
│   │   ├── Http/Controllers/
│   │   │   ├── Web/          # Public site, studio, admin, auth
│   │   │   └── Api/V1/       # Small JSON API used by gallery JavaScript + PawaPay webhook
│   │   ├── Jobs/             # Photo/video processing, ZIP exports, Google Photos sync
│   │   └── Services/         # UploadService, StorageService, PawaPayGateway
│   ├── database/             # Migrations and idempotent plan seeders
│   └── resources/views/      # Blade views (see DESIGN-SYSTEM.md)
├── cdn_cloudflare_worker.js  # Cloudflare Worker in front of Backblaze B2 (credentials are Worker secrets)
├── deploy.sh                 # Production deploy steps run by the push-to-main webhook
├── ecosystem.config.js       # pm2: queue workers, video worker, scheduler
├── docker/                   # Local docker files
└── docker-compose.yml        # Development stack (MySQL, Redis, Mailpit, S3 stand-in)
```

---

## 2. Key Architecture Features

* **Session authentication**: Laravel web sessions with CSRF protection; the gallery page's JSON calls use Sanctum's stateful API on photographer subdomains.
* **UUIDv7 Binary Indexing**: Uses Ordered UUIDv7 mapped to `BINARY(16)` fields internally to prevent MySQL index fragmentation.
* **Direct Browser Uploads**: The studio uploader calculates SHA-256 and streams uploads directly to Backblaze B2 via presigned URLs (type allowlisted, Content-Type signed).
* **Zero-Bandwidth Verification**: Laravel verifies uploads using 0-byte S3 `HeadObject` metadata requests, preserving application server network bandwidth.
* **Asynchronous Media Queues**: Redis jobs execute WebP thumbnail generation, EXIF extraction, BlurHash calculation and HLS video packaging.
* **Mobile Money Payment Integration**: `PaymentGateway` abstraction for **PawaPay** (MTN, Airtel, …); callbacks are re-verified against the provider before a payment is finalised.

---

## 3. Getting Started (Local Development)

The web UI is a Laravel full-stack app (Blade + Tailwind v4 + Alpine.js, built with Vite).

### Prerequisites
* Docker (OrbStack or Docker Desktop)
* Node.js 20+ on the host (for Vite)

### Step 1: Environment
Copy `backend/.env.example` to `backend/.env`. For local development, point
storage at the bundled S3-compatible service and mail at Mailpit:

```dotenv
APP_URL=http://localhost:8000
PROTOCOL=http
ROOT_DOMAIN=localhost:8000            # photographer sites live at http://{username}.localhost:8000
DB_HOST=mysql
REDIS_HOST=redis
QUEUE_CONNECTION=redis
MAIL_HOST=mailpit
MAIL_PORT=1025
BACKBLAZE_B2_KEY_ID=ifotoset
BACKBLAZE_B2_APPLICATION_KEY=ifotoset-secret
BACKBLAZE_B2_REGION=us-east-1
BACKBLAZE_B2_BUCKET=ifotoset-media
BACKBLAZE_B2_ENDPOINT=http://s3:8333                 # server-side (inside docker)
BACKBLAZE_B2_PUBLIC_ENDPOINT=http://localhost:8333   # browser-facing presigned URLs
CLOUDFLARE_CDN_DOMAIN=localhost:8333/ifotoset-media
CDN_PROTOCOL=http
USE_CDN_DOWNLOADS=false
```

Host port forwards can be overridden in a root `.env` (`FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`,
`FORWARD_MAILPIT_DASHBOARD_PORT`, `FORWARD_S3_PORT`).

### Step 2: Start the stack
```bash
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed   # includes DemoContentSeeder (real photos, clients, bookings…)
docker compose exec backend php artisan storage:init-local
```

Services: `backend` (:8000), `queue`, `queue_photos`, `scheduler`, `mysql`, `redis`, `mailpit` (UI :8025),
and `s3` (SeaweedFS S3 gateway on :8333, standing in for Backblaze B2 + Cloudflare CDN).

### Step 3: Frontend assets
```bash
cd backend && npm install
npm run dev     # Vite dev server with HMR
npm run build   # production build into public/build
```

Demo logins: `studio@ifotoset.com` / `demo123` (photographer), `admin@ifotoset.com` / `demo123` (admin).

### Design system
See `backend/resources/views/DESIGN-SYSTEM.md` for tokens, component classes, Blade components and layout conventions.

---

## 4. Production

* Push to `main` deploys automatically (see `deploy.sh`). Back up the database first.
* The scheduler must run: pm2 runs `ifotoset-scheduler` (`php artisan schedule:work`) from `ecosystem.config.js`. Do not also add a `schedule:run` cron.
* Tests: `docker compose exec -T backend php artisan test`.
