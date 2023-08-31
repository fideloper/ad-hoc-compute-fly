FROM fideloper/fly-laravel:8.2

COPY . /var/www/html

RUN composer install --optimize-autoloader --no-dev

CMD ["php", "/var/www/html/artisan", "ad-hoc"]

