$ErrorActionPreference = 'Stop'

Set-Location 'E:\xampp\htdocs\akses2-laravel'

& 'E:\xampp\php\php.exe' artisan serve --host=127.0.0.1 --port=8000
