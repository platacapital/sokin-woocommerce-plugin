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
   git clone https://github.com/platacapital/sokin-woocommerce-plugin.git
   ```

2. Activate the plugin through the WordPress admin panel.

   This plugin ships without Composer or npm runtime dependencies. If you are changing release automation (semantic-release), install tooling from the repository root with `npm ci`.

### Option 2: Using Docker Compose (Recommended for Isolated Testing)

A root `docker-compose.yml` spins up WordPress, MariaDB, and Nginx. TLS uses `local-dev/nginx.conf` and certificate files you place in `local-dev/certs/` (those cert files are not committed).

**Prerequisites:**
- Docker and Docker Compose installed.
- A tool to generate local SSL certificates (`mkcert` recommended).

**Setup Steps:**

1.  **Clone the Repository:**
    ```bash
    git clone https://github.com/platacapital/sokin-woocommerce-plugin.git
    cd sokin-woocommerce-plugin
    ```
2.  **(Optional) Update Passwords:** Open `docker-compose.yml` in the repository root and change the default passwords for `MYSQL_ROOT_PASSWORD`, `WORDPRESS_DB_PASSWORD`, and `ADMIN_PASS` to something more secure. You can override values with a `.env` file in the same directory; Docker Compose loads it automatically.

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
    *   Open your browser and navigate to `https://localhost:8443` (Nginx terminates TLS on port 8443).
    *   WordPress is also exposed directly on `http://localhost:8080` (plain HTTP, no Nginx); prefer `https://localhost:8443` for a setup closer to production.
    *   *(If using `openssl`, bypass the browser security warning).*
    *   WordPress Admin: `https://localhost:8443/wp-admin/`
    *   Admin Username: `testadmin` (or as set in `docker-compose.yml`)
    *   Admin Password: `testpass` (or as set in `docker-compose.yml`)
    *   Compose bind-mounts the repository into the container at `wp-content/plugins/sokin-pay` (the WordPress.org plugin slug), so edits on the host are reflected immediately.
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

## Developer Hooks

Sokin Pay exposes checkout field extension points through plugin-prefixed hooks:

- `platasokin_credit_card_form_start`
- `platasokin_credit_card_form_end`

If your integration previously used `woocommerce_credit_card_form_start` or
`woocommerce_credit_card_form_end` for Sokin Pay checkout customizations, update it
to the `platasokin_*` hook names above.

## Testing

There is no PHPUnit suite checked into this repository yet; validation is manual.

Before going live:

