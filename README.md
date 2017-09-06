# Lumturio Jira integration

You need to set the following environment variables to their right
values:

* `JIRA_HOST`
* `JIRA_USER`
* `JIRA_PASS`
* `LUMTURIO_TOKEN`

Optionally specify issue type to create in Jira (defaults to `Bug`):

* `JIRA_ISSUETYPE`

It will process sites in Lumturio tagged with `SLA` and with a
`JIRA:XX` tag where `XX` is the Jira project to create issues in for
the given site.

## YMMV

This is still an early version:

* No tests
* No advanced error handling
* Sparse documentation
