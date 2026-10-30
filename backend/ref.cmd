@echo off
REM Usage: ref <saved-response.json>
php -r "echo json_decode(file_get_contents($argv[1]))->data->reference, PHP_EOL;" "%~1"
