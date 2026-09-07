<?php

namespace Tests\Feature;

use App\Integrations\GoogleDrive\GoogleDriveBackupClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GoogleDriveBackupClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_drive_backup.enabled' => true,
            'services.google_drive_backup.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google_drive_backup.client_secret' => 'client-secret',
            'services.google_drive_backup.refresh_token' => 'refresh-token',
            'services.google_drive_backup.folder_id' => null,
            'services.google_drive_backup.folder_name' => 'Younz Backups',
            'services.google_drive_backup.timeout' => 120,
        ]);
    }

    public static function backupNames(): array
    {
        return [
            'legacy postgres' => ['younz-20260722-153000.dump.enc'],
            'mariadb' => ['younz-20260904-153000-aabbccdd.mariadb.enc'],
        ];
    }

    #[DataProvider('backupNames')]
    public function test_it_creates_a_folder_uploads_and_verifies_an_encrypted_backup(string $name): void
    {
        $contents = 'encrypted-backup-payload';
        $session = 'https://www.googleapis.com/upload/drive/v3/files?upload_id=session_1234567890';

        Http::fakeSequence()
            ->push(['access_token' => 'access-token'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'folder_1234567890', 'name' => 'Younz Backups'], 200)
            ->push('', 200, ['Location' => $session])
            ->push(['id' => 'file_123456789000', 'name' => $name, 'size' => (string) strlen($contents)], 201)
            ->push($contents, 200, ['Content-Type' => 'application/octet-stream'])
            ->push(['files' => []], 200);

        $uploaded = app(GoogleDriveBackupClient::class)
            ->upload("backups/{$name}", $contents, 30);

        $this->assertSame('file_123456789000', $uploaded['id']);
        $this->assertSame('folder_1234567890', $uploaded['folder_id']);
        $this->assertSame(strlen($contents), $uploaded['size']);
        Http::assertSentCount(7);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request->method() === 'POST'
            && $request['grant_type'] === 'refresh_token');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/upload/drive/v3/files?')
            && $request->method() === 'POST'
            && $request['parents'] === ['folder_1234567890']);
        Http::assertSent(fn (Request $request): bool => $request->url() === $session
            && $request->method() === 'PUT'
            && $request->body() === $contents);
    }

    public function test_it_deletes_an_upload_when_download_verification_fails(): void
    {
        config(['services.google_drive_backup.folder_id' => 'folder_1234567890']);
        $contents = 'encrypted-backup-payload';
        $name = 'younz-20260722-153100.dump.enc';
        $session = 'https://www.googleapis.com/upload/drive/v3/files?upload_id=session_1234567890';

        Http::fakeSequence()
            ->push(['access_token' => 'access-token'], 200)
            ->push('', 200, ['Location' => $session])
            ->push(['id' => 'file_123456789000', 'name' => $name, 'size' => (string) strlen($contents)], 201)
            ->push('corrupted-backup', 200)
            ->push('', 204);

        try {
            app(GoogleDriveBackupClient::class)->upload("backups/{$name}", $contents, 30);
            $this->fail('Upload yang rusak seharusnya ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Verifikasi isi backup Google Drive gagal.', $exception->getMessage());
        }

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), '/drive/v3/files/file_123456789000'));
    }

    public function test_it_rejects_a_forged_resumable_upload_location(): void
    {
        config(['services.google_drive_backup.folder_id' => 'folder_1234567890']);
        $contents = 'encrypted-backup-payload';

        Http::fakeSequence()
            ->push(['access_token' => 'access-token'], 200)
            ->push('', 200, ['Location' => 'https://attacker.example/upload?upload_id=stolen']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Drive tidak mengembalikan sesi upload yang valid.');

        try {
            app(GoogleDriveBackupClient::class)
                ->upload('backups/younz-20260722-153200.dump.enc', $contents, 30);
        } finally {
            Http::assertSentCount(2);
        }
    }
}
