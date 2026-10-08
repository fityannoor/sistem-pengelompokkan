
$ErrorActionPreference = 'Stop'

$principal = New-Object Security.Principal.WindowsPrincipal(
    [Security.Principal.WindowsIdentity]::GetCurrent()
)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'PowerShell harus dijalankan sebagai Administrator.'
}

$apache = 'C:\xampp\apache\bin\httpd.exe'
$databasePrograms = @(
    'C:\xampp\mysql\bin\mysqld.exe',
    'C:\Program Files\MariaDB 11.8\bin\mysqld.exe'
)
$ruleName = 'Skripsi K-Means Apache Hotspot'
$hotspotSubnet = '192.168.137.0/24'

if (-not (Test-Path -LiteralPath $apache)) {
    throw "Apache tidak ditemukan pada $apache"
}

Get-NetFirewallApplicationFilter |
    Where-Object { $_.Program -ieq $apache } |
    Get-NetFirewallRule |
    Where-Object { $_.Direction -eq 'Inbound' -and $_.Action -eq 'Allow' } |
    Disable-NetFirewallRule | Out-Null

Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue |
    Remove-NetFirewallRule

New-NetFirewallRule `
    -DisplayName $ruleName `
    -Description 'Akses aplikasi hanya dari perangkat pada hotspot Windows laptop server.' `
    -Direction Inbound `
    -Action Allow `
    -Program $apache `
    -Protocol TCP `
    -LocalPort 80,443 `
    -RemoteAddress $hotspotSubnet `
    -Profile Any | Out-Null

foreach ($program in $databasePrograms) {
    Get-NetFirewallApplicationFilter |
        Where-Object { $_.Program -ieq $program } |
        Get-NetFirewallRule |
        Where-Object { $_.Direction -eq 'Inbound' -and $_.Action -eq 'Allow' } |
        Disable-NetFirewallRule | Out-Null
}

Write-Host 'Konfigurasi firewall selesai.' -ForegroundColor Green
Write-Host "Website diizinkan dari: $hotspotSubnet"
Write-Host 'Izin jaringan masuk langsung ke MariaDB telah dinonaktifkan.'

Get-NetFirewallRule -DisplayName $ruleName |
    Get-NetFirewallAddressFilter |
    Select-Object Name, RemoteAddress |
    Format-Table -AutoSize
