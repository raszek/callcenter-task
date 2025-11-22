FROM php:8.4-fpm

RUN apt-get update
RUN apt-get install -y \
    libpng-dev \
    libpq-dev \
    libxml2-dev \
    wget \
    libzip-dev \
    unzip \
    libicu-dev \
    vim

# Installing Node
SHELL ["/bin/bash", "--login", "-i", "-c"]
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
RUN source /root/.bashrc && nvm install node
SHELL ["/bin/bash", "--login", "-c"]

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install symfony cli
RUN curl -sS https://get.symfony.com/cli/installer | bash

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

RUN docker-php-ext-install gd zip bcmath intl

WORKDIR /var/www/html/app
EXPOSE 8000

ADD ./start.sh /start.sh
RUN chmod +x /start.sh

ENTRYPOINT ["bash", "/start.sh"]
