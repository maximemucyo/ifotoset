# ifotoset - Decoupled Photography SaaS Platform

**ifotoset** is a production-grade, highly scalable SaaS platform for photographers to manage portfolios, organize client galleries, and collect payments. 

This platform uses a decoupled architecture consisting of a **Next.js 16 (React 19)** frontend and a **Laravel 11 (PHP 8.3)** REST API backend, backed by **MySQL**, **Redis**, and S3-compatible **Backblaze B2** storage proxied through **Cloudflare CDN** for zero egress costs.

---

## 1. Directory Structure

```
ifotoset/
├── frontend/             # Next.js 16 App Router (React 19, Tailwind v4, TanStack Query, Zod)
│   ├── app/              # Frontend pages (login, signup, galleries, settings)
│   └── lib/              # API client wrapper, Sanctum auth, and TanStack hooks
├── backend/              # Laravel 11 REST API Framework
│   ├── app/
│   │   ├── Enums/        # PHP 8.3 Backed Enums (PaymentStatus, PhotoStatus, etc.)
│   │   ├── Casts/        # Custom Eloquent Casts (UuidBinaryCast)
│   │   ├── Services/     # UploadService, StorageService, PawaPayGateway
│   │   └── Http/Controllers/Api/V1/
│   │       ├── Auth/     # Granular Auth Controllers (Login, Register, Logout)
│   │       └── Callback/ # Webhook callback handlers
│   └── database/         # Unified database migrations and default plan seeds
├── docker/               # Local docker files
└── docker-compose.yml    # Development stack orchestration (MySQL, Redis, Mailpit)
```

---

## 2. Key Architecture Features

* **Stateful SPA Authentication**: Cookie-based session auth with Laravel Sanctum using HttpOnly cookies and CSRF protection.
* **UUIDv7 Binary Indexing**: Uses Ordered UUIDv7 mapped to `BINARY(16)` fields internally to prevent MySQL index fragmentation.
* **Direct Browser Uploads**: Next.js browser client calculates SHA-256 and streams uploads directly to Backblaze B2 via presigned URLs.
* **Zero-Bandwidth Verification**: Laravel verifies uploads using 0-byte S3 `HeadObject` metadata requests, preserving application server network bandwidth.
* **Asynchronous Media Queues**: Chained Redis jobs execute WebP thumbnail generation, EXIF extraction, and BlurHash calculation.
* **Mobile Money Payment Integration**: Standardized `PaymentGateway` abstraction for **PawaPay** supporting Rwandese/East-African mobile money (MTN, Airtel, etc.) with idempotency, callback replay logs, and status polling.

---

## 3. Getting Started (Local Development)

### Prerequisites
* Docker & Docker Compose
* Node.js v20+ (optional, or run inside container)

### Step 1: Initialize Docker Services
Spin up MySQL 8, Redis 7, Mailpit, and the Laravel backend container:
```bash
docker compose up -d
```

### Step 2: Initialize Database Migrations & Seeds
Run migrations and default seeds (plans, features) inside the backend container:
```bash
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
```

### Step 3: Run Frontend Development Server
Start the frontend development container:
```bash
docker run -d --name ifotoset_frontend -p 3000:3000 -v $(pwd)/frontend:/app -w /app node:20-alpine npm run dev
```
Open `http://localhost:3000` to view the frontend application.
Access the Mailpit dashboard at `http://localhost:8025` to inspect outgoing emails.
