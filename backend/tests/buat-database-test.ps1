# Membuat ulang database simpres_test untuk php artisan test.
# Struktur diambil dari docs/database/simpres.sql (tanpa data awal),
# lalu tabel sistem Laravel dibuat lewat migrate.
# Jalankan dari folder backend:  powershell -File tests\buat-database-test.ps1
# Jalankan ulang setiap kali simpres.sql berubah.

param(
    [string]$Mysql = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
)

$ErrorActionPreference = 'Stop'
$sql = Get-Content "$PSScriptRoot\..\..\docs\database\simpres.sql" -Raw
$sql = $sql.Substring(0, $sql.IndexOf('-- Data awal'))
$sql = $sql -replace 'CREATE DATABASE IF NOT EXISTS simpres', 'DROP DATABASE IF EXISTS simpres_test; CREATE DATABASE simpres_test'
$sql = $sql -replace 'USE simpres;', 'USE simpres_test;'

$sql | & $Mysql --user=root --host=127.0.0.1 --port=3306 --default-character-set=utf8mb4
if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat database simpres_test.' }

$env:DB_DATABASE = 'simpres_test'
php artisan migrate --no-interaction --env=testing
Remove-Item Env:DB_DATABASE
Write-Host 'Database simpres_test siap.'
