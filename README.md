# Smart Invoice Guard

Demonstracyjna aplikacja do zarządzania fakturami, zbudowana w **Laravel 13 + Inertia.js v3 + Vue 3**. Projekt powstał jako portfolio/showcase pod rozmowę rekrutacyjną — celem nie jest "kolejny CRUD", lecz pokazanie **świadomych decyzji inżynierskich**: idempotencji płatności, kolejkowanych zadań, szyfrowania danych wrażliwych, audytu zmian statusu, rate limitingu zależnego od tokenu, importu strumieniowego, cache (Memcached), engagement trackingu (Redis), wyszukiwania pełnotekstowego (Elasticsearch) i pełnego środowiska Docker (PXC + ProxySQL).

> Stack: PHP 8.5 · Laravel 13 · Inertia v3 · Vue 3 · Tailwind v4 · Percona XtraDB Cluster · ProxySQL · Redis · Memcached · Elasticsearch · DomPDF · Reverb · Sanctum · Fortify · Wayfinder

---

## Spis treści

- [Najważniejsze cechy](#najważniejsze-cechy)
- [Architektura i przepływy](#architektura-i-przepływy)
- [Stos technologiczny](#stos-technologiczny)
- [Uruchomienie (Docker)](#uruchomienie-docker)
- [Najciekawsze fragmenty kodu](#najciekawsze-fragmenty-kodu)
- [API](#api)
- [Import faktur z CSV](#import-faktur-z-csv)
- [Wyszukiwanie faktur (Elasticsearch)](#wyszukiwanie-faktur-elasticsearch)
- [Testy i jakość kodu](#testy-i-jakość-kodu)
- [Struktura projektu](#struktura-projektu)

---

## Najważniejsze cechy

| Obszar | Co pokazuje |
| --- | --- |
| **Idempotentne płatności** | Dedykowany middleware `EnsureRequestIsIdempotent` wykorzystujący atomowe `Cache::add` — zabezpiecza przed podwójnym opłaceniem faktury przy podwójnym kliknięciu / retry. |
| **Kolejkowane zadania** | Generowanie PDF oraz wysyłka e‑maili jako joby (`ShouldQueue`). Wysyłka jest dodatkowo `ShouldBeUnique` + chroniona `lockForUpdate()` i `DB::afterCommit()`. |
| **Szyfrowanie danych wrażliwych** | NIP (`tax_number`) szyfrowany w spoczynku przez własny cast `EncryptedData` (Laravel Crypt). |
| **Audyt statusów** | `InvoiceObserver` automatycznie zapisuje każdą zmianę statusu do tabeli `status_histories`. |
| **Event / Listener** | Opłacenie faktury (`InvoicePaid`) → aktualizacja statusu + powiadomienie użytkownika. |
| **Rate limiting świadomy tokenu** | Limiter API rozpoznaje token Sanctum i nadaje wyższy limit zalogowanym (60/min) niż anonimowym (5/min). |
| **Import strumieniowy CSV** | Komenda Artisan parsuje plik strumieniowo i wykonuje batchowy `upsert` — stała pamięć niezależnie od wielkości pliku. |
| **Cache (Memcached)** | Dashboard stats i inne hot path-y przez `CACHE_STORE=memcached`. |
| **Invoice Pulse (Redis)** | `InvoicePulseService` rejestruje odsłony (SET unikalnych gości, HASH liczników, ZSET rankingu „hot invoices”) w jednym skrypcie Lua. |
| **Wyszukiwanie (Elasticsearch)** | `InvoiceSearchService` indeksuje faktury (multi-fields + `edge_ngram` pod prefix numeru); UI na `/invoices?q=…`. |
| **Baza HA (lokalnie)** | Percona XtraDB Cluster (3 węzły) + ProxySQL jako `DB_HOST=mariadb`. |
| **Autoryzacja** | Policy + `->can()` na poziomie tras; każdy widzi tylko własne faktury. |
| **Soft deletes** | Faktury usuwane miękko (`SoftDeletes`). |
| **Uwierzytelnianie** | Fortify: logowanie, rejestracja, reset hasła, weryfikacja e‑mail, **2FA (TOTP)**, **passkeys (WebAuthn)**, tokeny API (Sanctum). |
| **Typowane trasy** | Wayfinder generuje funkcje TS dla tras Laravela używane we Vue. |
| **Realtime‑ready** | Skonfigurowany Laravel Reverb + Echo. |

---

## Architektura i przepływy

### Cykl życia faktury

```
utworzenie ──► Observer: status_histories + indeks ES + invalidacja cache dashboardu
   │
   ├─► /pdf      ──► GenerateInvoicePdfJob (kolejka) ──► DomPDF ──► zapis pdf_path ──► event InvoicePdfGenerated
   │
   ├─► /send     ──► SendInvoiceEmail (kolejka, unique) ──► lock + transakcja ──► Mail ──► event InvoiceSent
   │
   ├─► show/pay  ──► InvoicePulseService (Redis) — views / unique visitors / heat ranking
   │
   └─► /pay      ──► middleware idempotencji ──► event InvoicePaid
                       └─► UpdateInvoiceStatus (lock) ──► status = paid
                       └─► SendInvoicePaidNotification ──► powiadomienie

usunięcie ──► Observer: czyszczenie pulse Redis + dokumentu ES + cache dashboardu
```

### Dlaczego tak

- **Idempotencja przez `Cache::add`** — operacja atomowa "ustaw, jeśli nie istnieje". Klucz `X-Idempotency-Key` blokuje równoległe/powtórzone żądania zapłaty zanim trafią do logiki domenowej; po sukcesie zostaje oznaczony jako `completed`, przy błędzie jest zwalniany.
- **`lockForUpdate()` + `afterCommit()`** w wysyłce e‑maila — gwarancja, że faktura zostanie wysłana dokładnie raz nawet przy równoległych workerach, a mail wychodzi dopiero po zatwierdzeniu transakcji.
- **Observer zamiast logiki w kontrolerze** — historia statusów, indeks wyszukiwania i cache są spójne niezależnie od miejsca zmiany (kontroler, import, listener).
- **Cast szyfrujący** — dane wrażliwe są przezroczyście szyfrowane/odszyfrowywane, logika modelu pozostaje czysta.
- **Redis vs Memcached vs Elasticsearch** — celowe rozdzielenie ról: Memcached = ogólny cache, Redis = struktury danych (pulse / kolejki / sesje), Elasticsearch = full-text + prefix search.

---

## Stos technologiczny

**Backend**
- PHP 8.5, Laravel 13
- Laravel Fortify (auth headless), Sanctum (tokeny API)
- Laravel Reverb (WebSockets), Wayfinder (typowane trasy)
- barryvdh/laravel-dompdf (generowanie PDF)
- `elasticsearch/elasticsearch` (oficjalny klient PHP)
- Percona XtraDB Cluster 8.0 + ProxySQL, Redis 7, Memcached 1.6, Elasticsearch 8.15

**Frontend**
- Inertia.js v3 + Vue 3 (SPA bez własnego API)
- Tailwind CSS v4, reka-ui, Lucide, vue-sonner
- Vite 8, TypeScript, ESLint 9, Prettier

**Jakość / DevX**
- PHPUnit 12, Larastan/PHPStan, Laravel Pint
- Laravel Boost, Pail (logi), Docker Compose

---

## Uruchomienie (Docker)

Środowisko zawiera: PHP‑FPM, Nginx, PXC (3 węzły) + ProxySQL, Redis, Memcached, Elasticsearch oraz MailHog.

```bash
# 1. Zbuduj i wystartuj kontenery
docker compose up -d --build

bin/bash

# 3. Wewnątrz kontenera – pełny setup
cp .env.example .env
php /usr/bin/composer.phar install
php artisan key:generate
php artisan storage:link
php artisan migrate
php artisan invoices:reindex --fresh   # indeks Elasticsearch
npm install
npm run build

# 4. Worker kolejki (osobny terminal w kontenerze)
php artisan queue:work & php artisan reverb:start &
```

Po starcie:

| Usługa | Adres |
| --- | --- |
| Aplikacja (HTTP) | http://localhost:8080 |
| Aplikacja (HTTPS) | https://localhost:8443 |
| MailHog (skrzynka) | http://localhost:8025 |
| Vite (dev) | https://localhost:5173 |
| Elasticsearch | hostname `elasticsearch:9200` (tylko sieć Docker; bez mapowania hosta) |

Konfiguracja ES (`.env`): `ELASTICSEARCH_ENABLED`, `ELASTICSEARCH_HOST`, `ELASTICSEARCH_INVOICES_INDEX`.

---

## Najciekawsze fragmenty kodu

**Middleware idempotencji** — atomowa blokada:

```18:48:app/Http/Middleware/EnsureRequestIsIdempotent.php
public function handle(Request $request, Closure $next): Response
{
    $key = $request->header('X-Idempotency-Key');

    if (!$key) {
        return $this->reject($request, __('Idempotency key is required.'));
    }

    $cacheKey = "idempotency_key:{$key}";

    if (!Cache::add($cacheKey, 'processing', now()->addMinutes(5))) {

        return $this->reject($request, __('Request already processed or processing.'));
    }
```

**Rate limiter zależny od tokenu Sanctum:**

```32:45:app/Providers/AppServiceProvider.php
RateLimiter::for('invoice-api', function (Request $request) {
    $userId = null;

    if ($token = $request->bearerToken()) {
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if ($accessToken) {
            $userId = $accessToken->tokenable_id;
        }
    }

    return Limit::perMinute($userId ? 60 : 5)
        ->by($userId ?: $request->ip());
});
```

**Wysyłka e‑maila odporna na wyścigi** (`ShouldBeUnique` + lock + `afterCommit`):

```37:50:app/Jobs/SendInvoiceEmail.php
            DB::transaction(function () use ($invoice) {
                $invoice = Invoice::lockForUpdate()->find($this->invoice->id);

                if ($invoice->sent_at) {
                    return;
                }

                $invoice->update(['sent_at' => now()]);
                DB::afterCommit(function () use ($invoice) {
                    Mail::to($this->email)->send(new InvoiceSentMail($invoice));
                    InvoiceSent::dispatch($invoice);
                });
            });
```

---

## API

Endpoint generujący PDF faktury, chroniony limiterem `invoice-api`:

```
POST /api/invoice/generate
Authorization: Bearer <token Sanctum>   # opcjonalne – wpływa na limit
Content-Type: application/json
```

Odpowiedź:

```json
{ "pdf_url": "http://localhost:8080/storage/invoices/..." }
```

Token API można wygenerować przez Tinkera:

```bash
php artisan tinker --execute 'echo App\Models\User::first()->createToken("demo")->plainTextToken;'
```

---

## Import faktur z CSV

Komenda importuje faktury strumieniowo (stałe zużycie pamięci) i zapisuje batchowo przez `upsert` (deduplikacja po `number` + `user_id`):

```bash
# 1. Wygeneruj testowy plik CSV (np. 1000 rekordów)
php artisan app:create-test-invoice-import 1000

# 2. Zaimportuj (URL do pliku w storage/app/public, rozmiar batcha opcjonalny)
php artisan app:import-invoices "https://nginx:8443/storage/test_invoices.csv" 100
```

NIP jest szyfrowany przed zapisem, identycznie jak przez cast modelu.

---

## Wyszukiwanie faktur (Elasticsearch)

Lista faktur (`/invoices`) obsługuje `?q=`:

- indeksowanie synchroniczne w `InvoiceObserver` (create / update / restore / delete)
- multi-fields na `number`: exact (lowercase), `edge_ngram` prefix (2–40), tokeny `standard`
- przy niedostępności ES — fallback do `LIKE` w SQL
- przebudowa indeksu:

```bash
php artisan invoices:reindex --fresh
# opcjonalnie: --user=1
```

Testy: `tests/Feature/Invoice/InvoiceSearchTest.php` (pomijane, gdy ES nie odpowiada).

---

## Testy wydajnościowe (k6)

Wymaga lokalnie zainstalowanego [k6](https://grafana.com/docs/k6/latest/) oraz działającego stacku (`https://localhost:8443`).

```bash
# 1. Przygotuj użytkownika, token Sanctum i korpus faktur
docker compose exec -T php php artisan db:seed --class=K6Seeder --no-interaction
# Token musi być świeży po każdym K6Seeder (stary plik = anonimowy limit 5/min → lawina 429)
export K6_API_TOKEN="$(docker compose exec -T php cat storage/app/private/k6/api-token.txt | tr -d '\r')"

# 2. Smoke (szybki health-check)
npm run test:k6:smoke
# albo: k6 run --insecure-skip-tls-verify tests/k6/smoke.js

# 3. Load (web browse + API PDF ~30 req/min)
npm run test:k6:load

# 4. Stress API (powyżej limitu 60/min — oczekiwane 429)
npm run test:k6:stress-api
```

Scenariusze w `tests/k6/`:

| Skrypt | Cel |
| --- | --- |
| `smoke.js` | Publiczne `/`, login Fortify, dashboard/invoices/search, 1× API PDF |
| `load.js` | Ramping VUs na UI + constant-arrival-rate na `POST /api/invoice/generate` |
| `stress-api.js` | Napór na throttle `invoice-api` (200/429, bez 5xx) |

Login Fortify jest limitowany (5/min) — skrypty logują się **raz** w `setup()` i współdzielą sesję między VU.
Po wcześniejszym 429 (login **lub** `invoice-api`): `docker compose exec php php artisan cache:clear` (limiter w Memcached).
Przed `test:k6:load` zawsze czyść cache — inaczej bucket 60/min z poprzedniego runu wygeneruje falę 429 na `/api/invoice/generate`.

Zmienne: `K6_BASE_URL`, `K6_EMAIL`, `K6_PASSWORD`, `K6_API_TOKEN`, `K6_API_RATE`, `K6_SMOKE_VUS`, `K6_SMOKE_ITERS`, `K6_LOAD_DURATION`.
Nie używaj `K6_VUS` / `K6_DURATION` / `K6_ITERATIONS` — to wbudowane override’y opcji k6.
Certyfikat self-signed: flagi `--insecure-skip-tls-verify` / `insecureSkipTLSVerify` w opcjach skryptów.
PDF-y z API lądują w `storage/app/public/invoices/` — po intensywnych runach warto posprzątać.
Jeśli wyszukiwanie ma iść przez Elasticsearch, po seedzie: `php artisan invoices:reindex --fresh`.

---

## Testy i jakość kodu

```bash
XDEBUG_MODE=off php artisan test --compact          # PHPUnit (SQLite in-memory)
XDEBUG_MODE=off php artisan test --compact tests/Feature/Invoice/InvoiceSearchTest.php
XDEBUG_MODE=off php artisan test --compact tests/Feature/Invoice/InvoicePulseTest.php
```

Część testów Feature wymaga działającego Redis / Elasticsearch w sieci Docker (w przeciwnym razie są skipowane).

---

## Struktura projektu

```
app/
├─ Casts/EncryptedData.php           # przezroczyste szyfrowanie NIP
├─ Console/Commands/                 # import, overdue, invoices:reindex
├─ Enums/InvoiceStatus.php           # paid / unpaid / partially_paid / overdue
├─ Events/ · Listeners/              # InvoicePaid → status + notyfikacja
├─ Http/
│  ├─ Controllers/                   # web + Api + Settings
│  ├─ Middleware/EnsureRequestIsIdempotent.php
│  └─ Requests/                      # Form Requesty (walidacja)
├─ Jobs/                             # GenerateInvoicePdfJob, SendInvoiceEmail
├─ Models/                           # Invoice, Invoice/StatusHistory, User
├─ Observers/InvoiceObserver.php     # audyt + ES + pulse cleanup + cache
├─ Policies/InvoicePolicy.php        # autoryzacja na poziomie zasobu
└─ Services/
   ├─ DashboardStatsService.php      # cache (Memcached)
   ├─ InvoicePulseService.php        # engagement (Redis)
   ├─ InvoiceSearchService.php       # full-text (Elasticsearch)
   ├─ InvoicePriceCalculator.php
   ├─ OverdueInvoiceService.php
   └─ PdfMaker.php
config/elasticsearch.php
resources/js/pages/                  # widoki Inertia/Vue
routes/                              # web, invoices, api, settings, channels
docker/                              # PHP-FPM, Nginx, PXC, ProxySQL
tests/                               # PHPUnit + Playwright e2e + k6
```

---

> Projekt demonstracyjny — środowisko skonfigurowane pod lokalny development (Docker + MailHog). Domyślne hasła w `compose.yaml` służą wyłącznie do prezentacji i nie nadają się na produkcję.
