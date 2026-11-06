# Web hosting image (Nginx + PHP-FPM) for Pterodactyl.
#
# Like the official "yolks": everything happens in /home/container, without root and on a read-only system, and the
# entrypoint runs the startup command of the egg. One image per PHP version, built on the machine of Wings by install.sh:
#
#   docker build --build-arg PHP_VERSION=8.3 -t modern-indigo/web:php8.3 .
FROM debian:bookworm-slim

ARG PHP_VERSION=8.3

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/sury-php.gpg \
    && echo "deb https://packages.sury.org/php/ bookworm main" >/etc/apt/sources.list.d/sury-php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        tini procps iproute2 git unzip bzip2 nginx \
        "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-pgsql" \
        "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-mbstring" \
        "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-gmp" "php${PHP_VERSION}-soap" "php${PHP_VERSION}-imagick" "php${PHP_VERSION}-redis" \
        "php${PHP_VERSION}-apcu" "php${PHP_VERSION}-opcache" \
    && curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer \
    && curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod 755 /usr/local/bin/composer /usr/local/bin/wp \
    && rm -rf /var/lib/apt/lists/*

RUN useradd -m -d /home/container -s /bin/bash container

COPY entrypoint.sh /entrypoint.sh
COPY web-start web-install /usr/local/bin/
COPY templates/ /etc/web/
RUN chmod 755 /entrypoint.sh /usr/local/bin/web-start /usr/local/bin/web-install

USER container
ENV USER=container HOME=/home/container
WORKDIR /home/container

ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/bin/bash", "/entrypoint.sh"]
