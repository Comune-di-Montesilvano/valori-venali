FROM mariadb:11@sha256:d36f18d65a0459510b8b0f643b226a6d299e3206486e2b64ff20754a6d291707

# Copia gli script di inizializzazione nella directory usata dall'image ufficiale
COPY initdb/ /docker-entrypoint-initdb.d/

USER mysql
