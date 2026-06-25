# Reusable Site Template (PHP/Laravel) — Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a provider-agnostic payments capability: a `PaymentProvider` interface, a Stripe Checkout adapter, a signed webhook endpoint, admin-editable public payment settings, and a fake provider for tests.

**Architecture:** A narrow `PaymentProvider` interface (`createCheckout`, `verifyWebhook`, `handleWebhookEvent`, `getStatus`) is bound in the container; the active implementation is chosen by config. Secrets come only from `.env`; non-secret config (enabled methods, success/cancel URLs, currency) lives in a `PaymentSettings` singleton editable in admin. A `FakePaymentProvider` is the default binding under tests so the suite never calls the network. The Stripe adapter uses the official `stripe/stripe-php` SDK (no Cashier billable model needed for one-off checkout). The webhook route is public and verifies the Stripe signature before acting.

**Tech Stack:** PHP 8.2+, Laravel 11, `stripe/stripe-php`, SQLite in-memory tests, PHPUnit.

This is **Plan 3 of 4**. It depends on **Plan 1** (`SiteSetting`, locale group) and **Plan 2** (`admin` middleware, admin dashboard).

## Global Constraints

- PHP **8.2+**; Laravel **11.x**. No Node.js.
- **Secrets (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) live only in `.env`** — never in the database or the admin UI.
- Tests must not hit the network: the container binds `FakePaymentProvider` under the `testing` environment.
- The webhook endpoint must verify the provider signature before processing and is exempt from CSRF.
- SQLite `:memory:` for tests; conventional commits; commit at the end of every task.

---

### Task 1: `PaymentProvider` interface + DTOs + `PaymentSettings`

**Files:**
- Create: `app/Payments/PaymentProvider.php`
- Create: `app/Payments/CheckoutResult.php`
- Create: `app/Payments/WebhookResult.php`
- Create: `database/migrations/2026_06_27_000001_create_payment_settings_table.php`
- Create: `app/Models/PaymentSettings.php`
- Test: `tests/Unit/PaymentSettingsTest.php`

**Interfaces:**
- Produces:
  - `interface PaymentProvider` with:
    - `createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult`
    - `verifyWebhook(string $payload, string $signature): bool`
    - `handleWebhookEvent(string $payload): WebhookResult`
    - `getStatus(string $reference): string`
  - `CheckoutResult` (readonly): `string $redirectUrl`, `string $reference`.
  - `WebhookResult` (readonly): `string $type`, `string $reference`, `string $status`.
  - `PaymentSettings::current(): self` singleton; casts `public_config`, `enabled_methods` to array.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PaymentSettingsTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Models\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_singleton_with_defaults(): void
    {
        $settings = PaymentSettings::current();
        $this->assertSame(1, $settings->id);
        $this->assertSame('stripe', $settings->provider);
        $this->assertSame('eur', $settings->public_config['currency']);
        $this->assertSame(1, PaymentSettings::count());
        PaymentSettings::current();
        $this->assertSame(1, PaymentSettings::count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentSettingsTest`
Expected: FAIL — `App\Models\PaymentSettings` not found.

- [ ] **Step 3: Create the interface and DTOs**

Create `app/Payments/PaymentProvider.php`:
```php
<?php

namespace App\Payments;

interface PaymentProvider
{
    /**
     * @param array{name: string, amount: int, currency: string, quantity: int} $line
     */
    public function createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult;

    public function verifyWebhook(string $payload, string $signature): bool;

    public function handleWebhookEvent(string $payload): WebhookResult;

    public function getStatus(string $reference): string;
}
```

Create `app/Payments/CheckoutResult.php`:
```php
<?php

namespace App\Payments;

class CheckoutResult
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly string $reference,
    ) {}
}
```

Create `app/Payments/WebhookResult.php`:
```php
<?php

namespace App\Payments;

class WebhookResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $reference,
        public readonly string $status,
    ) {}
}
```

- [ ] **Step 4: Create the migration and model**

Create `database/migrations/2026_06_27_000001_create_payment_settings_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('stripe');
            $table->json('public_config')->nullable();
            $table->json('enabled_methods')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
