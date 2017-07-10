# Contributing To Claws

Community made patches, localizations, bug reports and contributions are always welcome and are crucial to ensure Claws' success in the WordPress ecosystem.

When contributing please ensure you follow the guidelines below so that we can keep on top of things.

## Getting Started

* __Do not report potential security vulnerabilities here. Contact our security privately via [pippinsplugins.com/plugin-support](https://pippinsplugins.com/plugin-support/).__
* Submit a ticket for your issue, assuming one does not already exist.
  * Raise it on our [Issue Tracker](https://github.com/sandhillsdevelopment/claws/issues)
  * Clearly describe the issue including steps to reproduce the bug.
  * Make sure you fill in the earliest version that you know has the issue as well as the version of WordPress you're using.

## Making Changes

* Fork the repository on GitHub
* Make the changes to your forked repository
* Ensure you stick to the [WordPress Coding Standards](https://codex.wordpress.org/WordPress_Coding_Standards)
* When committing, reference your issue (if present) and include a note about the fix
* If possible, and if applicable, please also add/update unit tests for your changes
* Push the changes to your fork and submit a pull request to the `master` branch of the claws repository

## Code Documentation

* We ensure that every Claws method is documented well and follows the [WordPress Inline Documentation Standards for PHP](https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/php/)
* Please make sure that every function is documented so that when we update our API Documentation things don't go awry!
* If you're adding/editing a function in a class, make sure to add `@access {private|public|protected}`
* Finally, please use tabs and not spaces. The tab indent size should be 4 for all Claws code.

At this point you're waiting on us to merge your pull request. We'll review all pull requests, and make suggestions and changes if necessary.

# Additional Resources
* [General GitHub Documentation](https://help.github.com/)
* [GitHub Pull Request documentation](https://help.github.com/send-pull-requests/)
* [PHPUnit Tests Guide](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
