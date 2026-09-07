$ErrorActionPreference = 'Stop'
$env:APP_ENV = 'xampp'

$php = 'C:\xampp\php84-sampitmart\php.exe'
$artisan = 'C:\Daffa\Younz\YounzDigitalCenter\artisan'
$log = 'C:\Daffa\Younz\YounzDigitalCenter\storage\logs\xampp-topup-reconciliation.log'

& $php $artisan midtrans:reconcile-payments --limit=50 --no-interaction *>> $log
exit $LASTEXITCODE
