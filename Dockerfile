FROM php:8.3-fpm-alpine

# Installe nginx, sqlite, bash et utilitaires
RUN apk add --no-cache nginx sqlite sqlite-libs bash coreutils

# Prépare les dossiers
RUN mkdir -p /run/nginx /opt/scripts /var/www/showcase-website-service

# Copie config NGINX
COPY scipts-bin/NGINX_CONF.conf /etc/nginx/nginx.conf

# Copie les scripts
COPY scipts-bin/*.sh /opt/scripts/
RUN chmod +x /opt/scripts/*.sh

# Copie le entrypoint
COPY scipts-bin/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Ports
EXPOSE 80

# Lancement
ENTRYPOINT ["/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
