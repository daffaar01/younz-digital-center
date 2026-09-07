# Gateway WhatsApp sebagai Windows service

Installer: `scripts/install-whatsapp-service.ps1`. Memerlukan PowerShell Administrator
untuk memasang service; `-CheckOnly` dapat dijalankan tanpa Administrator dan tidak
mengubah service, task, proses, ACL, `.env`, atau sesi WhatsApp.

## Pemasangan pada komputer Younz

Pastikan WhatsApp sedang terhubung, lalu buka **PowerShell > Run as administrator**:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Daffa\Younz\YounzDigitalCenter\scripts\install-whatsapp-service.ps1" -VerifyRestart
```

Proses gateway lama akan berhenti sebentar untuk pemindahan dan uji restart.
Jangan lakukan pembelian selama pemasangan. Tidak ada pengiriman pesan/pembelian
uji, perubahan database, logout, atau penghapusan data auth oleh installer.

Installer memakai WinSW **2.12.0** yang sudah tersedia di
`C:\Program Files\YounzDigitalCenter\Services\WinSW-x64.exe`, tanpa unduhan baru.
Template mengikuti [konfigurasi resmi WinSW 2.12.0](https://github.com/winsw/winsw/blob/v2.12.0/doc/xmlConfigFile.md)
dan [rotasi log WinSW](https://github.com/winsw/winsw/blob/v2.12.0/doc/loggingAndErrorReporting.md).

- Service: `YounzWhatsAppGateway`, Automatic (Delayed Start), akun LocalService.
- Menjalankan Node langsung, dengan working directory `whatsapp-gateway`.
- Port dibaca dari `.env` gateway (komputer ini: **3001**), bind `127.0.0.1`.
- Memakai token dan sesi lama; token tidak disalin ke XML/argumen/output.
- Auth harus sudah ada di dalam `whatsapp-gateway/data/`; tidak membuat sesi baru.
- Restart setelah proses keluar dengan error: 10, 30, lalu 60 detik berulang.
- Task logon lama dinonaktifkan, bukan dihapus. Konfigurasinya disimpan sebagai
  `previous-task.xml` dalam folder service privat.
- Folder instalasi/log: `C:\Program Files\YounzDigitalCenter\Services\YounzWhatsAppGateway`.
  Log output/error dirotasi pada 10 MB, menyimpan lima arsip per aliran.
- LocalService diberi read/execute pada gateway serta modify pada folder auth
  dan log. Service tidak berjalan sebagai Administrator/LocalSystem.

Installer menolak port milik proses yang tidak dikenali, token tidak cocok,
task bernama sama dengan konfigurasi berbeda, serta konfigurasi service yang
berbeda. Eksekusi ulang dengan konfigurasi yang sama dapat menyalakan dan
memeriksa service yang telah terpasang tanpa menimpanya.

Jika instalasi baru gagal sesudah pemindahan, installer berusaha menghentikan
dan menonaktifkan service baru serta mengaktifkan kembali task/proses lama.
Periksa hasilnya; rollback tidak menjamin WhatsApp sudah tersambung kembali.
Folder instalasi yang tertinggal sebelum service terdaftar perlu ditinjau manual.
Tidak ada folder, sesi, atau service yang dihapus otomatis.

## Pemeriksaan

```powershell
Get-Service YounzWhatsAppGateway | Select-Object Name, Status, StartType
Get-ScheduledTask -TaskName YounzWhatsAppGateway | Select-Object TaskName, State
Invoke-RestMethod http://127.0.0.1:3001/health
```

Hasil yang diharapkan: service `Running/Automatic`, task lama `Disabled`, health
`ok: true`, `state: connected`. Jangan membagikan log mentah, QR, folder auth,
atau isi `.env`; log gateway dapat mengandung identitas/pesan pelanggan.

`-VerifyRestart` menguji restart service dan koneksi ulang, **bukan reboot Windows**.
Sesudah reboot berikutnya, tunggu delayed start dan periksa lagi perintah di atas.
Komputer tetap harus menyala, tidak sleep, dan memiliki internet. Startup otomatis
tidak memperbaiki sesi yang sudah dicabut dari WhatsApp atau gangguan internet.
Tidak ada reboot komputer otomatis dari installer.

Untuk pemeriksaan tanpa perubahan:

```powershell
powershell.exe -NoProfile -File "C:\Daffa\Younz\YounzDigitalCenter\scripts\install-whatsapp-service.ps1" -CheckOnly
```
