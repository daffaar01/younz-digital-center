#Requires -Version 5.1
[CmdletBinding()]
param(
    [switch]$CheckOnly,
    [switch]$VerifyRestart
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$serviceName = 'YounzWhatsAppGateway'
$repoDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$gatewayDir = Join-Path $repoDir 'whatsapp-gateway'
$envFile = Join-Path $gatewayDir '.env'
$servicesDir = Join-Path $env:ProgramFiles 'YounzDigitalCenter\Services'
$wrapperSource = Join-Path $servicesDir 'WinSW-x64.exe'
# A dedicated subdirectory keeps this service's permissions/configuration isolated.
$installDir = Join-Path $servicesDir $serviceName
$serviceExe = Join-Path $installDir "$serviceName.exe"
$serviceXml = Join-Path $installDir "$serviceName.xml"
$logDir = Join-Path $installDir 'logs'
$nodeExe = (Get-Command node.exe -CommandType Application | Select-Object -First 1).Source
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $CheckOnly -and -not $isAdmin) {
    throw 'Buka PowerShell dengan Run as administrator, lalu jalankan skrip ini lagi. Belum ada perubahan.'
}

function Read-GatewayStatus {
    # Never emit the raw response: it can contain the linked phone, QR or error details.
    Invoke-RestMethod -Uri "$gatewayUrl/v1/status" -Headers $gatewayHeaders -TimeoutSec 5
}

function Wait-GatewayConnected {
    $deadline = (Get-Date).AddSeconds(60)
    do {
        try {
            $waStatus = Read-GatewayStatus
            if ($waStatus.connected -eq $true) { return }
        } catch { }
        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)
    throw 'Gateway belum connected dalam 60 detik. Periksa jaringan dan log privat service; jangan hapus sesi auth.'
}

function Grant-ServiceAccess([string]$Path, [string]$Rights) {
    & icacls.exe $Path /grant "*S-1-5-19:$Rights" /Q | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Gagal memberi LocalService akses ke $Path" }
}

foreach ($required in @($envFile, $wrapperSource, (Join-Path $gatewayDir 'src\server.js'),
        (Join-Path $gatewayDir 'node_modules\baileys\package.json'))) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw "File diperlukan: $required" }
}
if ((Get-Item -LiteralPath $wrapperSource).VersionInfo.FileVersion -ne '2.12.0.0') {
    throw 'Installer ini telah disiapkan untuk WinSW 2.12.0; verifikasi versi wrapper dahulu.'
}
$nodeMajor = [int]((& $nodeExe --version).TrimStart('v').Split('.')[0])
if ($nodeMajor -lt 20) { throw 'Gateway memerlukan Node.js 20 atau lebih baru.' }

# Match server.js parsing; secrets remain in memory, never arguments/XML/output.
$gatewayEnv = @{}
foreach ($line in [IO.File]::ReadAllLines($envFile)) {
    if ($line -match '^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)\s*$') {
        $key = $Matches[1]
        if (-not $gatewayEnv.ContainsKey($key)) {
            $gatewayEnv[$key] = $Matches[2] -replace '^(["''])(.*)\1$', '$2'
        }
    }
}
if ([string]$gatewayEnv['WHATSAPP_INTERNAL_TOKEN'] -eq '' -or
        $gatewayEnv['WHATSAPP_INTERNAL_TOKEN'].Length -lt 16) { throw 'Token gateway belum valid.' }