```

Create `app/Models/PaymentSettings.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSettings extends Model
{
    protected $table = 'payment_settings';

    protected $guarded = [];

    protected $casts = [
        'public_config' => 'array',
        'enabled_methods' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], static::defaults());
    }

    public static function defaults(): array
    {
        return [
            'provider' => 'stripe',
            'public_config' => ['currency' => 'eur', 'success_url' => '/en', 'cancel_url' => '/en'],
            'enabled_methods' => ['card'],
        ];
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentSettingsTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Payments database/migrations/2026_06_27_000001_create_payment_settings_table.php app/Models/PaymentSettings.php tests/Unit/PaymentSettingsTest.php
git commit -m "feat: add PaymentProvider interface, DTOs, and PaymentSettings"
```

---

### Task 2: Fake provider + container binding

**Files:**
- Create: `app/Payments/FakePaymentProvider.php`
- Create: `app/Providers/PaymentServiceProvider.php`
- Modify: `bootstrap/providers.php` (register the provider)
- Modify: `config/services.php` (stripe keys from env)
- Test: `tests/Unit/PaymentBindingTest.php`

**Interfaces:**
- Consumes: `PaymentProvider` (Task 1).
- Produces:
  - `FakePaymentProvider implements PaymentProvider` — deterministic, no network; `verifyWebhook` returns true when `$signature === 'valid'`.
  - Container binding: `PaymentProvider::class` resolves to the implementation named by `config('services.payments.driver')`; under `testing` it is always `FakePaymentProvider`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PaymentBindingTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Payments\FakePaymentProvider;
use App\Payments\PaymentProvider;
use Tests\TestCase;

class PaymentBindingTest extends TestCase
{
    public function test_testing_environment_resolves_fake_provider(): void
    {
        $this->assertInstanceOf(FakePaymentProvider::class, app(PaymentProvider::class));
    }

    public function test_fake_checkout_returns_a_reference_and_url(): void
    {
        $result = app(PaymentProvider::class)->createCheckout(
            ['name' => 'Item', 'amount' => 1000, 'currency' => 'eur', 'quantity' => 1],
            '/ok',
            '/cancel',
        );

        $this->assertNotEmpty($result->reference);
        $this->assertStringContainsString('fake-checkout', $result->redirectUrl);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentBindingTest`
Expected: FAIL — `App\Payments\FakePaymentProvider` not found / nothing bound.

- [ ] **Step 3: Create the fake provider**

Create `app/Payments/FakePaymentProvider.php`:
```php
<?php

namespace App\Payments;

use Illuminate\Support\Str;

class FakePaymentProvider implements PaymentProvider
{
    public function createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult
    {
        $reference = 'fake_' . Str::random(12);

        return new CheckoutResult("/fake-checkout/{$reference}", $reference);
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        return $signature === 'valid';
    }

    public function handleWebhookEvent(string $payload): WebhookResult
    {
        $data = json_decode($payload, true) ?? [];

        return new WebhookResult(
            $data['type'] ?? 'unknown',
            $data['reference'] ?? '',
            $data['status'] ?? 'paid',
        );
    }

    public function getStatus(string $reference): string
    {
        return 'paid';
    }
}
```

- [ ] **Step 4: Add stripe config keys**

Edit `config/services.php` — add to the returned array:
```php
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'stripe'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
```

- [ ] **Step 5: Create the service provider**

Create `app/Providers/PaymentServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Payments\FakePaymentProvider;
use App\Payments\PaymentProvider;
use App\Payments\StripePaymentProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, function ($app) {
            if ($app->environment('testing') || config('services.payments.driver') === 'fake') {
                return new FakePaymentProvider();
            }

            return new StripePaymentProvider(
                (string) config('services.stripe.secret'),
                (string) config('services.stripe.webhook_secret'),
            );
        });
    }
}
```

Edit `bootstrap/providers.php` — add to the returned array:
```php
    App\Providers\PaymentServiceProvider::class,
```

> `StripePaymentProvider` is created in Task 3. Because the binding is a closure, the class is only referenced when resolved outside `testing`; the test suite resolves the fake, so this task's tests pass without the Stripe class existing yet. Do not resolve the provider in non-testing env until Task 3.

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=PaymentBindingTest`
Expected: PASS — both tests green.

- [ ] **Step 7: Commit**

```bash
git add app/Payments/FakePaymentProvider.php app/Providers/PaymentServiceProvider.php bootstrap/providers.php config/services.php tests/Unit/PaymentBindingTest.php
git commit -m "feat: add fake payment provider and container binding"
```

---

### Task 3: Stripe adapter

**Files:**
- Modify: `composer.json` via `composer require stripe/stripe-php`
- Create: `app/Payments/StripePaymentProvider.php`
- Test: `tests/Unit/StripePaymentProviderTest.php`

**Interfaces:**
- Consumes: `PaymentProvider` (Task 1); `stripe/stripe-php`.
- Produces:
  - `StripePaymentProvider implements PaymentProvider` constructed with `(string $secret, string $webhookSecret)`.
  - `verifyWebhook()` uses `\Stripe\Webhook::constructEvent` and returns `false` on `SignatureVerificationException` (unit-testable without network).

- [ ] **Step 1: Install the Stripe SDK**

Run: `composer require stripe/stripe-php`
Expected: `stripe/stripe-php` in `composer.json`.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/StripePaymentProviderTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Payments\StripePaymentProvider;
use Tests\TestCase;

class StripePaymentProviderTest extends TestCase
{
    public function test_verify_webhook_rejects_a_bad_signature(): void
    {
        $provider = new StripePaymentProvider('sk_test_x', 'whsec_test_x');

        $this->assertFalse($provider->verifyWebhook('{"id":"evt_1"}', 'not-a-real-signature'));
    }

    public function test_handle_webhook_event_extracts_reference_and_status(): void
    {
        $provider = new StripePaymentProvider('sk_test_x', 'whsec_test_x');

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_123', 'payment_status' => 'paid']],
        ]);

        $result = $provider->handleWebhookEvent($payload);

        $this->assertSame('checkout.session.completed', $result->type);
        $this->assertSame('cs_123', $result->reference);
        $this->assertSame('paid', $result->status);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=StripePaymentProviderTest`
Expected: FAIL — `App\Payments\StripePaymentProvider` not found.

- [ ] **Step 4: Create the Stripe adapter**

Create `app/Payments/StripePaymentProvider.php`:
```php
<?php

namespace App\Payments;

use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentProvider implements PaymentProvider
{
    private StripeClient $client;

    public function __construct(
        string $secret,
        private readonly string $webhookSecret,
    ) {
        $this->client = new StripeClient($secret);
    }

    public function createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => $line['quantity'],
                'price_data' => [
                    'currency' => $line['currency'],
                    'unit_amount' => $line['amount'],
                    'product_data' => ['name' => $line['name']],
                ],
            ]],
        ]);

        return new CheckoutResult($session->url, $session->id);
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);

            return true;
        } catch (SignatureVerificationException | \UnexpectedValueException) {
            return false;
        }
    }

    public function handleWebhookEvent(string $payload): WebhookResult
    {
        $event = json_decode($payload, true) ?? [];
        $object = $event['data']['object'] ?? [];

        return new WebhookResult(
            $event['type'] ?? 'unknown',
            $object['id'] ?? '',
            $object['payment_status'] ?? 'unknown',
        );
    }

    public function getStatus(string $reference): string
    {
        $session = $this->client->checkout->sessions->retrieve($reference);

        return $session->payment_status ?? 'unknown';
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=StripePaymentProviderTest`
Expected: PASS — both tests green (neither touches the network).

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock app/Payments/StripePaymentProvider.php tests/Unit/StripePaymentProviderTest.php
git commit -m "feat: add Stripe payment adapter"
```

---

### Task 4: Checkout route + webhook endpoint

**Files:**
- Create: `app/Http/Controllers/CheckoutController.php`
- Create: `app/Http/Controllers/PaymentWebhookController.php`
- Modify: `bootstrap/app.php` (CSRF-exempt the webhook path)
- Modify: `routes/web.php` (checkout + webhook routes)
- Test: `tests/Feature/CheckoutAndWebhookTest.php`

**Interfaces:**
- Consumes: bound `PaymentProvider` (Tasks 2/3); `PaymentSettings::current()` (Task 1).
- Produces:
  - `POST /{locale}/checkout` (name `checkout`) → creates a checkout via the provider, redirects to `redirectUrl`.
  - `POST /payments/webhook` (name `payments.webhook`, CSRF-exempt) → 400 on bad signature, 200 on valid; processes the event.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CheckoutAndWebhookTest.php`:
```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_redirects_to_provider_url(): void
    {
        $response = $this->post('/en/checkout', [
            'name' => 'Donation', 'amount' => 2500, 'quantity' => 1,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('fake-checkout', $response->headers->get('Location'));
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->call('POST', '/payments/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid',
        ], json_encode(['type' => 'x', 'reference' => 'r', 'status' => 'paid']))
            ->assertStatus(400);
    }

    public function test_webhook_accepts_valid_signature(): void
    {
        $this->call('POST', '/payments/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'valid',
        ], json_encode(['type' => 'checkout.session.completed', 'reference' => 'r', 'status' => 'paid']))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=CheckoutAndWebhookTest`
Expected: FAIL — routes undefined.

- [ ] **Step 3: Create the controllers**

Create `app/Http/Controllers/CheckoutController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Models\PaymentSettings;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __invoke(Request $request, PaymentProvider $payments)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $config = PaymentSettings::current()->public_config;

        $result = $payments->createCheckout(
            [...$data, 'currency' => $config['currency'] ?? 'eur'],
            url($config['success_url'] ?? '/'),
            url($config['cancel_url'] ?? '/'),
        );

        return redirect()->away($result->redirectUrl);
    }
}
```

Create `app/Http/Controllers/PaymentWebhookController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Payments\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentProvider $payments)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        if (! $payments->verifyWebhook($payload, $signature)) {
            return response('invalid signature', 400);
        }

        $event = $payments->handleWebhookEvent($payload);
        Log::info('payment.webhook', (array) $event);

        return response('ok', 200);
    }
}
```

- [ ] **Step 4: CSRF-exempt the webhook**

Edit `bootstrap/app.php` — inside the `->withMiddleware(...)` closure, add:
```php
        $middleware->validateCsrfTokens(except: ['payments/webhook']);
```

- [ ] **Step 5: Add the routes**

Edit `routes/web.php`:
- Add the webhook route at top level (outside the locale group):
```php
Route::post('/payments/webhook', \App\Http\Controllers\PaymentWebhookController::class)->name('payments.webhook');
```
- Add the checkout route inside the `{locale}` group:
```php
        Route::post('/checkout', \App\Http\Controllers\CheckoutController::class)->name('checkout');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=CheckoutAndWebhookTest`
Expected: PASS — all three tests green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/CheckoutController.php app/Http/Controllers/PaymentWebhookController.php bootstrap/app.php routes/web.php tests/Feature/CheckoutAndWebhookTest.php
git commit -m "feat: add checkout and signed payment webhook endpoints"
```

---

### Task 5: Admin payment settings page

**Files:**
- Create: `app/Http/Controllers/Admin/PaymentSettingsController.php`
- Create: `resources/views/admin/payments/edit.blade.php`
- Modify: `resources/views/admin/dashboard.blade.php` (add link)
- Modify: `routes/web.php` (admin payment settings routes)
- Test: `tests/Feature/AdminPaymentSettingsTest.php`

**Interfaces:**
- Consumes: `admin` middleware (Plan 2 Task 3); `PaymentSettings::current()` (Task 1).
- Produces:
  - `GET /admin/payments` (name `admin.payments.edit`), `PUT /admin/payments` (name `admin.payments.update`).
  - Updates only **non-secret** fields: `provider`, `public_config` (currency, success_url, cancel_url), `enabled_methods`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminPaymentSettingsTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\PaymentSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_public_payment_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/payments', [
            'provider' => 'stripe',
            'currency' => 'ron',
            'success_url' => '/en/thanks',
            'cancel_url' => '/en',
            'enabled_methods' => ['card', 'sepa'],
        ])->assertRedirect('/admin/payments');

        $settings = PaymentSettings::current();
        $this->assertSame('ron', $settings->public_config['currency']);
        $this->assertSame(['card', 'sepa'], $settings->enabled_methods);
    }

    public function test_non_admin_cannot_update(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put('/admin/payments', [])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=AdminPaymentSettingsTest`
Expected: FAIL — route `admin.payments.update` undefined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/PaymentSettingsController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSettings;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.payments.edit', ['settings' => PaymentSettings::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:stripe,mollie,fake'],
            'currency' => ['required', 'string', 'size:3'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
            'enabled_methods' => ['array'],
            'enabled_methods.*' => ['string'],
        ]);

        PaymentSettings::current()->update([
            'provider' => $data['provider'],
            'public_config' => [
                'currency' => $data['currency'],
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
            ],
            'enabled_methods' => $data['enabled_methods'] ?? [],
        ]);

        return redirect()->route('admin.payments.edit');
    }
}
```

- [ ] **Step 4: Create the view and dashboard link**

Create `resources/views/admin/payments/edit.blade.php`:
```blade
@extends('layouts.app')
@section('title', 'Payment Settings')
@section('content')
    <div class="container">
        <h1>Payment Settings</h1>
        <p class="muted">Secret API keys are configured via environment variables, not here.</p>
        <form method="POST" action="{{ route('admin.payments.update') }}">
            @csrf @method('PUT')
            <p><label>Provider
                <select name="provider">
                    @foreach (['stripe', 'mollie', 'fake'] as $p)
                        <option value="{{ $p }}" @selected($settings->provider === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </label></p>
            <p><label>Currency <input name="currency" value="{{ $settings->public_config['currency'] ?? 'eur' }}"></label></p>
            <p><label>Success URL <input name="success_url" value="{{ $settings->public_config['success_url'] ?? '/' }}"></label></p>
            <p><label>Cancel URL <input name="cancel_url" value="{{ $settings->public_config['cancel_url'] ?? '/' }}"></label></p>
            <p><label><input type="checkbox" name="enabled_methods[]" value="card" @checked(in_array('card', $settings->enabled_methods ?? []))> Card</label></p>
            <p><label><input type="checkbox" name="enabled_methods[]" value="sepa" @checked(in_array('sepa', $settings->enabled_methods ?? []))> SEPA</label></p>
            <p><button type="submit">Save</button></p>
        </form>
    </div>
@endsection
```

Edit `resources/views/admin/dashboard.blade.php` — add to the `<ul>`:
```blade
            <li><a href="{{ route('admin.payments.edit') }}">Payment settings</a></li>
```

- [ ] **Step 5: Add the routes**

Edit `routes/web.php` — inside the `admin` group:
```php
    Route::get('payments', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'edit'])->name('admin.payments.edit');
    Route::put('payments', [\App\Http\Controllers\Admin\PaymentSettingsController::class, 'update'])->name('admin.payments.update');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=AdminPaymentSettingsTest`
Expected: PASS — both tests green.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS — every test across Plans 1–3 green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/PaymentSettingsController.php resources/views/admin/payments/edit.blade.php resources/views/admin/dashboard.blade.php routes/web.php tests/Feature/AdminPaymentSettingsTest.php
git commit -m "feat: add admin payment settings page"
```

