<?php

namespace App\Support;

use App\Contracts\FirebaseTokenVerifier;
use App\Data\FirebaseUserIdentity;
use App\Exceptions\InvalidFirebaseToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleFirebaseTokenVerifier implements FirebaseTokenVerifier
{
    private const PUBLIC_KEYS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function verify(string $idToken): FirebaseUserIdentity
    {
        $projectId = trim((string) config('services.firebase.project_id'));

        if ($projectId === '') {
            throw new InvalidFirebaseToken('Firebase project ID belum dikonfigurasi.');
        }

        try {
            $segments = explode('.', $idToken);
            if (count($segments) !== 3) {
                throw new InvalidFirebaseToken('Format token Firebase tidak valid.');
            }

            $header = json_decode(JWT::urlsafeB64Decode($segments[0]), true, flags: JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
                throw new InvalidFirebaseToken('Header token Firebase tidak valid.');
            }

            $certificates = $this->publicKeys();
            if (! isset($certificates[$header['kid']])) {
                Cache::forget('firebase.google_public_keys');
                $certificates = $this->publicKeys();
            }
            if (! isset($certificates[$header['kid']])) {
                throw new InvalidFirebaseToken('Kunci penandatangan token tidak dikenali.');
            }

            $previousLeeway = JWT::$leeway;
            JWT::$leeway = 60;
            try {
                $claims = JWT::decode($idToken, new Key($certificates[$header['kid']], 'RS256'));
            } finally {
                JWT::$leeway = $previousLeeway;
            }

            $issuer = "https://securetoken.google.com/{$projectId}";
            if (($claims->aud ?? null) !== $projectId || ($claims->iss ?? null) !== $issuer) {
                throw new InvalidFirebaseToken('Token berasal dari proyek Firebase yang berbeda.');
            }

            $uid = is_string($claims->sub ?? null) ? trim($claims->sub) : '';
            if ($uid === '' || mb_strlen($uid) > 128) {
                throw new InvalidFirebaseToken('UID Firebase tidak valid.');
            }

            if (! is_numeric($claims->auth_time ?? null) || (int) $claims->auth_time > time() + 60) {
                throw new InvalidFirebaseToken('Waktu autentikasi Firebase tidak valid.');
            }

            $email = is_string($claims->email ?? null) ? Str::lower(trim($claims->email)) : '';
            $firebase = is_object($claims->firebase ?? null) ? $claims->firebase : (object) [];
            $provider = is_string($firebase->sign_in_provider ?? null) ? $firebase->sign_in_provider : '';
            $name = is_string($claims->name ?? null) ? trim($claims->name) : '';
            $picture = is_string($claims->picture ?? null) && str_starts_with($claims->picture, 'https://')
                ? $claims->picture
                : null;
            $phone = is_string($claims->phone_number ?? null) ? trim($claims->phone_number) : null;

            return new FirebaseUserIdentity(
                uid: $uid,
                email: $email,
                name: $name !== '' ? Str::limit($name, 150, '') : ($email !== '' ? Str::before($email, '@') : ''),
                picture: $picture,
                provider: $provider,
                emailVerified: ($claims->email_verified ?? false) === true,
                phone: $phone,
            );
        } catch (InvalidFirebaseToken $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidFirebaseToken('Token Firebase gagal diverifikasi.', previous: $exception);
        }
    }

    /** @return array<string, string> */
    private function publicKeys(): array
    {
        $cached = Cache::get('firebase.google_public_keys');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $response = Http::acceptJson()->timeout(8)->retry(2, 200)->get(self::PUBLIC_KEYS_URL);
        $response->throw();

        $keys = $response->json();
        if (! is_array($keys) || $keys === []) {
            throw new InvalidFirebaseToken('Sertifikat publik Google tidak tersedia.');
        }

        $maxAge = 3600;
        if (preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $matches)) {
            $maxAge = max(300, min(86400, (int) $matches[1]));
        }

        Cache::put('firebase.google_public_keys', $keys, now()->addSeconds($maxAge));

        return $keys;
    }
}