$gatewayPort = 0
if (-not [int]::TryParse($gatewayEnv['PORT'], [ref]$gatewayPort) -or $gatewayPort -lt 1 -or $gatewayPort -gt 65535) {
    throw 'PORT gateway harus diisi dengan port yang valid.'
}
$authSetting = [string]$gatewayEnv['WHATSAPP_AUTH_DIR']
if ([string]::IsNullOrWhiteSpace($authSetting)) { throw 'WHATSAPP_AUTH_DIR harus menunjuk sesi yang sudah ada.' }
if ([IO.Path]::IsPathRooted($authSetting)) { $authDir = [IO.Path]::GetFullPath($authSetting) }
else { $authDir = [IO.Path]::GetFullPath((Join-Path $gatewayDir $authSetting)) }
$dataDir = [IO.Path]::GetFullPath((Join-Path $gatewayDir 'data'))
if (-not $authDir.StartsWith($dataDir + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Auth di luar whatsapp-gateway\data: tinjau lokasi dan izin secara manual sebelum migrasi.'
}
if (-not (Test-Path -LiteralPath (Join-Path $authDir 'creds.json') -PathType Leaf)) {
    throw 'Sesi WhatsApp belum ditemukan. Installer tidak akan membuat atau mengganti sesi.'
}
$gatewayUrl = "http://127.0.0.1:$gatewayPort"
$gatewayHeaders = @{ Authorization = 'Bearer ' + $gatewayEnv['WHATSAPP_INTERNAL_TOKEN'] }
$envDigest = (Get-FileHash -LiteralPath $envFile -Algorithm SHA256).Hash

$oldTask = Get-ScheduledTask -TaskName $serviceName -TaskPath '\' -ErrorAction SilentlyContinue
if ($oldTask) {
    $actions = @($oldTask.Actions)
    if ($actions.Count -ne 1 -or $actions[0].Execute -ne $nodeExe -or
            $actions[0].WorkingDirectory.TrimEnd('\','/') -ne $gatewayDir -or
            $actions[0].Arguments -ne 'src/server.js') {
        throw 'Task dengan nama yang sama memiliki konfigurasi berbeda; tidak akan diubah otomatis.'
    }
}
$existingService = Get-CimInstance Win32_Service -Filter "Name='$serviceName'"
$template = [IO.File]::ReadAllText((Join-Path $repoDir 'deploy\YounzWhatsAppGateway.xml'))
foreach ($item in @{ NODE_EXE=$nodeExe; GATEWAY_DIR=$gatewayDir; PORT="$gatewayPort"; AUTH_DIR=$authDir; LOG_DIR=$logDir }.GetEnumerator()) {
    $template = $template.Replace("__$($item.Key)__", [Security.SecurityElement]::Escape($item.Value))
}
$desiredXml = [xml]$template
if ($existingService) {
    if ($existingService.PathName.Trim('"') -ne $serviceExe -or -not (Test-Path -LiteralPath $serviceXml)) {
        throw 'Service bernama sama bukan milik installer ini; tidak akan ditimpa.'
    }
    $installedXml = [xml][IO.File]::ReadAllText($serviceXml)
    if ($installedXml.OuterXml -ne $desiredXml.OuterXml) {
        throw 'Konfigurasi service berbeda. Tinjau manual; installer tidak menimpa konfigurasi yang ada.'
    }
}
$listeners = @(Get-NetTCPConnection -State Listen -LocalPort $gatewayPort -ErrorAction SilentlyContinue)
$listenerIds = @($listeners | Select-Object -ExpandProperty OwningProcess -Unique)
if ($listenerIds.Count -gt 1) { throw 'Lebih dari satu proses memakai port gateway; hentikan migrasi.' }
$oldProcess = $null
if ($listenerIds.Count -eq 1) {
    $candidate = Get-CimInstance Win32_Process -Filter "ProcessId=$($listenerIds[0])"
    if ($candidate.ExecutablePath -ne $nodeExe -or $candidate.CommandLine -notmatch 'src[/\\]server\.js') {
        throw 'Port gateway dipakai proses yang tidak dikenali; proses tidak akan dihentikan.'
    }
    try { $beforeStatus = Read-GatewayStatus } catch { throw 'Token lokal tidak cocok dengan gateway aktif; migrasi dibatalkan.' }
    if ($beforeStatus.connected -ne $true) { throw 'Hubungkan WhatsApp dahulu sebelum memindahkan gateway aktif.' }
    if ($existingService -and $existingService.State -eq 'Running') {
        if ($candidate.ParentProcessId -ne $existingService.ProcessId) { throw 'Ada gateway di luar service. Periksa proses ganda.' }
    } else { $oldProcess = $candidate }
}
if ($oldProcess -and -not $oldTask) { throw 'Task pemulihan tidak ditemukan; migrasi proses manual dibatalkan.' }

Write-Host "Pemeriksaan OK: port $gatewayPort, sesi lama tersedia, Node $nodeMajor, WinSW 2.12.0."
Write-Host "Service tujuan: $serviceName (Automatic Delayed Start, LocalService)."
if ($CheckOnly) {
    Write-Host "Mode pemeriksaan saja; tidak ada perubahan. Administrator: $isAdmin"
    return
}

$taskWasEnabled = $oldTask -and $oldTask.State -ne 'Disabled'
$installedNow = $false
$processStopped = $false
$taskDisabled = $false
try {
    if (-not $existingService) {
        if (Test-Path -LiteralPath $installDir) {
            throw 'Folder service sudah ada tanpa service terdaftar. Periksa sisa instalasi dahulu; tidak ditimpa.'
        }
        New-Item -ItemType Directory -Path $installDir | Out-Null
        # Restrict wrapper/config/logs to Administrators, SYSTEM, and read-only LocalService.
        & icacls.exe $installDir /inheritance:r /grant:r '*S-1-5-32-544:(OI)(CI)(F)' '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-19:(OI)(CI)(RX)' /Q | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Gagal mengamankan folder service.' }
        New-Item -ItemType Directory -Path $logDir | Out-Null
        Copy-Item -LiteralPath $wrapperSource -Destination $serviceExe
        $desiredXml.Save($serviceXml)
        if ($oldTask) {
            Export-ScheduledTask -TaskName $serviceName -TaskPath '\' |
                Set-Content -LiteralPath (Join-Path $installDir 'previous-task.xml') -Encoding Unicode
        }
        Grant-ServiceAccess $logDir '(OI)(CI)(M)'
        Grant-ServiceAccess $gatewayDir '(OI)(CI)(RX)'
        Grant-ServiceAccess $authDir '(OI)(CI)(M)'
        & $serviceExe install | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Pemasangan WinSW gagal; gateway lama belum dihentikan.' }
        $installedNow = $true
    }
    if ($taskWasEnabled) {
        Disable-ScheduledTask -TaskName $serviceName -TaskPath '\' | Out-Null
        $taskDisabled = $true
    }
    if ($oldProcess) {
        # Revalidate PID identity and authentication immediately before stopping only this gateway.
        $currentProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$($oldProcess.ProcessId)"
        if (-not $currentProcess -or $currentProcess.CreationDate -ne $oldProcess.CreationDate) {
            throw 'Proses gateway berubah saat persiapan; jalankan ulang pemeriksaan.'
        }
        $null = Read-GatewayStatus
        Stop-Process -Id $oldProcess.ProcessId -Force
        $processStopped = $true
        Start-Sleep -Seconds 2
    }
    Set-Service -Name $serviceName -StartupType Automatic
    & sc.exe config $serviceName start= delayed-auto | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Gagal mengatur delayed automatic start.' }
    Start-Service -Name $serviceName
    Wait-GatewayConnected
    if ($VerifyRestart) {
        Write-Host 'Gateway connected. Menguji restart service tanpa logout WhatsApp...'
        Restart-Service -Name $serviceName
        Wait-GatewayConnected
    }
    $verifiedService = Get-CimInstance Win32_Service -Filter "Name='$serviceName'"
    $verifiedListeners = @(Get-NetTCPConnection -State Listen -LocalPort $gatewayPort)
    $verifiedIds = @($verifiedListeners | Select-Object -ExpandProperty OwningProcess -Unique)
    if ($verifiedIds.Count -ne 1 -or @($verifiedListeners | Where-Object LocalAddress -ne '127.0.0.1').Count -ne 0) {
        throw 'Verifikasi listener lokal tunggal gagal.'
    }
    $verifiedNode = Get-CimInstance Win32_Process -Filter "ProcessId=$($verifiedIds[0])"
    if ($verifiedNode.ParentProcessId -ne $verifiedService.ProcessId -or
            $verifiedService.State -ne 'Running' -or $verifiedService.StartMode -ne 'Auto' -or
            $verifiedService.StartName -ne 'NT AUTHORITY\LocalService') {
        throw 'Identitas/status service tidak sesuai konfigurasi.'
    }
    if ((Get-FileHash -LiteralPath $envFile -Algorithm SHA256).Hash -ne $envDigest) {
        throw 'File .env gateway berubah selama pemasangan; periksa sebelum melanjutkan.'
    }
    Write-Host 'BERHASIL: service Running/Automatic, WhatsApp connected, satu listener lokal, .env tidak berubah.' -ForegroundColor Green
    if ($VerifyRestart) { Write-Host 'Uji restart service berhasil. Reboot komputer belum diuji.' }
} catch {
    $failure = $_
    # Never remove auth data or uninstall anything as part of rollback.
    if ($installedNow) {
        Stop-Service -Name $serviceName -ErrorAction SilentlyContinue
        Set-Service -Name $serviceName -StartupType Disabled -ErrorAction SilentlyContinue
    }
    $rollbackService = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
    $serviceStillRunning = $rollbackService -and $rollbackService.Status -eq 'Running'
    if (-not $serviceStillRunning) {
        if ($taskDisabled) { Enable-ScheduledTask -TaskName $serviceName -TaskPath '\' | Out-Null }
        if ($processStopped -and $oldTask) {
            if (-not $taskWasEnabled) { Enable-ScheduledTask -TaskName $serviceName -TaskPath '\' | Out-Null }
            Start-ScheduledTask -TaskName $serviceName -TaskPath '\'
            if (-not $taskWasEnabled) { Disable-ScheduledTask -TaskName $serviceName -TaskPath '\' | Out-Null }
            Write-Warning 'Service gagal; task gateway lama diminta berjalan kembali. Periksa /health.'
        }
    }
    throw $failure
}
