# Accept build arguments for version management
ARG WORDPRESS_VERSION=7.1.4
ARG PHP_VERSION=8.5
ARG WP_CLI_VERSION=2.12.0
ARG WOOCOMMERCE_VERSION=11.2.1

FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

# PHP2Shell fixes: https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w
# WordPress tags select a PHP minor, so check the actual bundled patch version.
RUN php -r ' \
    $minimums = ["8.2" => "8.2.35", "8.3" => "8.3.36", "8.4" => "8.4.27", "8.5" => "8.5.12"]; \
    $branch = PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION; \
    if (!isset($minimums[$branch]) || version_compare(PHP_VERSION, $minimums[$branch], "<")) { \
        fwrite(STDERR, "Unsupported or unpatched PHP runtime: " . PHP_VERSION . PHP_EOL); \
        exit(1); \
    } \
    echo "Patched PHP runtime: " . PHP_VERSION . PHP_EOL; \
'

# Re-declare ARGs after FROM to use them in this build stage
ARG WORDPRESS_VERSION
ARG PHP_VERSION  
ARG WP_CLI_VERSION
ARG WOOCOMMERCE_VERSION

# Set environment variables to pass to entrypoint script
ENV WORDPRESS_VERSION=${WORDPRESS_VERSION}
ENV PHP_VERSION=${PHP_VERSION}
ENV WP_CLI_VERSION=${WP_CLI_VERSION}
ENV WOOCOMMERCE_VERSION=${WOOCOMMERCE_VERSION}

# Create non-root user for security
RUN groupadd -r sokinuser && useradd -r -g sokinuser sokinuser

# Install necessary packages: curl, less and MySQL client (for WP-CLI)
# Note: Do not pin versions here so the image remains compatible across Debian releases.
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    less \
    default-mysql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install WP-CLI securely
RUN set -ex; \
    curl -o /usr/local/bin/wp -fL "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" && \
    # Verify file is not empty and is executable
    [ -s /usr/local/bin/wp ] && \
    chmod +x /usr/local/bin/wp && \
    # Verify it runs
    wp --info --allow-root

# Copy your custom plugin code (directory name must match wp.org slug: sokin-checkout)
COPY . /var/www/html/wp-content/plugins/sokin-checkout

# Copy the custom entrypoint script
COPY ./tests/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Disable XML-RPC at Apache level (must be done as root)
RUN echo '<Files "xmlrpc.php">' > /etc/apache2/conf-available/deny-xmlrpc.conf && \
    echo '    Require all denied' >> /etc/apache2/conf-available/deny-xmlrpc.conf && \
    echo '</Files>' >> /etc/apache2/conf-available/deny-xmlrpc.conf && \
    ls -la /etc/apache2/conf-available/deny-xmlrpc.conf && \
    a2enconf deny-xmlrpc && \
    ls -la /etc/apache2/conf-enabled/deny-xmlrpc.conf

# Set ownership of WordPress files to non-root user, but keep running as root for entrypoint
RUN chown -R sokinuser:sokinuser /var/www/html

USER sokinuser

# Add health check for WordPress
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Use the custom entrypoint script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

