FROM php:8.2-apache

# Extensions PHP requises par GLPI
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libldap2-dev \
    libicu-dev \
    libbz2-dev \
    libgd-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        pgsql \
        gd \
        zip \
        xml \
        intl \
        bz2 \
        opcache \
        ldap \
        mysqli \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Télécharger et installer GLPI 10.0.16
ENV GLPI_VERSION=10.0.16

RUN curl -L "https://github.com/glpi-project/glpi/releases/download/${GLPI_VERSION}/glpi-${GLPI_VERSION}.tgz" \
    -o /tmp/glpi.tgz \
    && tar -xzf /tmp/glpi.tgz -C /var/www/html/ \
    && rm /tmp/glpi.tgz \
    && chown -R www-data:www-data /var/www/html/glpi \
    && chmod -R 755 /var/www/html/glpi

# Configuration Apache pour GLPI
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/glpi/public\n\
    <Directory /var/www/html/glpi/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        RewriteEngine On\n\
        RewriteCond %{REQUEST_FILENAME} !-f\n\
        RewriteRule ^(.*)$ index.php [QSA,L]\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
