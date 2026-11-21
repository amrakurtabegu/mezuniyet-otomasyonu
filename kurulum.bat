@echo off
chcp 65001 >nul
rem XAMPP'ta veritabanını kurar/yükseltir ve demo verisini yükler. Önce XAMPP'ta MySQL'i başlatın.
set PHP=C:\xampp-new\php\php.exe
cd /d "%~dp0"
echo [1/3] Veritabani gocu (mevcut veriler silinmez)...
"%PHP%" database\migrate.php || goto hata
echo [2/3] Surum 3 is akisi gocu (yeni durumlar, belge ve liste alanlari)...
"%PHP%" database\migrate_v3.php || goto hata
echo [3/3] Demo verisi...
"%PHP%" database\seed.php || goto hata
echo.
echo Tamam. Tarayicida: http://localhost/mezuniyet-otomasyonu/
pause
exit /b 0
:hata
echo HATA olustu, yukaridaki mesaji okuyun.
pause
exit /b 1
