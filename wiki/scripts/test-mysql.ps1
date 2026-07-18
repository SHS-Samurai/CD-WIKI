param(
    [Parameter(Mandatory = $true)][string]$Database,
    [string]$HostName = '127.0.0.1',
    [int]$Port = 3306,
    [Parameter(Mandatory = $true)][string]$Username,
    [Parameter(Mandatory = $true)][string]$Password
)

$ErrorActionPreference = 'Stop'
if (-not $Database.EndsWith('_test')) { throw 'Der Datenbankname muss mit _test enden.' }
if ($env:ALLOW_WIKI_TEST_DB_RESET -ne 'YES') { throw 'Vor migrate:fresh muss ALLOW_WIKI_TEST_DB_RESET=YES gesetzt sein.' }

$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = $HostName
$env:DB_PORT = [string]$Port
$env:DB_DATABASE = $Database
$env:DB_USERNAME = $Username
$env:DB_PASSWORD = $Password
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'

php artisan config:clear
php artisan migrate:fresh --force
php artisan test --group=mysql
