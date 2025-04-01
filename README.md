# Sokin Pay for WooCommerce

A WordPress plugin that integrates Sokin Pay payment gateway with WooCommerce.

## Overview

This plugin enables WooCommerce stores to accept payments through Sokin Pay, providing secure payment processing options including credit cards and pay by bank transfers.

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- PHP 7.4+

## Development Setup

1. Clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/your-repo/sokin-woocommerce-plugin.git
   ```

2. Install dependencies (if using Composer):
   ```bash
   composer install
   ```

3. Activate the plugin through WordPress admin panel

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
