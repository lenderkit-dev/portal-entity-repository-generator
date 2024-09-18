FROM hub.jcdev.net:24000/php8.2-fpm-bullseye:8.2.17

RUN apt-get update \
    && apt-get install -y libyaml-dev \
    && docker-php-ext-enable yaml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . .
