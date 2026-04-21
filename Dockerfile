FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    curl \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork \
    && a2enmod rewrite


WORKDIR /var/www/html
COPY . /var/www/html/

# Копируем PHP конфиг для больших файлов
COPY php.ini /usr/local/etc/php/conf.d/large-files.ini

# Устанавливаем зависимости для Discord бота
RUN npm install

# Папки для сохранения загруженных картинок и откатов
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/rollbacks \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/rollbacks

# Создаем скрипт, который запускает встроенный сервер PHP и бота (без глючного Apache)
RUN echo '#!/bin/bash\nnode bot.js &\nexec php -S 0.0.0.0:${PORT:-80} -t /var/www/html\n' > /start.sh \
    && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
