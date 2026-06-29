# Smart Invoice Guard

Demonstracyjna aplikacja do zarządzania fakturami, zbudowana w **Laravel 13 + Inertia.js v3 + Vue 3**. Projekt powstał jako portfolio/showcase pod rozmowę rekrutacyjną — celem nie jest "kolejny CRUD", lecz pokazanie **świadomych decyzji inżynierskich**: idempotencji płatności, kolejkowanych zadań, szyfrowania danych wrażliwych, audytu zmian statusu, rate limitingu zależnego od tokenu, importu strumieniowego i pełnego środowiska Docker.

> Stack: PHP 8.5 · Laravel 13 · Inertia v3 · Vue 3 · Tailwind v4 · MariaDB · Redis · DomPDF · Reverb · Sanctum · Fortify · Wayfinder

---

## Spis treści

- [Najważniejsze cechy](#najważniejsze-cechy)
- [Architektura i przepływy](#architektura-i-przepływy)
- [Stos technologiczny](#stos-technologiczny)
- [Uruchomienie (Docker)](#uruchomienie-docker)
- [Najciekawsze fragmenty kodu](#najciekawsze-fragmenty-kodu)
- [API](#api)
- [Import faktur z CSV](#import-faktur-z-csv)
- [Testy i jakość kodu](#testy-i-jakość-kodu)
- [Struktura projektu](#struktura-projektu)

---

## Najważniejsze cechy

| Obszar | Co pokazuje |
| --- | --- |
| **Idempotentne płatności** | Dedykowany middleware `EnsureRequestIsIdempotent` wykorzystujący atomowe `Cache::add` (Redis) — zabezpiecza przed podwójnym opłaceniem faktury przy podwójnym kliknięciu / retry. |
| **Kolejkowane zadania** | Generowanie PDF oraz wysyłka e‑maili jako joby (`ShouldQueue`). Wysyłka jest dodatkowo `ShouldBeUnique` + chroniona `lockForUpdate()` i `DB::afterCommit()`. |
| **Szyfrowanie danych wrażliwych** | NIP (`tax_number`) szyfrowany w spoczynku przez własny cast `EncryptedData` (Laravel Crypt). |
| **Audyt statusów** | `InvoiceObserver` automatycznie zapisuje każdą zmianę statusu do tabeli `status_histories`. |
| **Event / Listener** | Opłacenie faktury (`InvoicePaid`) → aktualizacja statusu + powiadomienie użytkownika. |
| **Rate limiting świadomy tokenu** | Limiter API rozpoznaje token Sanctum i nadaje wyższy limit zalogowanym (60/min) niż anonimowym (5/min). |
| **Import strumieniowy CSV** | Komenda Artisan parsuje plik strumieniowo i wykonuje batchowy `upsert` — stała pamięć niezależnie od wielkości pliku. |
| **Autoryzacja** | Policy + `->can()` na poziomie tras; każdy widzi tylko własne faktury. |
| **Soft deletes** | Faktury usuwane miękko (`SoftDeletes`). |
| **Uwierzytelnianie** | Fortify: logowanie, rejestracja, reset hasła, weryfikacja e‑mail, **2FA (TOTP)**, **passkeys (WebAuthn)**, tokeny API (Sanctum). |
| **Typowane trasy** | Wayfinder generuje funkcje TS dla tras Laravela używane we Vue. |
| **Realtime‑ready** | Skonfigurowany Laravel Reverb + Echo. |

---

## Architektura i przepływy

### Cykl życia faktury

```
utworzenie ──► Observer zapisuje pierwszy wpis w status_histories
   │
   ├─► /pdf      ──► GenerateInvoicePdfJob (kolejka) ──► DomPDF ──► zapis pdf_path ──► event InvoicePdfGenerated
   │
   ├─► /send     ──► SendInvoiceEmail (kolejka, unique) ──► lock + transakcja ──► Mail ──► event InvoiceSent
   │
   └─► /pay      ──► middleware idempotencji ──► event InvoicePaid
                       └─► UpdateInvoiceStatus (lock) ──► status = paid
                       └─► SendInvoicePaidNotification ──► powiadomienie
```

### Dlaczego tak

- **Idempotencja przez `Cache::add`** — operacja atomowa "ustaw, jeśli nie istnieje". Klucz `X-Idempotency-Key` blokuje równoległe/powtórzone żądania zapłaty zanim trafią do logiki domenowej; po sukcesie zostaje oznaczony jako `completed`, przy błędzie jest zwalniany.
- **`lockForUpdate()` + `afterCommit()`** w wysyłce e‑maila — gwarancja, że faktura zostanie wysłana dokładnie raz nawet przy równoległych workerach, a mail wychodzi dopiero po zatwierdzeniu transakcji.
- **Observer zamiast logiki w kontrolerze** — historia statusów jest spójna niezależnie od miejsca zmiany (kontroler, import, listener).
- **Cast szyfrujący** — dane wrażliwe są przezroczyście szyfrowane/odszyfrowywane, logika modelu pozostaje czysta.

---

## Stos technologiczny

**Backend**
- PHP 8.5, Laravel 13
- Laravel Fortify (auth headless), Sanctum (tokeny API)
- Laravel Reverb (WebSockets), Wayfinder (typowane trasy)
- barryvdh/laravel-dompdf (generowanie PDF)
- Redis (cache, kolejki, sesje), MariaDB 10.11

**Frontend**
- Inertia.js v3 + Vue 3 (SPA bez własnego API)
- Tailwind CSS v4, reka-ui, Lucide, vue-sonner
- Vite 8, TypeScript, ESLint 9, Prettier

**Jakość / DevX**
- PHPUnit 12, Larastan/PHPStan, Laravel Pint
- Laravel Boost, Pail (logi), Docker Compose

---

## Uruchomienie (Docker)

Środowisko zawiera: PHP‑FPM, Apache, MariaDB, Redis oraz MailHog (podgląd maili).

```bash
# 1. Zbuduj i wystartuj kontenery
docker compose up -d --build

bin/bash

# 3. Wewnątrz kontenera – pełny setup
cp .env.example .env
composer install
php artisan key:generate
php artisan storage:link
php artisan migrate
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

---

## Najciekawsze fragmenty kodu

**Middleware idempotencji** — atomowa blokada na Redis:

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
php artisan tinker --execute 'App\Models\User::first()->createToken("demo")->plainTextToken;'
```

---

## Import faktur z CSV

Komenda importuje faktury strumieniowo (stałe zużycie pamięci) i zapisuje batchowo przez `upsert` (deduplikacja po `number` + `user_id`):

```bash
# 1. Wygeneruj testowy plik CSV (np. 1000 rekordów)
php artisan app:create-test-invoice-import 1000

# 2. Zaimportuj (URL do pliku w storage/app/public, rozmiar batcha opcjonalny)
php artisan app:import-invoices "http://localhost:8080/storage/test_invoices.csv" 100
```

NIP jest szyfrowany przed zapisem, identycznie jak przez cast modelu.

---

## Testy i jakość kodu

```bash
php artisan test --compact          # testy (PHPUnit, baza SQLite in-memory)
vendor/bin/pint                     # formatowanie PHP
vendor/bin/phpstan analyse          # analiza statyczna (Larastan)
npm run lint && npm run format      # ESLint + Prettier
composer ci:check                   # pełny pipeline jak w CI
```

Testy funkcjonalne pokrywają m.in. autoryzację, rejestrację, 2FA, tworzenie/płatność/wysyłkę faktur. Środowisko testowe używa SQLite `:memory:`, kolejek `sync` i sterownika maili `array`.

---

## Struktura projektu

```
app/
├─ Casts/EncryptedData.php           # przezroczyste szyfrowanie NIP
├─ Console/Commands/                 # import + generator danych testowych
├─ Enums/InvoiceStatus.php           # paid / unpaid / partially_paid
├─ Events/ · Listeners/              # InvoicePaid → status + notyfikacja
├─ Http/
│  ├─ Controllers/                   # web + Api + Settings
│  ├─ Middleware/EnsureRequestIsIdempotent.php
│  └─ Requests/                      # Form Requesty (walidacja)
├─ Jobs/                             # GenerateInvoicePdfJob, SendInvoiceEmail
├─ Models/                           # Invoice, Invoice/StatusHistory, User
├─ Observers/InvoiceObserver.php     # audyt statusów
├─ Policies/InvoicePolicy.php        # autoryzacja na poziomie zasobu
└─ Services/                         # InvoicePriceCalculator, PdfMaker
resources/js/pages/                  # widoki Inertia/Vue
routes/                              # web, invoices, api, settings, channels
docker/                             # PHP-FPM, Apache, konfiguracje
tests/                              # PHPUnit (Feature + Unit)
```

---

> Projekt demonstracyjny — środowisko skonfigurowane pod lokalny development (Docker + MailHog). Domyślne hasła w `compose.yaml` służą wyłącznie do prezentacji i nie nadają się na produkcję.
