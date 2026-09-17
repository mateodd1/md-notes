FROM composer:2 AS composer

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install intl pdo_mysql pdo_sqlite zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'max_file_uploads = 10'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/md-notes-uploads.ini

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/md-notes-entrypoint

RUN chmod 755 /usr/local/bin/md-notes-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["md-notes-entrypoint"]
CMD ["apache2-foreground"]
