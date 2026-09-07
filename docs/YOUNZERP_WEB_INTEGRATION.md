# Integrasi YOUNZ ERP ke Web YounzDigitalCenter

Web utama tetap menggunakan frontend `frontend/` milik YounzDigitalCenter.
Workspace ERP tersedia di `/admin/erp` dan memakai API Laravel YOUNZERP yang
sama dengan aplikasi Flutter Android.

## Lokal

Jalankan API YOUNZERP pada `http://127.0.0.1:8001`, lalu jalankan frontend:

```powershell
cd C:\Daffa\Younz\YounzDigitalCenter\frontend
npm run dev
```

Buka `http://localhost:3000/admin/erp`.

Konfigurasi frontend berada di `frontend/.env.local`:

```env
NEXT_PUBLIC_YOUNZERP_API_URL=/api/younz-erp
YOUNZERP_API_URL=http://127.0.0.1:8001/api/v1
```

Browser memanggil proxy same-origin `/api/younz-erp/*`. Proxy meneruskan
Authorization, `X-Business-Id`, dan body request ke API ERP.

## Production

Gunakan pembagian host berikut:

```text
https://younzdigitalcenter.my.id              website utama
https://erp.younzdigitalcenter.my.id/api/v1   Laravel API YOUNZ ERP
```

Deploy API YOUNZERP pada subdomain `erp.younzdigitalcenter.my.id`, lalu pada
environment server frontend ubah `YOUNZERP_API_URL` menjadi:

```env
YOUNZERP_API_URL=https://erp.younzdigitalcenter.my.id/api/v1
```

`NEXT_PUBLIC_YOUNZERP_API_URL` tetap `/api/younz-erp` agar browser memakai
proxy same-origin website utama. Jangan menaruh `MIDTRANS_SERVER_KEY`,
`DIGIFLAZZ_USERNAME`, atau `DIGIFLAZZ_API_KEY` di frontend. Kredensial provider
tetap dibaca Laravel API dari konfigurasi server.

API harus dikonfigurasi dengan `APP_URL=https://erp.younzdigitalcenter.my.id`
dan CORS yang mengizinkan website utama serta subdomain ERP. Android langsung
memakai URL subdomain tersebut ketika release build dibuat.

Webhook pembayaran tetap harus menunjuk ke endpoint API YOUNZERP, bukan route
webhook lama YounzDigitalCenter:

```env
YOUNZERP_MIDTRANS_NOTIFICATION_URL=https://erp.younzdigitalcenter.my.id/api/v1/integrations/midtrans/webhook
YOUNZERP_DIGIFLAZZ_WEBHOOK_URL=https://erp.younzdigitalcenter.my.id/api/v1/integrations/digiflazz/webhook
```
