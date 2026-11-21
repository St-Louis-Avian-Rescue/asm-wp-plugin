# wp-cli plus Composer, for the cli and tools services.
FROM wordpress:cli
USER root
RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
USER www-data
