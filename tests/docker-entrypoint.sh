#!/bin/bash
set -e # Exit immediately if a command exits with a non-zero status.

# --- 1. Set working directory ---
cd /var/www/html

# The official wordpress image's entrypoint is replaced, so we must handle the initial file copy.
if [ ! -f /var/www/html/index.php ]; then
    echo "WordPress core not found. Copying files from /usr/src/wordpress..."
    # Copy everything from the source, including hidden files.
    cp -a /usr/src/wordpress/. /var/www/html/
    echo "WordPress files copied."
fi

# --- 2. Wait for Database ---
echo "Waiting for database connection..."
# Extract hostname from WORDPRESS_DB_HOST (e.g., db:3306 -> db)
DB_HOSTNAME=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f1)
attempts=0
# Loop for a maximum of 30 attempts (60 seconds)
while ! mysqladmin ping -h"$DB_HOSTNAME" --silent; do
    attempts=$((attempts+1))
    if [ $attempts -gt 30 ]; then
        echo "FATAL: Database connection timed out after 60 seconds."
        exit 1
    fi
    sleep 2
done
echo "Database connection is ready."

# --- 3. Create wp-config.php ---
if [ ! -f /var/www/html/wp-config.php ]; then
    echo "Creating wp-config.php..."
    wp config create --dbname="$WORDPRESS_DB_NAME" \
                     --dbuser="$WORDPRESS_DB_USER" \
                     --dbpass="$WORDPRESS_DB_PASSWORD" \
                     --dbhost="$WORDPRESS_DB_HOST" \
                     --skip-check \
                     --extra-php="\$_SERVER['HTTPS'] = 'on'; define('FORCE_SSL_ADMIN', true);"
fi

# --- 4. Install WordPress ---
# A reliable way to check for a true installation is to see if the users table exists.
if ! wp db tables 'wp_users' --skip-plugins --skip-themes --quiet; then
    wp core install --url="$VIRTUAL_HOST" \
                    --title="WordPress Environment" \
                    --admin_user="${ADMIN_USER:-admin}" \
                    --admin_password="${ADMIN_PASS:-password}" \
                    --admin_email="admin@example.com" \
                    --skip-email
fi

# --- 5. Install & Activate Plugins ---
if ! wp plugin is-installed woocommerce; then
    wp plugin install woocommerce --version="${WOOCOMMERCE_VERSION:-9.9.5}" --activate
fi

if ! wp plugin is-active sokin-woocommerce-plugin; then
    wp plugin activate sokin-woocommerce-plugin
fi

# --- 6. Configure Options ---
# With plugins active, we can now fetch metadata and set final options.
PLUGIN_VERSION=$(wp eval 'echo get_plugin_data(WP_PLUGIN_DIR . "/sokin-woocommerce-plugin/sokinpay.php")["Version"];' 2>/dev/null || echo "Unknown")

# Dynamically set final site title based on VIRTUAL_HOST.
if [[ "$VIRTUAL_HOST" == *pr-* ]]; then
    PR_SUBDOMAIN=$(echo "$VIRTUAL_HOST" | cut -d'.' -f1)
    PR_NUMBER=${PR_SUBDOMAIN#pr-}
    SITE_TITLE="PR-${PR_NUMBER} Development Environment"
    wp option update blogname "$SITE_TITLE"
elif [[ "$VIRTUAL_HOST" == *demo.* ]]; then
    SITE_TITLE="Sokin Woocommerce Plugin Demo v${PLUGIN_VERSION}"
    wp option update blogname "$SITE_TITLE"
fi

SETTINGS_JSON="{\"enabled\": \"yes\", \"title\": \"Pay by card\", \"description\": \"Powered by Sokin\", \"woo_cpay_redirect_url\": \"${SOKIN_REDIRECT_URL:-https://portal.sandbox.sokin.com/sokinpay/customerPay}\", \"woo_cpay_x_api_key\": \"${SOKIN_X_API_KEY:-dummy_api_key}\", \"woo_cpay_api_url\": \"${SOKIN_API_URL:-https://api.sandbox.sokin.net/api/services/v1}\"}"
wp option update woocommerce_sokinpay_gateway_settings "$SETTINGS_JSON" --format=json

WP_VERSION=$(wp core version 2>/dev/null || echo "Unknown")
WC_VERSION=$(wp plugin get woocommerce --field=version 2>/dev/null || echo "Unknown")
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2)
NOTICE_TEXT="This is a demo store for testing purposes | 🚀 Plugin v${PLUGIN_VERSION} | WC v${WC_VERSION} | WP v${WP_VERSION} | PHP v${PHP_VERSION} - "

wp option update woocommerce_coming_soon "no"
wp option update woocommerce_demo_store "yes"
wp option update woocommerce_demo_store_notice "$NOTICE_TEXT"

# --- 7. Import Data ---
SAMPLE_XML="/var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml"
if [ -f "$SAMPLE_XML" ]; then
    if ! wp plugin is-active wordpress-importer; then
        wp plugin install wordpress-importer --activate
    fi
    wp import "$SAMPLE_XML" --authors=create --quiet
fi

echo "Entrypoint script finished successfully."
echo "Starting Apache..."
exec apache2-foreground
