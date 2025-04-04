FROM wordpress:latest

# Install necessary packages: curl, less and MySQL client (for WP-CLI)
RUN apt-get update && apt-get install -y \
    curl \
    less \
    default-mysql-client

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Copy your custom plugin code
COPY . /var/www/html/wp-content/plugins/sokin-woocommerce-plugin

# Copy the custom entrypoint script
COPY ./tests/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Use the custom entrypoint script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
