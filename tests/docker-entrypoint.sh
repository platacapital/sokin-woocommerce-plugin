#!/bin/bash
set -e

echo "Ensuring WordPress files are present..."

# If /var/www/html/index.php is missing, copy WordPress core files.
if [ ! -f /var/www/html/index.php ]; then
    echo "Copying WordPress core files..."
    cp -R /usr/src/wordpress/. /var/www/html/
fi

echo "Waiting for database connection..."
# Extract hostname from WORDPRESS_DB_HOST (e.g., db:3306 -> db)
DB_HOSTNAME=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f1)
echo "Pinging database host: $DB_HOSTNAME"

set -x # Enable verbose command printing
# Loop until mysqladmin ping is successful (exit code 0)
while true; do
    # Use --silent to avoid spamming logs on success, but capture output/errors
    output=$(mysqladmin ping -h"$DB_HOSTNAME" 2>&1)
    status=$?
    if [ $status -eq 0 ]; then
        echo "Database ping successful!"
        break # Exit the loop on success
    else
        echo "Database not responding yet (Status: $status, Output: $output)..."
        sleep 2
    fi
done
set +x # Disable verbose command printing
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
SETTINGS_JSON="{\"enabled\": \"yes\", \"title\": \"Pay by card\", \"description\": \"Powered by Sokin\", \"woo_cpay_redirect_url\": \"${SOKIN_REDIRECT_URL:-https://portal.sandbox.sokin.com/sokinpay/customerPay}\", \"woo_cpay_x_api_key\": \"${SOKIN_X_API_KEY:-dummy_api_key}\", \"woo_cpay_api_url\": \"${SOKIN_API_URL:-https://api.sandbox.sokin.net/api/services/v1}\"}"
wp option update woocommerce_sokinpay_gateway_settings "$SETTINGS_JSON" --format=json --allow-root

# Configure WooCommerce for live mode with plugin version notice
echo "Configuring WooCommerce for live operation..."

# Get the plugin version from the main plugin file
PLUGIN_VERSION=$(wp eval 'echo get_plugin_data(WP_PLUGIN_DIR . "/sokin-woocommerce-plugin/sokin-woocommerce-plugin.php")["Version"];' --allow-root 2>/dev/null || echo "Unknown")

# Disable demo store mode
wp option update woocommerce_demo_store "no" --allow-root

# Set custom store notice with plugin version
STORE_NOTICE="🚀 Sokin WooCommerce Plugin v${PLUGIN_VERSION} - Demo Environment Active"
wp option update woocommerce_store_notice "$STORE_NOTICE" --allow-root
wp option update woocommerce_demo_store_notice "$STORE_NOTICE" --allow-root

# Ensure store is not in coming soon mode
wp option update woocommerce_coming_soon "no" --allow-root

# Configure store for live operation
wp option update woocommerce_onboarding_opt_in "no" --allow-root
wp option update woocommerce_task_list_hidden "yes" --allow-root

echo "WooCommerce configured for live operation with plugin version notice."

# Install WordPress Importer plugin if not already active
echo "Checking for WordPress Importer plugin..."
if ! wp plugin is-active wordpress-importer --allow-root; then
  echo "WordPress Importer not active. Installing and activating..."
  wp plugin install wordpress-importer --activate --allow-root
  IMPORTER_INSTALL_STATUS=$?
  if [ $IMPORTER_INSTALL_STATUS -ne 0 ]; then
      echo "Failed to install/activate WordPress Importer. Aborting XML import."
      # Decide how to handle failure - exit? skip import?
      # For now, we'll let it continue and the import will likely fail again below
  else
      echo "WordPress Importer installed and activated."
  fi
else
  echo "WordPress Importer is already active."
fi

# Import WooCommerce Sample Data (XML)
echo "Attempting to import WooCommerce sample products via XML..."
SAMPLE_XML="/var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml"
if [ -f "$SAMPLE_XML" ]; then
    echo "Found sample XML: $SAMPLE_XML. Importing..."
    # Import using wp import, create authors if they don't exist
    wp import "$SAMPLE_XML" --authors=create --allow-root --quiet
    IMPORT_STATUS=$? # Capture exit status
    if [ $IMPORT_STATUS -eq 0 ]; then
        echo "Sample XML import successful."
    else
        echo "Sample XML import failed with status $IMPORT_STATUS."
    fi
else
    echo "WooCommerce sample product XML not found at $SAMPLE_XML. Skipping import."
    # Optional: Add fallback to CSV or product creation here if desired
fi

# Ensure correct permissions for web server
# echo "Setting file ownership for web server..."
# chown -R www-data:www-data /var/www/html/wp-content # REMOVED - Relying on named volume for uploads

echo "Starting Apache..."
exec apache2-foreground
