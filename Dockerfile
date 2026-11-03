# Map-OS para Easypanel: imagem única (Apache + PHP 8.5 + cron) com o código embutido.
# Coloque este arquivo na raiz do repositório (fork do mapos) junto com docker-entrypoint.sh.

# ---------- 1) dependências PHP (composer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    --ignore-platform-reqs --optimize-autoloader

# ---------- 2) imagem final ----------
FROM php:8.5-apache-bookworm

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
 && apt-get install -y --no-install-recommends cron \
 && install-php-extensions mysqli pdo_mysql gd zip intl bcmath exif opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/* /tmp/*

# Ajustes de PHP (uploads, timezone)
RUN { \
      echo 'file_uploads=On'; \
      echo 'upload_max_filesize=20M'; \
      echo 'post_max_size=20M'; \
      echo 'memory_limit=256M'; \
      echo 'max_execution_time=600'; \
      echo 'date.timezone=America/Sao_Paulo'; \
      echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/mapos.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/application/vendor /var/www/html/application/vendor

# Pastas internas que não podem ser públicas (o .htaccess da raiz já cobre parte)
RUN rm -rf docker .git .github tests \
 && printf '<Directory /var/www/html/install>\n  <FilesMatch "^(settings\\.json|instalador\\.php|.*\\.lock)$">\n    Require all denied\n  </FilesMatch>\n</Directory>\n<Directory /var/www/html/install/view>\n  Require all denied\n</Directory>\n<DirectoryMatch "^/var/www/html/assets/(anexos|arquivos)">\n  <FilesMatch "\\.(php[0-9]*|phtml|phar|pht|phps)$">\n    Require all denied\n  </FilesMatch>\n</DirectoryMatch>\n' > /etc/apache2/conf-enabled/mapos.conf

# Cron do envio de e-mails (roda como www-data)
RUN printf '*/2 * * * * /usr/local/bin/php /var/www/html/index.php email/process >/dev/null 2>&1\n*/5 * * * * /usr/local/bin/php /var/www/html/index.php email/retry >/dev/null 2>&1\n' | crontab -u www-data -

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Tudo que precisa sobreviver a um redeploy fica em /data (monte um volume aqui)
VOLUME ["/data"]
EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
