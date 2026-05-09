FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    git \
    unzip \
    default-libmysqlclient-dev \
    default-mysql-client \
    build-essential \
    ca-certificates \
    wget \
    procps \
    locales \
    python3 \
    python3-pip \
    python3-venv

RUN docker-php-ext-install pdo_mysql mysqli zip

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN pip3 install --break-system-packages requests || true

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
