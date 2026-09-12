FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    curl \
    libzip-dev \
    unzip \
    git \
    default-mysql-client \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ >/dev/null || exit 1

CMD ["apache2-foreground"]

RUN cat > /etc/apache2/conf-available/assessment-public.conf <<'APACHEEOF'
<Directory /var/www/html/public>
    AllowOverride All
    Require all granted
</Directory>
APACHEEOF

RUN a2enconf assessment-public
