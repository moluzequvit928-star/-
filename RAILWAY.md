# Запуск проекта на Railway

## 1) Подготовка
- Установи и авторизуйся в Railway CLI:
  - `npm i -g @railway/cli`
  - `railway login`

## 2) Создай проект и базу
- В панели Railway создай новый проект.
- Добавь сервис **MySQL** (New -> Database -> MySQL).
- Добавь сервис для приложения (Deploy from repo или Empty Service + upload).

## 3) Подключи переменные окружения (для сервиса приложения)
Обычно Railway сам пробрасывает их от MySQL сервиса. Убедись, что есть:
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

Дополнительно для интеграции переаттестации с Google Sheets / Apps Script:
- `GOOGLE_SHEET_ID` - ID таблицы (если отличается от текущего дефолта)
- `MAIN_SHEET_GID` - gid основного листа для `index.php`/`api.php`
- `REATTESTATION_GID` - gid вкладки "Переаттестация"
- `APP_SCRIPT_WEBHOOK_URL` - URL Web App из Google Apps Script (куда отправляем "сдал")
- `APP_SCRIPT_WEBHOOK_TOKEN` - опциональный токен для валидации запросов

## 4) Деплой
Проект уже подготовлен под Docker (`Dockerfile` в корне).

Вариант A (через GitHub):
- Залей проект в GitHub
- В Railway: Deploy from GitHub Repo

Вариант B (через CLI):
- В папке проекта:
  - `railway init`
  - `railway up`

## 5) Публичная ссылка
- В Railway открой сервис приложения -> **Settings / Networking**
- Нажми **Generate Domain**
- Получишь прямую ссылку вида `https://<имя>.up.railway.app`

## Важно
- Файлы `users.json` и `uploads` в контейнере могут теряться при пересборке.
- Для продакшена лучше хранить пользователей и файлы в БД/объектном хранилище (S3/R2).
