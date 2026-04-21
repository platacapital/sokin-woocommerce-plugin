# CONTRIBUTING.md

## Contributing to this project

Thank you for your interest in contributing to our payment gateway! We value the input and expertise of our community and look forward to collaborations that improve these integrations for everyone.

### Ways to Contribute

There are several ways you can contribute to this project:

1. **Reporting Bugs**: Create an issue describing the bug, including steps to reproduce and expected behavior
2. **Suggesting Enhancements**: Submit feature requests that would make the plugin more valuable
3. **Code Contributions**: Submit pull requests with bug fixes or new features
4. **Documentation**: Help improve or translate our documentation
5. **Testing**: Test the plugin in different environments and report compatibility issues

### Development Process

1. **Fork the Repository**: Create your own fork of the code
2. **Create a Branch**: Create a branch with a descriptive name (`fix-checkout-issue`, `add-new-payment-method`)
3. **Make Changes**: Implement your changes, following our coding standards
4. **Write Tests**: If applicable, add or update tests for your changes
5. **Documentation**: Update documentation to reflect your changes
6. **Submit Pull Request**: Submit a PR with a clear description of the changes and their benefits

### Coding Standards

- Follow the existing code style and patterns in the project
- For WooCommerce plugin: adhere to WordPress coding standards
- Write clear, descriptive commit messages
- Keep changes focused and avoid mixing multiple unrelated changes in one PR

### Pull Request Process

1. Ensure your code adheres to the project's style and standards
2. Update the README.md or documentation with details of changes if appropriate
3. The PR should work properly on the latest versions of WooCommerce
4. Once reviewed, your PR will be merged by a maintainer

### Community Guidelines

- Be respectful and inclusive in your communications
- Provide constructive feedback
- Acknowledge the ideas and contributions of others
- Focus on what is best for the community and users of the plugin

### Setting Up Your Development Environment

1. **Prerequisites**:
   - PHP 7.4 or higher (match the versions noted in `README.md`)
   - WordPress and WooCommerce compatible with the plugin
   - Docker and Docker Compose if you use the bundled local environment

2. **Installation**:
   ```bash
   git clone https://github.com/platacapital/sokin-woocommerce-plugin.git
   cd sokin-woocommerce-plugin
   ```

   For an isolated stack, follow **Option 2: Using Docker Compose** in `README.md`. For an existing WordPress tree, follow **Option 1: Manual Installation** there.

3. **Optional tooling**:
   - Node.js is only needed if you change semantic-release or other Node-based automation (`npm ci` from the repository root).

4. **Testing locally**:
   - Use sandbox credentials and the checklist in the **Testing** section of `README.md`.

### Questions?

If you have any questions about contributing, please reach out to us at [support@sokin.com].

---

Thank you for contributing to make our payment gateway better for everyone!

Sokin Team