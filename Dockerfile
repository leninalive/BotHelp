FROM php:7.4-alpine

RUN docker-php-ext-install opcache pcntl \
 && apk update \
 && apk add gcc g++ make libtool automake autoconf git zlib-dev libzip-dev \
 && docker-php-ext-install zip \
 && git clone https://github.com/phpredis/phpredis.git /tmp/phpredis \
 && cd /tmp/phpredis \
 && phpize \
 && ./configure \
 && make \
 && make install \
 && docker-php-ext-enable redis \
 && mkdir -p /srv/worker \
 && cd /srv/worker \
 && rm -rf /tmp/phpredis \
 && apk del gcc g++ make libtool automake autoconf git \
 && rm -rf /var/cache/apk/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /srv/worker

CMD ["bin/console", "demo:consume"]