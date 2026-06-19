#!/bin/bash
set -e

echo "⏳ Attente de la base de données..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 2
done

echo "✅ Base de données prête"
echo "🔄 Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "🌱 Chargement des fixtures Super Admin..."
php bin/console doctrine:fixtures:load --no-interaction --append --group=SuperAdmin 2>/dev/null || true

echo "🚀 Démarrage PHP-FPM..."
exec php-fpm