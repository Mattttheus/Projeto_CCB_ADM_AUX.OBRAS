#!/bin/sh
set -eu

if [ "${PORT:-80}" != "80" ]; then
    sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

exec apache2-foreground