@echo off
title Absensi - Laravel Workers
echo ==============================================
echo Menjalankan Pekerja Latar Belakang (Laragon)
echo ==============================================
echo.

cd /d "%~dp0"

echo [1/2] Menjalankan Queue Worker (untuk memproses antrean email, notifikasi, dll)...
start "Absensi - Queue Worker" cmd /c "php artisan queue:work --tries=3 --delay=5 --timeout=60"

echo [2/2] Menjalankan Scheduler (untuk menjalankan tugas cron secara periodik)...
start "Absensi - Schedule Worker" cmd /c "php artisan schedule:work"

echo.
echo Pekerja latar belakang telah dijalankan pada jendela terpisah!
echo Jendela ini dapat ditutup dengan aman.
pause
