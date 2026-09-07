#Requires -Version 5.1
$ErrorActionPreference = 'Stop'
$repoDir = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$source = [IO.File]::ReadAllText((Join-Path $PSScriptRoot 'rotate-whatsapp-confirmation.ps1'))
$tokens = $null
$errors = $null
$ast = [Management.Automation.Language.Parser]::ParseInput($source, [ref]$tokens, [ref]$errors)
if ($errors.Count) { throw 'PowerShell syntax failed.' }
$envDigest = (Get-FileHash -LiteralPath (Join-Path $repoDir '.env')).Hash

# Test only selected statements from the rotation script, without invoking rotation.
$credentialDir = Join-Path $repoDir ('.codex\rotation-tests\' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $credentialDir | Out-Null
$userSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
$aclCommands = $ast.FindAll({ param($node)
    $node -is [Management.Automation.Language.CommandAst] -and $node.GetCommandName() -eq 'icacls.exe'
}, $true)
if ($aclCommands.Count -ne 1) { throw 'Expected one ACL command.' }
& ([scriptblock]::Create($aclCommands[0].Extent.Text)) | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'ACL command failed.' }
$acl = Get-Acl -LiteralPath $credentialDir
$allowed = @($userSid, 'S-1-5-18', 'S-1-5-32-544')
$rules = @($acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
if (-not $acl.AreAccessRulesProtected -or $rules.Count -ne 3) { throw 'ACL is not restricted.' }
foreach ($rule in $rules) {
    if ($rule.IdentityReference.Value -notin $allowed -or $rule.IsInherited) { throw 'Unexpected ACL entry.' }
}
$fixture = Join-Path $credentialDir 'fixture.txt'
[IO.File]::WriteAllText($fixture, 'NON-SECRET TEST FILE')
if ([IO.File]::ReadAllText($fixture) -ne 'NON-SECRET TEST FILE') { throw 'File readback failed.' }
Write-Output 'PASS: protected folder ACL, exactly three allowed SIDs, file write/read.'

$hash = '$argon2id$v=19$m=65536,t=4,p=1$1234567890$abcdefghijklmnopqrstuvwxyz'
$originalEnv = "BEFORE=keep`r`nYOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH=old`r`n`r`nAFTER=keep`r`n"
foreach ($name in @('$pattern', '$replacement', '$newEnv')) {
    $assignments = $ast.FindAll({ param($node)
        $node -is [Management.Automation.Language.AssignmentStatementAst] -and $node.Left.Extent.Text -eq $name
    }, $true)
    if ($assignments.Count -ne 1) { throw 'Expected one replacement assignment.' }
    . ([scriptblock]::Create($assignments[0].Extent.Text))
}
$expected = "BEFORE=keep`r`nYOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH='" + $hash + "'`r`n`r`nAFTER=keep`r`n"
if ($newEnv -cne $expected) { throw 'Hash replacement changed dollar signs or surrounding lines.' }
Write-Output 'PASS: literal Argon2 dollar signs, quoted hash, surrounding lines preserved.'
if ((Get-FileHash -LiteralPath (Join-Path $repoDir '.env')).Hash -ne $envDigest) { throw 'Live .env changed.' }
Write-Output "PASS: PowerShell $($PSVersionTable.PSVersion), live .env unchanged, rotation not executed."