- Run through checkout in a **sandbox** Sokin environment (see `SOKIN_API_URL` and related variables in `docker-compose.yml`; override with a root `.env` file if needed).
- Verify payment flows with Sokin's test cards and credentials from the [Sokin API documentation](https://api-docs.sokin.com).
- In **WooCommerce > Settings > Advanced > Webhooks** (or your gateway's documented callback URL), confirm webhooks reach your store and orders update as expected.
- Exercise failure paths (declines, timeouts, user cancel) and confirm the customer sees a clear message.

## Release Process

Details below are primarily for **maintainers** who cut releases; day-to-day contributions do not require this workflow.

### Automated (recommended)

1. **Write Conventional Commits**
   - Only `feat`, `fix`, `perf`, or commits marked as BREAKING CHANGES will trigger a release.
   - Other commit types (`chore`, `docs`, `ci`, `build`, etc.) are ignored by semantic-release.

2. **Merge to `main`**
   - The `Release` workflow is triggered on merges that touch plugin files (`sokin-pay.php`, `includes/**`, `assets/**`, `languages/**`, `readme.txt`, etc.), but the release job only runs for the `chore(release): vX.Y.Z` merge commit from the Release PR.
   - The workflow can also be run manually from the Actions tab (`workflow_dispatch`).

3. **Semantic-release automation**
   - Calculates the next version and uses `scripts/bump-wp-version.mjs` to update `sokin-pay.php`, `readme.txt`, the WordPress changelog order, and all internal versioned constants/script/style handles used by WooCommerce.
   - Commits the version bump, creates a `vX.Y.Z` git tag, and publishes a GitHub Release with generated notes and an attached zip built from `.distignore` (no `docker-entrypoint.sh`).
   - When triggered via the **Prepare Release PR** workflow, any `notes` you provide are fed into the bump script and included in the `readme.txt` changelog entry for that version.
   - The `Release` workflow only runs automatically when the `chore(release): vX.Y.Z` merge commit from the Release PR hits `main`, or when manually dispatched from Actions.

4. **WordPress.org deployment**
   - The `Deploy to WordPress.org` workflow fires when the GitHub Release is published.
   - It rebuilds a clean `dist/` payload, reuses the release zip, and pushes the new version to the WordPress.org plugin SVN using `WPORG_USERNAME`/`WPORG_PASSWORD` secrets.
   - Download the `sokin-woocommerce-plugin-vX.Y.Z.zip` asset from the release if you need to upload to any additional marketplaces.

5. **Verify**
   - Confirm the release and tag exist, the WordPress.org listing shows the new version, and the plugin files reflect the bumped version.

### Manual fallback

If the automation is unavailable, you can still cut a release manually while letting the bump script keep all WordPress metadata in sync:

1. From the project root, run the bump script with the new version and (optionally) base64-encoded notes:
   ```bash
   # Without explicit notes: derives notes from git commits since the last tag, or falls back to a generic maintenance entry
   node scripts/bump-wp-version.mjs X.Y.Z

   # With explicit notes (recommended for clearer changelogs)
   NOTES_B64=$(printf 'Short description of changes for X.Y.Z' | base64)
   RELEASE_NOTES_B64="$NOTES_B64" node scripts/bump-wp-version.mjs X.Y.Z
   ```
   This updates `sokin-pay.php`, `readme.txt`, the internal plugin/class/script/style versions, and prepends a `= X.Y.Z =` changelog entry built from the provided or derived notes.
2. Review the diff, then commit and push the changes to `main` (for example: `chore(release): vX.Y.Z`).
3. Create a GitHub Release targeting `main`, adding a new `vX.Y.Z` tag and release notes.
4. If you need a manual zip (e.g. for marketplaces), package the plugin, mirroring `.distignore` exclusions:
   ```bash
   zip -r sokin-woocommerce-plugin-vX.Y.Z.zip . \
     -x "**/.git*" "**/.DS_Store" "local-dev/*" "tests/*" "wp-content/*" \
        "docker-compose*" "*.log" "**/docker-entrypoint.sh" "scripts/*" \
        "package.json" "package-lock.json" ".releaserc.json" ".distignore"
   ```
5. Upload the archive wherever needed (for example, to a marketplace that does not consume the GitHub Release directly).

#### GitHub secrets

Before relying on the automation, add these repository secrets:

- `WPORG_USERNAME`
- `WPORG_PASSWORD`

The workflows assume the WordPress.org slug is `sokin-pay` (configured via the `PLUGIN_SLUG` environment variable), while the GitHub repository name remains `sokin-woocommerce-plugin`.

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

## Documentation

For detailed documentation:
- [Plugin Documentation](docs/)
- [Sokin API Documentation](https://api-docs.sokin.com)
- [Technical Support](mailto:support@sokin.com)


## License

This plugin is licensed under the terms of the [GNU General Public License v2 or later](LICENSE), consistent with the WordPress.org plugin directory requirements.

Sokin is a trading name of Plata Capital Ltd. For information about how Sokin is regulated and the terms under which Sokin services are provided, please refer to the legal and regulatory information on [sokin.com](https://sokin.com) and the region-specific terms and policies listed on the [Sokin Legal](https://sokin.com/legal) page.

While not required by the license, we appreciate contributions back to the project. See our [CONTRIBUTING.md](CONTRIBUTING.md) guidelines for details on how to help improve this plugin.