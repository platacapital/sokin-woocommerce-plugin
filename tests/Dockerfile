# Accept build arguments for version management
ARG WORDPRESS_VERSION=6.8.1
ARG PHP_VERSION=8.4
ARG WP_CLI_VERSION=2.12.0
ARG WOOCOMMERCE_VERSION=9.9.5

FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

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
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl=7.88.1-10+deb12u12 \
    less=590-2.1~deb12u2 \
    default-mysql-client=1.1.0 \
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

# Copy your custom plugin code
COPY . /var/www/html/wp-content/plugins/sokin-woocommerce-plugin

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

