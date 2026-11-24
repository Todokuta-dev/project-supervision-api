# Project Supervision API

Laravel 12 API for student projects, submissions, supervisor feedback, approvals, similarity reports, and the completed-project repository.

## Setup

Requirements: PHP 8.2+, Composer, and Node.js only if you need the optional Vite assets.

```sh
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Set `FRONTEND_URL` to the frontend origin and include that host in `SANCTUM_STATEFUL_DOMAINS`. The defaults support a frontend at `http://localhost:3000`. For production, use HTTPS and configure `SESSION_DOMAIN` for the shared parent domain when the frontend and API use separate subdomains. Keep the session cookie HttpOnly and secure in production.

## Authentication

The browser frontend uses Laravel Sanctum's stateful session authentication. It requests `/sanctum/csrf-cookie` before login or registration and sends cookies with API requests. The API allows credentialed CORS only from `FRONTEND_URL`. Public registration is limited to student and supervisor accounts; administrator accounts must be provisioned by an administrator.

## Main API areas

- `/api/login`, `/api/register`, `/api/logout`, `/api/user`
- `/api/projects` and `/api/projects/{project}`
- `/api/submissions` and `/api/submissions/{submission}`
- `/api/feedback`, `/api/approvals`, and `/api/similarity-reports`
- `/api/repository-items`, `/api/departments`, and `/api/academic-sessions`
- `/api/users` returns a limited profile directory scoped to the caller's projects; administrators can view the full directory.

### Similarity text extraction

Submission uploads accept common office documents, text files, PDFs, and images. For broad format support, deploy an Apache Tika server in the same Railway project and set the API service variable `TIKA_URL` to its internal base URL (for example, `http://tika.railway.internal:9998`). The API sends uploaded files to Tika's `/tika` endpoint and uses the existing PHP parsers as a fallback. Configure the Tika service with OCR support (including Tesseract) if scanned PDFs or images should be analyzed. Without `TIKA_URL`, only the built-in PDF, Word, and plain-text extractors are used.

Similarity reports use `analysis_status` to distinguish a completed comparison from extraction failure or insufficient text. In those non-complete states, `score` is `null`; it should be rendered as unavailable, not as zero percent.

Students create projects and upload PDF or Word submissions. The API extracts text, compares submissions with repository records and matching chapter stages, and creates a report. Supervisor feedback or a final approval can archive a completed project. Repository records are linked uniquely to their project so either completion path can be safely repeated.

Run `php artisan route:list --path=api` to inspect the registered endpoints.
