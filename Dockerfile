FROM composer:2.2.3 AS build-env

COPY . /opt/lumturio-jira/

WORKDIR /opt/lumturio-jira

RUN composer install --no-interaction --no-progress

FROM php:8.1.0-alpine

COPY --from=build-env /opt/lumturio-jira /opt/lumturio-jira

RUN apk add --no-cache tini=0.19.0-r0

# hadolint ignore=DL4006,SC2016
RUN crontab -l | { cat; echo '*/10    *       *       *       *       eval $(printenv | grep -E "^(JIRA|LUMTURIO)_" | sed "s/^\(.*\)$/export \1/g"); /opt/lumturio-jira/lumturio-jira.phar --verbose'; } | crontab -

# Run the command just to make sure we can.
RUN /opt/lumturio-jira/bin/lumturio-jira --help

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["/usr/sbin/crond", "-f"]
