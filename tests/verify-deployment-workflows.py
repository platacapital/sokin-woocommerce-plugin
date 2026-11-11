# /// script
# requires-python = ">=3.10"
# dependencies = ["PyYAML>=6.0"]
# ///
"""Run with uv run tests/verify-deployment-workflows.py. No remote services used."""

import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

import yaml


ROOT = Path(__file__).resolve().parent.parent


def workflow(name):
    return yaml.safe_load((ROOT / ".github/workflows" / name).read_text())


def step(document, job, name):
    return next(item for item in document["jobs"][job]["steps"] if item["name"] == name)


def github_script(script, cwd, state="open"):
    harness = """
const fs = require('fs');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
const outputs = {};
const core = {setOutput: (key, value) => { outputs[key] = String(value); }};
const github = {rest: {pulls: {get: async (args) => {
  if (args.pull_number !== 58 || args.owner !== 'platacapital') throw Error('Wrong PR');
  return {data: {state: input.state}};
}}}};
const context = {repo: {owner: 'platacapital', repo: 'sokin-woocommerce-plugin'}, issue: {number: 58}};
(async () => {
  const run = new (Object.getPrototypeOf(async function(){}).constructor)('require', 'core', 'github', 'context', input.script);
  const result = await run(require, core, github, context);
  console.log(JSON.stringify({outputs, result}));
})();
"""
    result = subprocess.run(
        ["node", "-e", harness], cwd=cwd, input=json.dumps({"script": script, "state": state}),
        text=True, capture_output=True, check=True,
    )
    return json.loads(result.stdout)


class DeploymentWorkflows(unittest.TestCase):
    def setUp(self):
        self.fixture = tempfile.TemporaryDirectory(prefix="sokin-workflows-")
        self.addCleanup(self.fixture.cleanup)
        self.root = Path(self.fixture.name)
        self.host = self.root / "deployments-wordpress"
        self.host.mkdir()
        self.commands = self.root / "commands"
        binaries = self.root / "bin"
        binaries.mkdir()
        compose = binaries / "docker-compose"
        compose.write_text('#!/bin/sh\nprintf "compose %s\\n" "$*" >> "$TEST_COMMANDS"\n')
        compose.chmod(0o755)
        self.env = {**os.environ, "PATH": f"{binaries}:{os.environ['PATH']}", "TEST_COMMANDS": str(self.commands)}
        self.release = workflow("deploy-release.yaml")

    def read_release(self, has_cron):
        tag = self.root / ("current-tag" if has_cron else "legacy-tag")
        tag.mkdir(exist_ok=True)
        compose = "services:\n  wordpress:\n    image: fixture\n"
        (tag / "docker-compose.deploy.yml").write_text(compose)
        if has_cron:
            (tag / "scripts").mkdir(exist_ok=True)
            (tag / "scripts/manage-wp-cron.sh").write_text(
                '#!/bin/sh\nprintf "cron %s\\n" "$1" >> "$TEST_COMMANDS"\n'
            )
        result = github_script(step(self.release, "deploy", "Read deployment files")["with"]["script"], tag)
        self.assertEqual(result["result"], compose)
        return result

    def run_host_script(self, script, values):
        import re

        rendered = re.sub(r"\$\{\{\s*(.*?)\s*\}\}", lambda match: values.get(match[1], "fixture"), script)
        rendered = rendered.replace("~/deployments-wordpress", str(self.host))
        subprocess.run(["bash", "-c", rendered], cwd=self.root, env=self.env, check=True)

    def deploy_release(self, result):
        values = {f"steps.compose_file.outputs.{key}": value for key, value in result["outputs"].items()}
        values["steps.compose_file.outputs.result"] = result["result"]
        self.run_host_script(step(self.release, "deploy", "Deploy via SSH")["with"]["script"], values)

    def test_old_tag_without_helper_still_deploys(self):
        self.deploy_release(self.read_release(False))
        self.assertEqual(self.commands.read_text().splitlines(), ["compose down", "compose pull", "compose up -d --force-recreate"])

    def test_rollback_removes_existing_schedule(self):
        self.deploy_release(self.read_release(True))
        self.deploy_release(self.read_release(False))
        self.assertEqual(self.commands.read_text().splitlines(), [
            "cron install", "compose down", "compose pull", "compose up -d --force-recreate",
            "cron remove", "compose down", "compose pull", "compose up -d --force-recreate",
        ])

    def test_deploy_and_teardown_share_non_cancelling_queue(self):
        deploy = workflow("deploy-pr.yaml")["jobs"]["deploy"]["concurrency"]
        teardown = workflow("teardown-pr.yaml")["jobs"]["teardown"]["concurrency"]
        self.assertEqual(deploy["group"], teardown["group"])
        self.assertFalse(deploy["cancel-in-progress"] or teardown["cancel-in-progress"])
        # GitHub's default single pending slot could cancel a queued teardown.
        self.assertEqual((deploy["queue"], teardown["queue"]), ("max", "max"))

    def test_closed_pr_skips_all_deployment_side_effects(self):
        deploy = workflow("deploy-pr.yaml")
        gate = step(deploy, "deploy", "Check PR state")["with"]["script"]
        for state in ("open", "closed"):
            outputs = github_script(gate, self.root, state)["outputs"]
            for name in ("Read deployment files", "Deploy via SSH", "Post PR Comment with Deployment URL"):
                condition = step(deploy, "deploy", name)["if"]
                result = subprocess.run([
                    "node", "-e",
                    'const steps = JSON.parse(process.argv[1]); console.log(Function("steps", "return (" + process.argv[2] + ")")(steps));',
                    json.dumps({"pr_state": {"outputs": outputs}}), condition,
                ], text=True, capture_output=True, check=True)
                self.assertEqual(result.stdout.strip(), "true" if state == "open" else "false")

    def test_teardown_handles_no_deployment_and_removes_existing_one(self):
        script = step(workflow("teardown-pr.yaml"), "teardown", "Teardown via SSH")["with"]["script"]
        self.run_host_script(script, {"github.event.number": "58"})
        self.assertFalse(self.commands.exists())
        deployment = self.host / "pr-58"
        deployment.mkdir()
        (deployment / "manage-wp-cron.sh").write_text('#!/bin/sh\nprintf "cron %s\\n" "$1" >> "$TEST_COMMANDS"\n')
        self.run_host_script(script, {"github.event.number": "58"})
        self.assertEqual(self.commands.read_text().splitlines(), ["cron remove", "compose -p pr-58 down"])
        self.assertFalse(deployment.exists())


if __name__ == "__main__":
    unittest.main(verbosity=2)
