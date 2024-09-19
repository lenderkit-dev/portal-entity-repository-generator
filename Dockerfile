#FROM hub.jcdev.net:24000/php8.2-fpm-bullseye:8.2.17
FROM php:8.2-fpm-bullseye

RUN apt-get update \
    && apt-get install -y libyaml-dev \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/
COPY . .
    
ENTRYPOINT ["/usr/local/bin/php", "/app/bin/peg" ]

