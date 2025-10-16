FROM php:8.2-apache

# Install dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libssl-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo_mysql gd mbstring zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Install PHP dependencies (including PHPMailer)
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
