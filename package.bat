@echo off
setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0"

set "SRC=%~dp0"
if "%SRC:~-1%"=="\" set "SRC=%SRC:~0,-1%"
set "DEST=%SRC%\.."
set "VER=1.0.0"

for /f "tokens=3" %%A in ('findstr /R /C:"Version:" "%SRC%\jsontoimg.php"') do (
  set "VER=%%A"
  goto :have_ver
)
:have_ver

rem WordPress Upload Plugin uses the zip filename as the destination folder.
rem The zip MUST be named jsontoimg.zip and contain jsontoimg/jsontoimg.php.
set "OUT=%DEST%\jsontoimg.zip"
set "OUT_VER=%DEST%\jsontoimg-!VER!.zip"
set "STAGE=%TEMP%\jsontoimg-pkg-%RANDOM%%RANDOM%"

if exist "%OUT%" del /f "%OUT%"
if exist "%OUT_VER%" del /f "%OUT_VER%"
mkdir "%STAGE%\jsontoimg" >nul

robocopy "%SRC%" "%STAGE%\jsontoimg" /E /XD node_modules .git graft /XF *.zip package.bat package.sh /NFL /NDL /NJH /NJS /NP
if errorlevel 8 (
  echo Failed to copy plugin files.
  rmdir /s /q "%STAGE%" 2>nul
  exit /b 1
)

rem tar.exe writes forward-slash zip entries that Linux hosting can extract.
tar.exe -a -c -f "%OUT%" -C "%STAGE%" jsontoimg
if errorlevel 1 (
  echo Failed to create zip.
  rmdir /s /q "%STAGE%" 2>nul
  exit /b 1
)

copy /y "%OUT%" "%OUT_VER%" >nul
rmdir /s /q "%STAGE%"

echo Created %OUT%
echo Created %OUT_VER%  ^(GitHub/archive only — do not upload this in WP Admin^)
echo.
echo Upload jsontoimg.zip via Plugins - Add New - Upload Plugin.
endlocal
exit /b 0
