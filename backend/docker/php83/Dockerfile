FROM php:8.3-apache
# Installer les extensions PHP nécessaires pour WordPress
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
    libjpeg62-turbo-dev \
    default-libmysqlclient-dev \
    default-mysql-client \
    build-essential \
    ca-certificates \
    gnupg \
    wget \
    procps \
    locales \
    python3 \
    python3-pip \
    python3-venv

# Install Python dependencies
RUN pip3 install --break-system-packages requests

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# Enable Apache modules
RUN a2enmod rewrite headers ssl
RUN docker-php-ext-install pdo_mysql mysqli zip
EXPOSE 80
EXPOSE 443
