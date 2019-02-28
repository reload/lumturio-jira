workflow "Build and test" {
  on = "push"
  resolves = ["Test"]
}

action "Build" {
  uses = "actions/docker/cli@master"
  args = "build -t reload/lumturio-jira:test ."
}

action "Test" {
  needs = ["Build"]
  uses = "actions/docker/cli@master"
  args = "run --rm reload/lumturio-jira:test php /opt/lumturio-jira/lumturio-jira.phar --no-interaction --version"
}
