@echo off
title Futurama Bot - Autorestart
:loop
echo [%DATE% %TIME%] Запуск бота...
node bot.js
echo [%DATE% %TIME%] Бот упал! Перезапуск через 5 секунд...
timeout /t 5
goto loop
