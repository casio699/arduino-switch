FROM php:8.2-apache
# Install the mysqli extension (XAMPP has this by default, Docker needs it)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
