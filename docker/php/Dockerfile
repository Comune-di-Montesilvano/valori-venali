FROM php:8.2-apache

# Image metadata
ARG APP_VERSION=dev
ARG GITHUB_URL=https://github.com/Comune-di-Montesilvano/valori-venali
ENV APP_VERSION=$APP_VERSION
ENV GITHUB_URL=$GITHUB_URL
LABEL org.opencontainers.image.source=$GITHUB_URL
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

# Copia i sorgenti dell'app nell'immagine (build context: repository root)
WORKDIR /var/www/html
COPY src/ /var/www/html/

# Assicura permessi corretti per www-data e crea cartella uploads
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
