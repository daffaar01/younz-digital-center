<?php

namespace App\Integrations\GoogleDrive;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleDriveBackupClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';

    public function isConfigured(): bool
    {
        return (bool) config('services.google_drive_backup.enabled')
            && filled(config('services.google_drive_backup.client_id'))
            && filled(config('services.google_drive_backup.client_secret'))
            && filled(config('services.google_drive_backup.refresh_token'));
    }

    /** @return array{id: string, name: string, size: int, folder_id: string} */
    public function upload(string $target, string $contents, int $retentionDays): array
    {
        $this->ensureConfigured();

        $name = basename(str_replace('\\', '/', $target));
        if ($contents === '' || ! preg_match('/^younz-\d{8}-\d{6}(?:-[a-f0-9]{8})?\.(?:sqlite|dump|mariadb)\.enc$/', $name)) {
            throw new RuntimeException('Nama atau isi backup Google Drive tidak valid.');
        }

        $accessToken = $this->accessToken();
        $folderId = $this->folderId($accessToken);
        $file = $this->uploadResumable($accessToken, $folderId, $name, $contents);

        try {
            $this->verifyUpload($accessToken, $file['id'], $contents);
        } catch (Throwable $exception) {
            $this->deleteQuietly($accessToken, $file['id']);

            throw $exception;
        }

        $this->rotate($accessToken, $folderId, $file['id'], max(1, $retentionDays));

        return $file + ['folder_id' => $folderId];
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->post(self::TOKEN_URL, [
                'client_id' => (string) config('services.google_drive_backup.client_id'),
                'client_secret' => (string) config('services.google_drive_backup.client_secret'),
                'refresh_token' => (string) config('services.google_drive_backup.refresh_token'),
                'grant_type' => 'refresh_token',
            ])->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException('Google tidak mengembalikan access token yang valid.');
        }

        return $token;
    }

    private function folderId(string $accessToken): string
    {
        $configured = trim((string) config('services.google_drive_backup.folder_id'));
        if ($configured !== '') {
            $this->ensureValidDriveId($configured, 'folder');

            return $configured;
        }

        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and appProperties has { key='younzBackupFolder' and value='true' }";
        $response = $this->request($accessToken)
            ->withQueryParameters([
                'q' => $query,
                'spaces' => 'drive',
                'pageSize' => 10,
                'fields' => 'files(id,name)',
            ])->get(self::FILES_URL)->throw();
        $files = $response->json('files');

        if (is_array($files)) {
            foreach ($files as $file) {
                $id = is_array($file) ? ($file['id'] ?? null) : null;
                if (is_string($id) && $this->isValidDriveId($id)) {
                    return $id;
                }
            }
        }

        $folderName = trim((string) config('services.google_drive_backup.folder_name'));
        $created = $this->request($accessToken)
            ->withQueryParameters(['fields' => 'id,name'])
            ->post(self::FILES_URL, [
                'name' => $folderName !== '' ? $folderName : 'Younz Digital Center Backups',
                'mimeType' => 'application/vnd.google-apps.folder',
                'appProperties' => ['younzBackupFolder' => 'true'],
            ])->throw();
        $id = $created->json('id');

        if (! is_string($id) || ! $this->isValidDriveId($id)) {
            throw new RuntimeException('Google Drive tidak mengembalikan ID folder yang valid.');
        }

        return $id;
    }

    /** @return array{id: string, name: string, size: int} */
    private function uploadResumable(string $accessToken, string $folderId, string $name, string $contents): array
    {
        $size = strlen($contents);
        $session = $this->request($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => 'application/octet-stream',
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->withQueryParameters([
                'uploadType' => 'resumable',
                'fields' => 'id,name,size',
            ])->post(self::UPLOAD_URL, [
                'name' => $name,
                'parents' => [$folderId],
                'appProperties' => ['younzBackup' => 'true'],
            ])->throw();
        $location = $session->header('Location');

        if (! is_string($location) || ! $this->isAllowedUploadSession($location)) {
            throw new RuntimeException('Google Drive tidak mengembalikan sesi upload yang valid.');
        }

        $uploaded = $this->request($accessToken)
            ->withHeaders(['Content-Length' => (string) $size])
            ->withBody($contents, 'application/octet-stream')
            ->put($location)
            ->throw();
        $id = $uploaded->json('id');
        $uploadedName = $uploaded->json('name');
        $uploadedSize = $uploaded->json('size');

        if (! is_string($id) || ! $this->isValidDriveId($id)) {
            throw new RuntimeException('Google Drive tidak mengembalikan ID file yang valid.');
        }

        if ($uploadedName !== $name
            || ! is_numeric($uploadedSize)
            || (int) $uploadedSize !== $size
        ) {
            $this->deleteQuietly($accessToken, $id);

            throw new RuntimeException('Metadata backup Google Drive tidak sesuai dengan file lokal.');
        }

        return ['id' => $id, 'name' => $name, 'size' => $size];
    }

    private function verifyUpload(string $accessToken, string $fileId, string $expected): void
    {
        $downloaded = $this->request($accessToken)
            ->withQueryParameters(['alt' => 'media'])
            ->get(self::FILES_URL.'/'.rawurlencode($fileId))
            ->throw()
            ->body();

        if (! hash_equals(hash('sha256', $expected), hash('sha256', $downloaded))) {
            throw new RuntimeException('Verifikasi isi backup Google Drive gagal.');
        }
    }

    private function rotate(string $accessToken, string $folderId, string $currentFileId, int $retentionDays): void
    {
        $cutoff = CarbonImmutable::now()->subDays($retentionDays);
        $pageToken = null;
        $seenTokens = [];

        do {
            $parameters = [
                'q' => "'{$folderId}' in parents and trashed = false and appProperties has { key='younzBackup' and value='true' }",
                'spaces' => 'drive',
                'pageSize' => 1000,
                'fields' => 'nextPageToken,files(id,name,createdTime)',
            ];
            if (is_string($pageToken)) {
                $parameters['pageToken'] = $pageToken;
            }

            $response = $this->request($accessToken)
                ->withQueryParameters($parameters)
                ->get(self::FILES_URL)
                ->throw();
            $files = $response->json('files');

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (! is_array($file)) {
                        continue;
                    }

                    $id = $file['id'] ?? null;
                    $name = $file['name'] ?? null;
                    $createdAt = $file['createdTime'] ?? null;
                    if (! is_string($id)
                        || $id === $currentFileId
                        || ! $this->isValidDriveId($id)
                        || ! is_string($name)
                        || ! preg_match('/^younz-\d{8}-\d{6}(?:-[a-f0-9]{8})?\.(?:sqlite|dump|mariadb)\.enc$/', $name)
                        || ! is_string($createdAt)
                    ) {
                        continue;
                    }

                    try {
                        $expired = CarbonImmutable::parse($createdAt)->isBefore($cutoff);
                    } catch (Throwable) {
                        continue;
                    }

                    if ($expired) {
                        $this->request($accessToken)
                            ->delete(self::FILES_URL.'/'.rawurlencode($id))
                            ->throw();
                    }
                }
            }

            $next = $response->json('nextPageToken');
            $pageToken = is_string($next) && $next !== '' ? $next : null;
            if ($pageToken !== null) {
                if (isset($seenTokens[$pageToken]) || count($seenTokens) >= 100) {
                    throw new RuntimeException('Paginasi Google Drive tidak valid.');
                }
                $seenTokens[$pageToken] = true;
            }
        } while ($pageToken !== null);
    }

    private function deleteQuietly(string $accessToken, string $fileId): void
    {
        try {
            $this->request($accessToken)
                ->delete(self::FILES_URL.'/'.rawurlencode($fileId))
                ->throw();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function request(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(max(30, min(600, (int) config('services.google_drive_backup.timeout', 120))));
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Backup Google Drive aktif, tetapi kredensial OAuth belum lengkap.');
        }
    }

    private function ensureValidDriveId(string $id, string $kind): void
    {
        if (! $this->isValidDriveId($id)) {
            throw new RuntimeException("ID {$kind} Google Drive tidak valid.");
        }
    }

    private function isValidDriveId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{10,200}$/', $id);
    }

    private function isAllowedUploadSession(string $url): bool
    {
        $parts = parse_url($url);
        $query = [];
        if (is_array($parts)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
        }

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'www.googleapis.com'
            && ! isset($parts['user'], $parts['pass'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443)
            && ($parts['path'] ?? null) === '/upload/drive/v3/files'
            && is_string($query['upload_id'] ?? null)
            && $query['upload_id'] !== '';
    }
}
