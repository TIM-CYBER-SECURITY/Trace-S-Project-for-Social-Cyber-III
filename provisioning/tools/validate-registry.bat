@echo off
setlocal
set ROOT=%~dp0..\..
set PHP_BIN=php
if exist "%ROOT%\.tools\php\php.exe" set PHP_BIN="%ROOT%\.tools\php\php.exe"
%PHP_BIN% "%ROOT%\artisan" trace:validate-registry %*
