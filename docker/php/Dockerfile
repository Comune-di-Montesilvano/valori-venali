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

# php.ini di produzione: l'immagine ufficiale non ne ha uno e PHP senza ini
# mostra gli errori nella pagina (percorsi, query). Errori solo nel log
# (stderr → docker logs); E_ALL anche per le deprecation, che lo smoke test
# cerca nei log. expose_php Off: niente versione PHP negli header (anche
# php.ini-production la lascia On).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' 'error_reporting = E_ALL' 'display_errors = Off' 'log_errors = On' 'error_log = /dev/stderr' \
       'expose_php = Off' > "$PHP_INI_DIR/conf.d/zz-hardening.ini" \
    && sed -i -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' \
       /etc/apache2/conf-available/security.conf

# Abilita mod_rewrite per URL friendly
RUN a2enmod rewrite

# Configura Apache per permettere .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Apache resta su 80 anche come www-data (USER sotto): Docker >= 20.10 imposta
# net.ipv4.ip_unprivileged_port_start=0 nei container (il compose lo dichiara
# comunque), quindi niente capability. Porta invariata: il mapping del compose
# (scaricato da git da Portainer) vale per le immagini vecchie e nuove.

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
EXPOSE 80
