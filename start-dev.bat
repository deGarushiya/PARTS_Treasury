@echo off
echo Starting PARTS Treasury System...
echo.

echo Starting Laravel Backend...
start cmd /k "php artisan serve"

echo Waiting for backend to start...
timeout /t 3 /nobreak > nul

echo Starting React Frontend...
start cmd /k "cd main && npm start"

echo.
echo ========================================
echo Backend running at: http://localhost:8000
echo Frontend running at: http://localhost:3000
echo ========================================
echo.
echo Press any key to continue...
pause > nul

