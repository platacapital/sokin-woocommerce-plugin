# Sokin Pay for WooCommerce

A WordPress plugin that integrates Sokin Pay payment gateway with WooCommerce.

## Overview

This plugin enables WooCommerce stores to accept payments through Sokin Pay, providing secure payment processing options including credit cards and pay by bank transfers.

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- PHP 7.4+
- Docker and Docker Compose (for local development)

## Development Setup

### Option 1: Manual Installation

1. Clone this repository into your existing WordPress development environment's plugins directory:
   ```bash
   cd /path/to/wp-content/plugins
   git clone https://github.com/your-repo/sokin-woocommerce-plugin.git
   ```

2. Install dependencies (if using Composer):
   ```bash
   cd sokin-woocommerce-plugin
   composer install
   ```

3. Activate the plugin through the WordPress admin panel.

### Option 2: Using Docker Compose (Recommended for Isolated Testing)

This repository includes a setup in the `local-dev/` directory for quickly spinning up a local WordPress + WooCommerce environment using Docker Compose. This method uses a local Nginx reverse proxy to provide HTTPS access.

**Prerequisites:**
- Docker and Docker Compose installed.
- A tool to generate local SSL certificates (`mkcert` recommended).

**Setup Steps:**

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/your-repo/sokin-woocommerce-plugin.git
    cd sokin-woocommerce-plugin
    ```
2.  **(Optional) Update Passwords:** Open `local-dev/docker-compose.yml` and change the default passwords for `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, and `ADMIN_PASS` to something more secure.

3.  **Generate Local SSL Certificate:** This setup requires an SSL certificate for `localhost` placed in `local-dev/certs/`. These certificates are **not committed** to the repository and must be generated locally.

    **Recommended Method: `mkcert` (Avoids Browser Warnings)**
    *   **Install `mkcert`:** Follow instructions for your OS at [https://github.com/FiloSottile/mkcert#installation](https://github.com/FiloSottile/mkcert#installation) (e.g., `brew install mkcert` on macOS).
    *   **Install Local CA (One-time setup):** Run `mkcert -install`. This requires administrator privileges to add the local Certificate Authority to your system/browser trust stores.
    *   **Generate Certificate:** From the project **root** directory, run:
        ```bash
        # Ensure the certs directory exists
        mkdir -p local-dev/certs
        # Generate cert files signed by your local CA
        mkcert -key-file local-dev/certs/localhost.key -cert-file local-dev/certs/localhost.crt localhost 127.0.0.1 ::1
        ```

    **Alternative Method: `openssl` (Requires Bypassing Browser Warnings)**
    *   If you prefer not to install `mkcert`'s local CA, you can generate a standard self-signed certificate. From the project **root** directory, run:
        ```bash
        # Ensure the certs directory exists
        mkdir -p local-dev/certs
        # Generate self-signed cert files
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
          -keyout local-dev/certs/localhost.key -out local-dev/certs/localhost.crt \
          -subj "/CN=localhost"
        ```
    *   *(When accessing the site using this method, you will need to manually bypass browser security warnings about the certificate not being trusted).*

4.  **Start the Environment:** From the project **root** directory, run:
    ```bash
    docker-compose up --build -d
    ```
    *   `--build`: Ensures the image is built with the latest changes.
    *   `-d`: Runs the containers in the background.

5.  **Access the Site:**
    *   Open your browser and navigate to `https://localhost:8443`.
    *   *(If using `openssl`, bypass the browser security warning).*
    *   WordPress Admin: `https://localhost:8443/wp-admin/`
    *   Admin Username: `testadmin` (or as set in `docker-compose.yml`)
    *   Admin Password: `testpass` (or as set in `docker-compose.yml`)

6.  **Stopping the Environment:** From the project **root** directory:
    ```bash
    docker-compose down
    ```

7.  **Removing Data (for a completely fresh start):** From the project **root** directory:
    ```bash
    docker-compose down -v
    ```

## Configuration

1. Go to WooCommerce > Settings > Payments
2. Enable and configure Sokin Pay
3. Enter your API credentials from your [Sokin Business Account](https://sokin.com/business/business-account-signup/)

## Testing

Before going live:
- Test the integration using Sokin's sandbox environment
- Verify payment flows with test cards
- Check webhook handling
- Ensure proper error handling

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

## Documentation

For detailed documentation:
- [Plugin Documentation](docs/)
- [Sokin API Documentation](https://api-docs.sokin.com)
- [Technical Support](mailto:support@sokin.com)


## License

This project is licensed under the [MIT License](LICENSE).

While not required by the license, we appreciate contributions back to the project. See our [CONTRIBUTING.md](CONTRIBUTING.md) guidelines for details on how to help improve this plugin.
