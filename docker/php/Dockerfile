# Asset frontend (Bootstrap Italia + font) — versioni in package.json,
# aggiornate da Dependabot (ecosistema npm). Serviti dall'app, niente CDN.
# Immagini base fissate per digest: Dependabot aggiorna tag e digest insieme.
FROM node:26-alpine@sha256:40cd011b55415bc894e45b02fdd5a5dfde056921b7d23a64c74f80497af48797 AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

FROM php:8.5-apache@sha256:fce5eaf640e8fb620aac9aaf53efebe5584b3f3c1f2033b78b08be252be92adc

# Image metadata
ARG APP_VERSION=dev
ARG APP_REVISION=unknown
ARG APP_IS_TAG=false
ARG GITHUB_URL=https://github.com/Comune-di-Montesilvano/valori-venali
ENV APP_VERSION=$APP_VERSION
ENV APP_REVISION=$APP_REVISION
ENV APP_IS_TAG=$APP_IS_TAG
ENV GITHUB_URL=$GITHUB_URL
LABEL org.opencontainers.image.source=$GITHUB_URL
# Commit sorgente: rebuild-check.sh lo confronta con il target di :latest
LABEL org.opencontainers.image.revision=$APP_REVISION
LABEL org.opencontainers.image.maintainer="Comune-di-Montesilvano"

# Patch di sicurezza Debian uscite dopo l'ultima build dell'immagine base.
# PKG_REFRESH (data del giorno, da release.yml) invalida questo layer una
# volta al giorno: senza, la cache registry lo congelerebbe.
ARG PKG_REFRESH=none
RUN echo "pkg refresh: $PKG_REFRESH"     && apt-get update \
    && apt-get upgrade -y --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Installa estensioni PHP necessarie
RUN docker-php-ext-install pdo pdo_mysql

# Abilita mod_rewrite per URL friendly
RUN a2enmod rewrite

# Configura Apache per permettere .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Apache su 8080: gira come www-data (USER sotto), senza root né capability
# per aprire porte < 1024. Il compose mappa ${APP_PORT}:8080.
RUN sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Upload (logo/favicon): mai eseguiti come PHP, anche se un .php ci finisse dentro
RUN printf '%s\n' \
      '<Directory /var/www/html/uploads>' \
      '    php_admin_flag engine off' \
      '    AllowOverride None' \
      '    Options -Indexes -ExecCGI' \
      '    <FilesMatch "\.(php|phtml|phar|php[0-9])$">' \
      '        Require all denied' \
      '    </FilesMatch>' \
      '</Directory>' \
      > /etc/apache2/conf-available/uploads.conf \
    && a2enconf uploads

# Imposta upload max size per CSV OMI
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M"       >> /usr/local/etc/php/conf.d/uploads.ini

# Asset fuori dal DocumentRoot (in sviluppo src/ è montato sopra /var/www/html)
COPY --from=assets /build/node_modules/bootstrap-italia/dist/ /opt/assets/bootstrap-italia/
RUN printf '%s\n' \
      'Alias /assets/bootstrap-italia /opt/assets/bootstrap-italia' \
      '<Directory /opt/assets/bootstrap-italia>' \
      '    Options -Indexes' \
      '    AllowOverride None' \
      '    Require all granted' \
      '</Directory>' \
      > /etc/apache2/conf-available/assets.conf \
    && a2enconf assets

# Copia i sorgenti dell'app nell'immagine (build context: repository root)
WORKDIR /var/www/html
COPY src/ /var/www/html/

# Codice di root, in sola lettura per www-data; scrivibile solo uploads/
# (volume app_uploads). In produzione il filesystem è read_only (compose).
RUN chown -R root:root /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && chown www-data:www-data /var/www/html/uploads

USER www-data
EXPOSE 8080
