FROM php:8.2-cli-bullseye

# generic dependencies
RUN apt-get update \
    && apt-get install -y iproute2

# php extensions
RUN apt-get install -y libyaml-dev \
    && pecl install yaml \
    && docker-php-ext-enable yaml

# cleanup
RUN apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/
COPY . .

RUN mv docker-entrypoint.sh /usr/local/bin/ \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /app/bin/peg

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/app/bin/peg"]

