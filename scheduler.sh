#!/bin/sh
# ─────────────────────────────────────────────────────────────────────────────
# FutGO v2.1 — disparador del scheduler de Laravel para Hostinger.
#
# En Hostinger se configura UN ÚNICO cron job (cada minuto) que ejecuta este
# script. Laravel decide internamente qué comandos corresponden en cada minuto
# (ver routes/console.php): predictions:lock, notifications:reminders,
# results:sync (polla) y torneos:match-reminders (torneos).
#
# Cron en hPanel → Cron Jobs (cada minuto):
#   /bin/sh /home/USUARIO/domains/futgo.com/public_html/scheduler.sh
#
# O directamente, sin script:
#   * * * * * cd /home/USUARIO/.../public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
# ─────────────────────────────────────────────────────────────────────────────

# Ruta absoluta al proyecto en Hostinger (ajustar al desplegar).
APP_DIR="$(cd "$(dirname "$0")" && pwd)"

# Binario de PHP de Hostinger (ajustar a la versión del hosting, p.ej. php8.3).
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_DIR" || exit 1
"$PHP_BIN" artisan schedule:run >> storage/logs/scheduler.log 2>&1
