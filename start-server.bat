@echo off
echo Starting Symfony server with extended timeout...
php -d max_execution_time=300 -d max_input_time=300 -S localhost:8000 -t public public/index.php
pause

