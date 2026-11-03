#!/bin/sh
# Liga os arquivos/pastas que precisam persistir ao volume /data.
set -e
APP=/var/www/html
DATA=/data

mkdir -p "$DATA/storage" "$DATA/assets" "$DATA/logs"

# Pastas de upload: na 1ª vez copia o conteúdo original para o volume
for d in anexos arquivos userImage; do
  if [ ! -d "$DATA/assets/$d" ]; then
    cp -a "$APP/assets/$d" "$DATA/assets/$d" 2>/dev/null || mkdir -p "$DATA/assets/$d"
  fi
  rm -rf "$APP/assets/$d"
  ln -s "$DATA/assets/$d" "$APP/assets/$d"
done

# Logs do sistema
rm -rf "$APP/application/logs" && ln -s "$DATA/logs" "$APP/application/logs"

# Pasta de anexos (STORAGE_PATH) - o instalador sugere /var/www/mapos-storage
rm -rf /var/www/mapos-storage && ln -s "$DATA/storage" /var/www/mapos-storage

# .env gerado pelo instalador vive no volume
ln -sf "$DATA/.env" "$APP/application/.env"

# Já instalado? então remove o instalador (recomendação do projeto)
if [ -f "$DATA/.env" ]; then
  rm -rf "$APP/install"
fi

chown -R www-data:www-data "$DATA" "$APP/application" "$APP/updates" 2>/dev/null || true

# cron em segundo plano (e-mails)
cron

exec "$@"
