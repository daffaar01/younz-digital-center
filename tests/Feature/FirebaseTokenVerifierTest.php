<?php

namespace Tests\Feature;

use App\Exceptions\InvalidFirebaseToken;
use App\Support\GoogleFirebaseTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseTokenVerifierTest extends TestCase
{
    public function test_it_verifies_google_signed_firebase_identity(): void
    {
        [$privateKey, $publicKey] = $this->rsaKeyPair();
        config([
            'cache.default' => 'array',
            'services.firebase.project_id' => 'younz-test-project',
        ]);
        Cache::forget('firebase.google_public_keys');
        Http::fake([
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response(
                ['test-key-id' => $publicKey],
                200,
                ['Cache-Control' => 'public, max-age=3600'],
            ),
        ]);

        $token = JWT::encode($this->validClaims(), $privateKey, 'RS256', 'test-key-id');
        $identity = app(GoogleFirebaseTokenVerifier::class)->verify($token);

        $this->assertSame('firebase-user-123', $identity->uid);
        $this->assertSame('google.user@example.test', $identity->email);
        $this->assertSame('Google User', $identity->name);
        $this->assertSame('google.com', $identity->provider);
        $this->assertTrue($identity->emailVerified);
        Http::assertSentCount(1);
    }

    public function test_it_rejects_token_for_a_different_firebase_project(): void
    {
        [$privateKey, $publicKey] = $this->rsaKeyPair();
        config([
            'cache.default' => 'array',
            'services.firebase.project_id' => 'younz-test-project',
        ]);
        Cache::forget('firebase.google_public_keys');
        Http::fake([
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response(
                ['test-key-id' => $publicKey],
                200,
                ['Cache-Control' => 'public, max-age=3600'],
            ),
        ]);
        $claims = $this->validClaims();
        $claims['aud'] = 'attacker-project';
        $claims['iss'] = 'https://securetoken.google.com/attacker-project';

        $this->expectException(InvalidFirebaseToken::class);

        app(GoogleFirebaseTokenVerifier::class)->verify(
            JWT::encode($claims, $privateKey, 'RS256', 'test-key-id'),
        );
    }

    public function test_it_verifies_phone_signed_firebase_identity_without_email(): void
    {
        [$privateKey, $publicKey] = $this->rsaKeyPair();
        config([
            'cache.default' => 'array',
            'services.firebase.project_id' => 'younz-test-project',
        ]);
        Cache::forget('firebase.google_public_keys');
        Http::fake([
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response(
                ['test-key-id' => $publicKey],
                200,
                ['Cache-Control' => 'public, max-age=3600'],
            ),
        ]);
        $claims = $this->validClaims();
        unset($claims['email'], $claims['email_verified'], $claims['name'], $claims['picture']);
        $claims['phone_number'] = '+628219207240';
        $claims['firebase'] = ['sign_in_provider' => 'phone'];

        $identity = app(GoogleFirebaseTokenVerifier::class)->verify(
            JWT::encode($claims, $privateKey, 'RS256', 'test-key-id'),
        );

        $this->assertSame('firebase-user-123', $identity->uid);
        $this->assertSame('', $identity->email);
        $this->assertSame('', $identity->name);
        $this->assertSame('phone', $identity->provider);
        $this->assertSame('+628219207240', $identity->phone);
        $this->assertFalse($identity->emailVerified);
    }

    /** @return array{string, string} */
    private function rsaKeyPair(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $windowsConfig = 'C:/xampp/apache/conf/openssl.cnf';
        if (PHP_OS_FAMILY === 'Windows' && is_file($windowsConfig)) {
            $options['config'] = $windowsConfig;
        }

        $resource = openssl_pkey_new($options);
        $this->assertNotFalse($resource, 'RSA test key could not be generated.');
        $this->assertTrue(openssl_pkey_export($resource, $privateKey, null, $options));
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);

        return [$privateKey, $details['key']];
    }

    /** @return array<string, mixed> */
    private function validClaims(): array
    {
        return [
            'aud' => 'younz-test-project',
            'iss' => 'https://securetoken.google.com/younz-test-project',
            'sub' => 'firebase-user-123',
            'iat' => time() - 10,
            'exp' => time() + 3600,
            'auth_time' => time() - 10,
            'email' => 'google.user@example.test',
            'email_verified' => true,
            'name' => 'Google User',
            'picture' => 'https://example.test/avatar.jpg',
            'firebase' => ['sign_in_provider' => 'google.com'],
        ];
    }
}
