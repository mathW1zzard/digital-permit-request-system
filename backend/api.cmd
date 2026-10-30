@echo off
REM Usage: api <user-email> <more curl arguments...>
curl -s -H "Accept: application/json" -H "Content-Type: application/json" -H "X-Demo-User: %~1" %2 %3 %4 %5 %6 %7 %8 %9
echo.
