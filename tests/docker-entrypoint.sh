#!/bin/bash
set -e

echo "Ensuring WordPress files are present..."

# If /var/www/html/index.php is missing, copy WordPress core files.
if [ ! -f /var/www/html/index.php ]; then
    echo "Copying WordPress core files..."
    cp -R /usr/src/wordpress/. /var/www/html/
fi

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
                     --allow-root \
                     --extra-php="\$_SERVER['HTTPS'] = 'on'; define('FORCE_SSL_ADMIN', true);"
fi

# Auto-install WordPress if not already installed.
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

# Install and activate WooCommerce if not installed.
if ! wp plugin is-installed woocommerce --allow-root; then
    echo "Installing and activating WooCommerce..."
    wp plugin install woocommerce --activate --allow-root
fi

# Activate your custom plugin.
if ! wp plugin is-active sokin-woocommerce-plugin --allow-root; then
    echo "Activating custom plugin..."
    wp plugin activate sokin-woocommerce-plugin --allow-root
fi

# Configure Sokin Pay Gateway Settings
echo "Configuring Sokin Pay settings..."
SETTINGS_JSON="{\"enabled\": \"yes\", \"title\": \"Sokin Pay (Test)\", \"description\": \"Pay via Sokin Test Env\", \"woo_cpay_redirect_url\": \"${SOKIN_REDIRECT_URL:-https://portal.sandbox.sokin.com/sokinpay/customerPay}\", \"woo_cpay_x_api_key\": \"${SOKIN_X_API_KEY:-dummy_api_key}\", \"woo_cpay_api_url\": \"${SOKIN_API_URL:-https://api.sandbox.sokin.net/api/services/v1}\"}"
wp option update woocommerce_sokinpay_gateway_settings "$SETTINGS_JSON" --format=json --allow-root

echo "Starting Apache..."
exec apache2-foreground
