FROM php:8.2-apache

# Image metadata
LABEL org.opencontainers.image.source="https://github.com/Comune-di-Montesilvano/valori-venali"
LABEL org.opencontainers.image.maintainer="Comune-di-Montesilvano"

# Installa estensioni PHP necessarie
RUN docker-php-ext-install pdo pdo_mysql

# Abilita mod_rewrite per URL friendly
RUN a2enmod rewrite

# Configura Apache per permettere .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Imposta upload max size per CSV OMI
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M"       >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
