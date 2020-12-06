.PHONY: check phpstan phpcs markdownlint hadolint

check: phpstan phpcs markdownlint hadolint

phpstan:
	-vendor/bin/phpstan analyse

phpcs:
	-vendor/bin/phpcs -s

markdownlint:
	-mdl *.md

hadolint:
	-docker run --rm -i hadolint/hadolint < Dockerfile
