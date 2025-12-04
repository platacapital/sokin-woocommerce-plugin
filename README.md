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

## Release Process

### Automated (recommended)

1. **Prepare the release branch (GitHub Action)**
   - Go to the **Actions** tab in GitHub and run the **“Prepare Release PR”** workflow against the `main` branch.
   - The workflow:
     - Analyzes commits since the last `vX.Y.Z` tag using conventional commits (via `semantic-release` and `.releaserc.json`).
     - Computes the next semantic version (major/minor/patch).
     - Generates release notes from those commits.
     - Runs `scripts/bump-wp-version.mjs` to update `sokinpay.php`, `readme.txt`, and all internal versioned constants/script/style handles used by WooCommerce.
     - Commits the changes and pushes a `release/vX.Y.Z` branch with message `chore(release): vX.Y.Z`.

2. **Open and merge the Release PR**
   - In GitHub, open a pull request from `release/vX.Y.Z` into `main`.
   - Review the diff (version fields, changelog entry, etc.) and get it approved.
   - Merge the PR into `main` using your normal process.

3. **Tag and GitHub Release (Release workflow)**
   - When the merge commit lands on `main`, the **“Release”** workflow runs automatically.
   - It:
     - Reads the `Version:` header from `sokinpay.php` and compares it to the previous commit.
     - If the version changed, creates and pushes a `vX.Y.Z` tag pointing at the merge commit.
     - Extracts the `= X.Y.Z =` section from `readme.txt` and uses it as the GitHub Release notes.
     - Creates a GitHub Release named `vX.Y.Z` with those notes.

4. **Deployments driven by the GitHub Release**
   - **Demo environment:** the **“Deploy Release Demo”** workflow (`deploy-release.yaml`) runs on GitHub `release` events and:
     - Builds and pushes a Docker image tagged with `vX.Y.Z`.
     - Updates the demo environment to use the new image.
   - **WordPress.org:** the **“Deploy to WordPress.org”** workflow (`deploy-wporg.yml`) runs on published GitHub Releases and:
     - Verifies that the `vX.Y.Z` tag matches the `Version:` in `sokinpay.php`.
     - Builds a clean `dist/` directory using `.distignore`.
     - Creates a `sokin-woocommerce-plugin-vX.Y.Z.zip` archive.
     - Deploys the new version to the WordPress.org plugin SVN using `WPORG_USERNAME`/`WPORG_PASSWORD` secrets.

5. **Verify**
   - Confirm the tag and GitHub Release exist, the WordPress.org listing shows the new version, and the plugin files reflect the bumped version.

### Manual fallback

If GitHub Actions are unavailable or partially failing, you can perform each step manually.

#### Step 1: Prepare the release in a local clone

1. Ensure your local `main` is up to date:
   ```bash
   git checkout main
   git pull origin main
   npm ci
   ```

2. Compute the next version and notes using the same rules as the automation:
   ```bash
   node scripts/compute-next-version.mjs > /tmp/next-release.json

   # Inspect the result
   cat /tmp/next-release.json
   # {
   #   "version": "X.Y.Z",
   #   "notes": "…generated release notes…"
   # }
   ```

3. Run the bump script using that version and notes:
   ```bash
   VERSION=$(jq -r '.version' /tmp/next-release.json)
   NOTES=$(jq -r '.notes' /tmp/next-release.json)
   NOTES_B64=$(printf '%s' "$NOTES" | base64 | tr -d '\n')

   node scripts/bump-wp-version.mjs "$VERSION" "$NOTES_B64"
   ```

4. Create and push the release branch:
   ```bash
   git checkout -b "release/v$VERSION"
   git commit -am "chore(release): v$VERSION"
   git push origin "release/v$VERSION"
   ```

5. Open a PR from `release/vX.Y.Z` to `main` in GitHub, review, and merge.

#### Step 2: Tag and create the GitHub Release manually

1. After the PR is merged, tag the merge commit on `main`:
   ```bash
   git checkout main
   git pull origin main

   VERSION=X.Y.Z  # match the version in sokinpay.php/readme.txt
   git tag "v$VERSION"
   git push origin "v$VERSION"
   ```

2. In GitHub, go to **Releases**:
   - Click **“Draft a new release”**.
   - Select the `vX.Y.Z` tag.
   - Use the `= X.Y.Z =` section from `readme.txt` as the release notes.
   - Publish the release.

#### Step 3: Demo and WordPress.org deployments

If the tag and GitHub Release exist but the automation fails:

- **Demo environment:**
  - Manually run the **“Deploy Release Demo”** workflow from the Actions tab.
  - Use `vX.Y.Z` as the `tag` input.

- **WordPress.org:**
  - Manually run the **“Deploy to WordPress.org”** workflow from the Actions tab for the `vX.Y.Z` Release.
  - Alternatively, to build a zip locally that mirrors `.distignore`:
    ```bash
    rsync -rc --delete --exclude-from='.distignore' ./ ./dist/
    cd dist
    zip -r "../sokin-woocommerce-plugin-vX.Y.Z.zip" . -x '*.DS_Store'
    ```
  - You can then deploy that zip to WordPress.org using your own SVN client if needed.

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