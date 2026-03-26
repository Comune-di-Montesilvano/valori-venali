FROM mariadb:11

# Copia gli script di inizializzazione nella directory usata dall'image ufficiale
COPY initdb/ /docker-entrypoint-initdb.d/

USER mysql
