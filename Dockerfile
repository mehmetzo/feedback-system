
FROM php:8.2-apache

# Gerekli PHP eklentileri
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libldap2-dev \
    ldap-utils \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install pdo pdo_mysql mysqli gd zip ldap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Timezone ayarı
RUN echo "date.timezone = Europe/Istanbul" > /usr/local/etc/php/conf.d/timezone.ini

# Apache mod_rewrite
RUN a2enmod rewrite

# Varsa eski custom.conf kalıntılarını temizle
RUN rm -f /etc/apache2/conf-enabled/custom.conf \
    && rm -f /etc/apache2/conf-available/custom.conf

# AllowOverride None → All (yeni dosya oluşturmadan, sed ile)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Doğrula
RUN apachectl configtest

# Composer (QR kütüphanesi için)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# QR kod kütüphanesi kur
COPY www/composer.json .
RUN composer install --no-dev --optimize-autoloader 2>/dev/null || true

EXPOSE 80

CMD ["apache2-foreground"]