---

## Self-Review

**Spec coverage (Plan 3 slice):**
- §5.6 payments (`PaymentProvider` interface with `createCheckout`/`verifyWebhook`/`handleWebhookEvent`/`getStatus`; Stripe default adapter; webhook with signature verification; admin-editable public config; secrets via env) — Tasks 1, 3, 4, 5. ✔
- §6 data model `PaymentSettings` (provider, public config, enabled methods; secrets in env) — Task 1. ✔
- Constraint: tests never hit the network (fake provider bound under `testing`) — Task 2. ✔

**Placeholder scan:** Every step shows complete code with expected command output. The forward reference to `StripePaymentProvider` from the Task 2 binding is explicitly explained as safe under `testing`. ✔

**Type/name consistency:** `PaymentProvider` method signatures, `CheckoutResult($redirectUrl,$reference)`, `WebhookResult($type,$reference,$status)`, `PaymentSettings::current()`/`public_config`/`enabled_methods`, route names `checkout`/`payments.webhook`/`admin.payments.edit`/`admin.payments.update`, and config keys `services.payments.driver`/`services.stripe.*` are consistent across tasks. ✔

---

## Execution Handoff

Covered: provider-agnostic payments with a Stripe adapter, signed webhooks, and admin settings. The final plan (**cPanel Deployment**) configures production env, document root, scheduler, backups, and AutoSSL.
