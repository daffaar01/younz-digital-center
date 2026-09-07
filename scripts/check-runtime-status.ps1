$ErrorActionPreference = 'Stop'

$services = @(
    @{ Name = 'postgresql-x64-18'; Label = 'PostgreSQL (database utama)' }
    @{ Name = 'mysql';             Label = 'MariaDB XAMPP (legacy)' }
    @{ Name = 'Apache2.4';         Label = 'Apache origin (port 8081)' }
    @{ Name = 'YounzOrigin';       Label = 'FrankenPHP origin (port 8092)' }
    @{ Name = 'YounzFrontend';     Label = 'Next.js frontend (port 3100)' }
    @{ Name = 'YounzQueue';        Label = 'Laravel queue worker' }
    @{ Name = 'YounzScheduler';    Label = 'Laravel scheduler' }
    @{ Name = 'Cloudflared';       Label = 'Cloudflare Tunnel' }
)

$endpoints = @(
    @{ Label = 'Frontend health';   Url = 'http://127.0.0.1:3100/api/health'; Expect = 200 }
    @{ Label = 'Laravel origin';    Url = 'http://127.0.0.1:8081/up'; Host = 'younzdigitalcenter.my.id'; Expect = 200 }
    @{ Label = 'Publik';            Url = 'https://younzdigitalcenter.my.id/'; Expect = 200 }
    @{ Label = 'Admin masuk';       Url = 'https://admin.younzdigitalcenter.my.id/admin/masuk'; Expect = 200 }
    @{ Label = 'Admin dashboard';   Url = 'https://admin.younzdigitalcenter.my.id/admin/dashboard'; Expect = 200 }
)

Write-Host ''
Write-Host 'Younz Digital Center - Status Runtime' -ForegroundColor Cyan
Write-Host '------------------------------------'

$problems = @()

foreach ($service in $services) {
    $found = Get-Service -Name $service.Name -ErrorAction SilentlyContinue

    if (-not $found) {
        Write-Host ('  [?] {0,-32} service tidak ditemukan' -f $service.Label) -ForegroundColor Yellow
        $problems += $service.Label
        continue
    }

    if ($found.Status -eq 'Running') {
        Write-Host ('  [OK] {0,-32} {1}' -f $service.Label, $found.Status) -ForegroundColor Green
        continue
    }

    Write-Host ('  [!!] {0,-32} {1}' -f $service.Label, $found.Status) -ForegroundColor Red
    $problems += $service.Label
}

Write-Host ''
Write-Host 'Pemeriksaan endpoint' -ForegroundColor Cyan
Write-Host '-------------------'

foreach ($endpoint in $endpoints) {
    $arguments = @('-s', '-o', 'NUL', '-w', '%{http_code}', '-m', '15')

    if ($endpoint.ContainsKey('Host')) {
        $arguments += @('-H', ('Host: {0}' -f $endpoint.Host))
    }

    $arguments += $endpoint.Url

    $code = (& curl.exe @arguments) 2>$null

    if ("$code" -eq "$($endpoint.Expect)") {
        Write-Host ('  [OK] {0,-32} HTTP {1}' -f $endpoint.Label, $code) -ForegroundColor Green
        continue
    }

    Write-Host ('  [!!] {0,-32} HTTP {1}' -f $endpoint.Label, $code) -ForegroundColor Red
    $problems += $endpoint.Label
}

Write-Host ''

if ($problems.Count -eq 0) {
    Write-Host 'Semua komponen berjalan normal.' -ForegroundColor Green
    exit 0
}

Write-Host ('Perlu perhatian: {0}' -f ($problems -join ', ')) -ForegroundColor Yellow
Write-Host 'Jalankan skrip ini sebagai Administrator, lalu gunakan:' -ForegroundColor Yellow
Write-Host '  Restart-Service Apache2.4, YounzOrigin, YounzFrontend, YounzQueue, YounzScheduler' -ForegroundColor Yellow
exit 1
