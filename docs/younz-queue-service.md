# YounzQueue

`YounzQueue` harus berjalan dengan `QUEUE_CONNECTION=database`. Driver `sync`
menjalankan job di dalam request dan membuat `artisan queue:work` berhenti normal,
sehingga Windows service terlihat `Stopped` meski startup-nya `Automatic`.

Pemeriksaan tanpa perubahan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Daffa\Younz\YounzDigitalCenter\scripts\repair-younz-queue.ps1" -CheckOnly
```

Perbaikan (PowerShell **Run as administrator**):

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Daffa\Younz\YounzDigitalCenter\scripts\repair-younz-queue.ps1"
```

Skrip memvalidasi service dan tabel antrean, mengganti hanya baris
`QUEUE_CONNECTION`, membangun ulang cache konfigurasi, menyalakan service, dan
menguji restart. Jika gagal setelah `.env` berubah, skrip mengembalikan isi `.env`
semula dan membangun ulang cache. Skrip tidak menjalankan job uji dan tidak
menghapus job/failed job.

Verifikasi:

```powershell
Get-Service YounzQueue | Select-Object Name, Status, StartType
php artisan tinker --execute="echo config('queue.default');"
```

Hasil yang diharapkan adalah `Running`, `Automatic`, dan `database`.
