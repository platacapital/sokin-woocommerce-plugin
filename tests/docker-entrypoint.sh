#!/bin/bash
set -e

echo "Waiting for database connection..."
while ! mysqladmin ping -h"$WORDPRESS_DB_HOST" --silent; do
    sleep 2
done
echo "Database is up."

# Create wp-config.php if it doesn't exist.
if [ ! -f /var/www/html/wp-config.php ]; then
    echo "Creating wp-config.php..."
    wp config create --dbname="$WORDPRESS_DB_NAME" \
                     --dbuser="$WORDPRESS_DB_USER" \
                     --dbpass="$WORDPRESS_DB_PASSWORD" \
                     --dbhost="$WORDPRESS_DB_HOST" \
                     --allow-root
fi

# Automatically install WordPress if not already installed.
if ! wp core is-installed --allow-root; then
    echo "Installing WordPress..."
    wp core install --url="$VIRTUAL_HOST" \
                    --title="WordPress Test Environment" \
                    --admin_user="$ADMIN_USER" \
                    --admin_password="$ADMIN_PASS" \
                    --admin_email="admin@example.com" \
                    --skip-email \
                    --allow-root
fi

# Install and activate WooCommerce if not already installed.
if ! wp plugin is-installed woocommerce --allow-root; then
    echo "Installing and activating WooCommerce..."
    wp plugin install woocommerce --activate --allow-root
fi

# Activate your custom plugin.
if ! wp plugin is-active my-plugin --allow-root; then
    echo "Activating custom plugin..."
    wp plugin activate my-plugin --allow-root
fi

echo "Starting Apache..."
exec apache2-foreground
