<?php

namespace Tests\Feature;

use App\Contracts\FirebaseTokenVerifier;
use App\Data\FirebaseUserIdentity;
use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidFirebaseToken;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_must_verify_email_after_registration(): void
    {
        Notification::fake();

        $response = $this->post(route('customer.register.store'), [
            'name' => 'Daffa Pelanggan',
            'email' => 'DAFFA@EXAMPLE.TEST',
            'phone' => '0821-9207-240',
            'password' => 'PasswordKuat123',
            'password_confirmation' => 'PasswordKuat123',
            'terms' => '1',
            'marketing_consent' => '1',
        ]);

        $user = User::query()->where('email', 'daffa@example.test')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('argon2id', Hash::info($user->password)['algoName']);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'name' => 'Daffa Pelanggan',
            'phone' => '08219207240',
            'marketing_consent' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'customer.registered', 'user_id' => $user->id]);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('customer.dashboard'))->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );
        $this->get($verificationUrl)->assertRedirect(route('customer.dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_mobile_registration_can_verify_email_without_a_web_session(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Pelanggan Mobile',
            'email' => 'mobile@example.test',
            'phone' => '08219207242',
            'password' => 'MobilePassword123',
            'password_confirmation' => 'MobilePassword123',
            'terms' => true,
            'device_name' => 'Younz Android Test',
        ])->assertCreated()
            ->assertJsonPath('data.verification_required', true)
            ->assertJsonPath('data.verification_email_sent', true);

        $user = User::query()->where('email', 'mobile@example.test')->firstOrFail();
        $this->assertGuest();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('status', 'Email berhasil diverifikasi. Kembali ke aplikasi lalu masuk menggunakan akun Anda.');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->postJson('/api/v1/auth/customer-login', [
            'email' => 'mobile@example.test',
            'password' => 'MobilePassword123',
            'device_name' => 'Younz Android Test',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_mobile_customer_can_resend_verification_with_valid_credentials(): void
    {
        Notification::fake();
        [$user] = $this->makeCustomer('resend-mobile@example.test', '08219207243');
        $user->forceFill(['email_verified_at' => null])->save();

        $this->postJson('/api/v1/auth/email-verification/resend', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        Notification::assertNothingSent();

        $this->postJson('/api/v1/auth/email-verification/resend', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('message', 'Tautan verifikasi baru telah dikirim ke email Anda.');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_customer_registration_rejects_password_shorter_than_twelve_characters(): void
    {
        $this->post(route('customer.register.store'), [
            'name' => 'Password Pendek',
            'email' => 'short-password@example.test',
            'phone' => '08219207241',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => '1',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short-password@example.test']);
    }

    public function test_customer_can_login_and_staff_is_sent_to_the_correct_login(): void
    {
        [$customerUser] = $this->makeCustomer('customer@example.test', '081234567890');

        $this->post(route('customer.login.store'), [
            'email' => 'customer@example.test',
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($customerUser);
        $this->post(route('logout'));

        $staff = User::factory()->create([
            'email' => 'staff@example.test',
            'role' => UserRole::Cashier,
        ]);
        $this->assertGuest();

        $this->post(route('customer.login.store'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_can_reset_password_without_exposing_account_existence(): void
    {
        Notification::fake();
        [$customerUser] = $this->makeCustomer('reset@example.test', '081234567891');
        $token = null;

        $this->post(route('password.email'), ['email' => $customerUser->email])
            ->assertSessionHas('status', 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.');
        Notification::assertSentTo($customerUser, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertNotNull($token);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $customerUser->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('customer.login'));

        $this->assertTrue(Hash::check('NewPassword123', $customerUser->fresh()->password));

        $this->post(route('password.email'), ['email' => 'tidak-ada@example.test'])
            ->assertSessionHas('status', 'Jika email terdaftar sebagai pelanggan, tautan reset password telah dikirim.');
    }

    public function test_guest_opening_customer_portal_is_redirected_to_customer_login(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }

    public function test_customer_dashboard_only_lists_owned_orders(): void
    {
        [$user, $customer] = $this->makeCustomer('one@example.test', '081111111111');
        [, $otherCustomer] = $this->makeCustomer('two@example.test', '082222222222');

        $owned = $this->makeOrder($customer, 'ORD-OWN-001');
        $other = $this->makeOrder($otherCustomer, 'ORD-OTHER-001');

        $this->actingAs($user)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee($owned->order_number)
            ->assertDontSee($other->order_number);

        $this->actingAs($user)->get(route('customer.orders.show', $other))->assertNotFound();
    }

    public function test_logged_in_customer_order_uses_profile_identity_and_links_to_account(): void
    {
        [$user, $customer] = $this->makeCustomer('order@example.test', '083333333333');
        $service = Service::create([
            'name' => 'Print Dokumen',
            'slug' => 'print-dokumen-customer-test',
            'type' => 'print',
            'base_price' => 1000,
            'unit' => 'lembar',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('public.order.store'), [
            'customer_name' => 'Nama Yang Diubah',
            'customer_phone' => '089999999999',
            'service_id' => $service->id,
            'type' => 'print',
            'notes' => 'Cetak dua rangkap.',
        ]);

        $order = ServiceOrder::query()->sole();

        $response->assertRedirect(route('customer.orders.show', $order));
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($customer->name, $order->customer_name);
        $this->assertSame($customer->phone, $order->customer_phone);
        $this->assertSame($user->id, $order->created_by);
    }

    public function test_google_firebase_login_creates_customer_and_requests_phone_completion(): void
    {
        config(['services.firebase.enabled' => true]);
        $identity = new FirebaseUserIdentity(
            uid: 'firebase-google-uid-001',
            email: 'google.customer@example.test',
            name: 'Google Customer',
            picture: 'https://example.test/avatar.jpg',
            provider: 'google.com',
            emailVerified: true,
        );
        $this->fakeFirebaseVerifier($identity);

        $response = $this->postJson(route('customer.firebase'), ['id_token' => 'verified-firebase-token']);
        $user = User::query()->where('firebase_uid', $identity->uid)->firstOrFail();

        $response->assertOk()->assertJsonPath('redirect', route('customer.profile.edit'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'email' => $identity->email,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'customer.firebase_login', 'user_id' => $user->id]);
    }

    public function test_google_login_links_existing_customer_account(): void
    {
        config(['services.firebase.enabled' => true]);
        [$customerUser] = $this->makeCustomer('linked@example.test', '084444444444');
        $legacyPasswordHash = password_hash('LegacyPassword123', PASSWORD_BCRYPT);
        DB::table('users')->where('id', $customerUser->id)->update(['password' => $legacyPasswordHash]);
        $customerUser->refresh();
        $this->fakeFirebaseVerifier(new FirebaseUserIdentity(
            uid: 'firebase-linked-uid',
            email: $customerUser->email,
            name: $customerUser->name,
            picture: null,
            provider: 'google.com',
            emailVerified: true,
        ));

        $this->postJson(route('customer.firebase'), ['id_token' => 'valid-token'])
            ->assertOk()
            ->assertJsonPath('redirect', route('customer.dashboard'));
        $this->assertSame('firebase-linked-uid', $customerUser->fresh()->firebase_uid);
        $this->assertSame($legacyPasswordHash, $customerUser->fresh()->password);
    }

    public function test_google_login_claims_unverified_registration_and_invalidates_old_password(): void
    {
        config(['services.firebase.enabled' => true]);
        [$customerUser] = $this->makeCustomer('victim@example.test', '084444444445');
        $customerUser->forceFill([
            'email_verified_at' => null,
            'password' => Hash::make('AttackerPassword123'),
        ])->save();
        $this->fakeFirebaseVerifier(new FirebaseUserIdentity(
            uid: 'firebase-victim-uid',
            email: $customerUser->email,
            name: 'Pemilik Email',
            picture: null,
            provider: 'google.com',
            emailVerified: true,
        ));

        $this->postJson(route('customer.firebase'), ['id_token' => 'valid-token'])->assertOk();

        $customerUser->refresh();
        $this->assertTrue($customerUser->hasVerifiedEmail());
        $this->assertSame('firebase-victim-uid', $customerUser->firebase_uid);
        $this->assertFalse(Hash::check('AttackerPassword123', $customerUser->password));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'customer.unverified_account_claimed',
            'subject_id' => $customerUser->id,
        ]);
    }

    public function test_google_login_never_links_staff_account(): void
    {
        config(['services.firebase.enabled' => true]);
        $staff = User::factory()->create([
            'email' => 'employee.google@example.test',
            'role' => UserRole::Admin,
        ]);
        $this->fakeFirebaseVerifier(new FirebaseUserIdentity(
            uid: 'firebase-staff-uid',
            email: $staff->email,
            name: $staff->name,
            picture: null,
            provider: 'google.com',
            emailVerified: true,
        ));

        $staffResponse = $this->postJson(route('customer.firebase'), ['id_token' => 'valid-staff-token']);
        $staffResponse->assertUnprocessable()->assertJsonValidationErrors('id_token');
        $this->assertGuest();
        $this->assertNull($staff->fresh()->firebase_uid);
    }

    public function test_phone_firebase_identity_is_rejected(): void
    {
        config(['services.firebase.enabled' => true]);
        $this->fakeFirebaseVerifier(new FirebaseUserIdentity(
            uid: 'firebase-phone-uid',
            email: '',
            name: '',
            picture: null,
            provider: 'phone',
            emailVerified: false,
            phone: '+628219207240',
        ));

        $this->postJson(route('customer.firebase'), ['id_token' => 'valid-phone-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_token')
            ->assertJsonPath('errors.id_token.0', 'Gunakan akun Google yang terverifikasi.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_firebase_token_is_rejected_without_creating_session(): void
    {
        config(['services.firebase.enabled' => true]);
        $this->fakeFirebaseVerifier(new InvalidFirebaseToken('invalid'));
        $this->assertGuest();

        $invalidResponse = $this->postJson(route('customer.firebase'), ['id_token' => 'forged-token']);
        $invalidResponse->assertUnprocessable()->assertJsonValidationErrors('id_token');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_customer_can_complete_whatsapp_profile(): void
    {
        [$user, $customer] = $this->makeCustomer('profile@example.test', '085555555555');
        $user->update(['phone' => null, 'firebase_uid' => 'firebase-profile-uid']);
        $customer->update(['phone' => null]);

        $this->actingAs($user)->patch(route('customer.profile.update'), [
            'name' => 'Profil Google Lengkap',
            'phone' => '0812-3456-7890',
            'address' => 'Jalan Pengujian No. 1',
            'marketing_consent' => '1',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Profil Google Lengkap',
            'phone' => '081234567890',
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'phone' => '081234567890',
            'marketing_consent' => true,
        ]);
    }

    public function test_customer_auth_pages_render_the_combined_login_and_registration_design(): void
    {
        $login = $this->get(route('customer.login'));

        $login->assertOk()
            ->assertSee('data-customer-auth-shell', false)
            ->assertSee('data-auth-mode="login"', false)
            ->assertSee('Halo, selamat datang!')
            ->assertSee('Buat akun')
            ->assertSee('action="'.route('customer.login.store').'"', false)
            ->assertSee('action="'.route('customer.register.store').'"', false)
            ->assertSee('aria-controls="customer-password"', false)
            ->assertSee('aria-controls="register-password"', false)
            ->assertSee('aria-controls="register-password-confirmation"', false)
            ->assertSee('Tampilkan password')
            ->assertSee('customer-auth-toggle-logo', false)
            ->assertDontSee('customer-auth-toggle-mark', false)
            ->assertDontSee('boxicons');

        $this->assertSame(3, substr_count($login->getContent(), 'data-password-toggle aria-controls'));
        $this->assertSame(2, substr_count($login->getContent(), 'customer-auth-toggle-logo'));

        $registration = $this->get(route('customer.register'));

        $registration->assertOk()
            ->assertSee('class="customer-auth-shell is-register"', false)
            ->assertSee('data-auth-mode="register"', false)
            ->assertSee('Selamat datang kembali!')
            ->assertSee('Buat akun pelanggan');
    }

    public function test_google_button_and_required_security_policy_render_when_firebase_is_configured(): void
    {
        config([
            'services.firebase.enabled' => true,
            'services.firebase.web' => [
                'apiKey' => 'public-api-key',
                'authDomain' => 'younz-test.firebaseapp.com',
                'projectId' => 'younz-test',
                'appId' => '1:123:web:abc',
            ],
        ]);

        $response = $this->get(route('customer.login'));

        $response->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertSee('data-firebase-google', false)
            ->assertSee('data-auth-icon="gmail"', false)
            ->assertDontSee('Masuk dengan nomor HP')
            ->assertDontSee('SMS OTP')
            ->assertDontSee('data-firebase-phone', false)
            ->assertDontSee('firebase-recaptcha-container', false)
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $this->assertStringContainsString(
            'https://identitytoolkit.googleapis.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'https://younz-test.firebaseapp.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'https://www.gstatic.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertStringContainsString(
            'https://apis.google.com',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    /** @return array{User, Customer} */
    private function makeCustomer(string $email, string $phone): array
    {
        $user = User::factory()->create([
            'name' => 'Pelanggan Uji',
            'email' => $email,
            'phone' => $phone,
            'role' => UserRole::Customer,
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $email,
            'phone' => $phone,
            'type' => 'umum',
        ]);

        return [$user, $customer];
    }

    private function makeOrder(Customer $customer, string $number): ServiceOrder
    {
        return ServiceOrder::create([
            'order_number' => $number,
            'public_token' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'type' => 'print',
            'status' => ServiceOrderStatus::AwaitingReview,
        ]);
    }

    private function fakeFirebaseVerifier(FirebaseUserIdentity|Throwable $result): void
    {
        $this->app->instance(FirebaseTokenVerifier::class, new class($result) implements FirebaseTokenVerifier
        {
            public function __construct(private readonly FirebaseUserIdentity|Throwable $result) {}

            public function verify(string $idToken): FirebaseUserIdentity
            {
                if ($this->result instanceof Throwable) {
                    throw $this->result;
                }

                return $this->result;
            }
        });
    }
}
