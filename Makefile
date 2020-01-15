.PHONY: check phpstan phpcs markdownlint hadolint

check: phpstan phpcs markdownlint

phpstan:
	-vendor/bin/phpstan analyse .

phpcs:
	-vendor/bin/phpcs -s bin/ src/ tests/

markdownlint:
	-mdl *.md

hadolint:
	-hadolint Dockerfile
