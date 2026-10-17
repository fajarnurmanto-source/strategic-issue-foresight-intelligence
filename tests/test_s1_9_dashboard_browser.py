"""Real Chrome JS/DOM checks without external packages or application DB."""

import html
import subprocess
import tempfile
import unittest
from pathlib import Path, PureWindowsPath

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "src/sifi/web/static/app.js"
CHROME = Path("/mnt/c/Program Files/Google/Chrome/Application/chrome.exe")
SCRATCH = Path("/mnt/d/Research/SIFI_WORKSPACE")

START = "function showRecord(kind, data) {"
END = "async function loadStatus() {"

HARNESS = r"""
const output = document.querySelector('#test-output');
const result = document.querySelector('#result');
const exerciseMessage = document.querySelector('#exerciseMessage');

function assert(condition, description) {
  if (!condition) throw new Error(description);
}
function response(assessment, ok = true) {
  return {
    ok,
    async json() {
      return ok ? {
        assessment,
        analytical_only: true,
        human_approval_granted: false,
        production_action_authorized: false
      } : {error: 'API tidak tersedia'};
    }
  };
}
function assessment(status, checked=[], missing=[], restricted=[]) {
  return {
    status,
    checked_refs: checked,
    missing_refs: missing,
    restricted_refs: restricted,
    human_approval_granted: false,
    production_action_authorized: false
  };
}
async function settle() {
  for (let i = 0; i < 12; i++) await Promise.resolve();
}
async function run() {
  const ready = assessment('READY_FOR_HUMAN_REVIEW', ['EV-1']);
  window.fetch = async () => response(ready);
  showRecord('brief', {brief_id:'BR-READY', issue_id:'IS-1'});
  await settle();
  assert(result.textContent.includes('Menunggu tinjauan manusia'),
    'valid ready status not rendered');
  assert(result.textContent.includes('EV-1'), 'checked ref missing');
  assert(result.textContent.includes('Tidak membuktikan kebenaran'),
    'authority disclaimer missing');

  const blocked = assessment('NEEDS_REMEDIATION',
    ['EV-1','EV-2'], ['EV-3'], ['EV-2']);
  window.fetch = async () => response(blocked);
  showRecord('brief', {brief_id:'BR-BLOCK', issue_id:'IS-1'});
  await settle();
  assert(result.textContent.includes('Perlu remediasi'),
    'blocked status not rendered');
  assert(result.textContent.includes('EV-3'), 'missing ref absent');
  assert(result.textContent.includes('EV-2'), 'restricted ref absent');

  window.fetch = async () => response(null, false);
  showRecord('brief', {brief_id:'BR-FAIL', issue_id:'IS-1'});
  await settle();
  assert(result.textContent.includes('Penilaian belum tersedia'),
    'API failure not shown');
  assert(result.textContent.includes('Jangan menganggap brief siap'),
    'fail-closed warning absent');

  let releaseOld;
  window.fetch = (url) => {
    if (url.includes('BR-OLD')) {
      return new Promise(resolve => { releaseOld = resolve; });
    }
    return Promise.resolve(response(blocked));
  };
  showRecord('brief', {brief_id:'BR-OLD', issue_id:'IS-1'});
  showRecord('brief', {brief_id:'BR-NEW', issue_id:'IS-1'});
  await settle();
  releaseOld(response(ready));
  await settle();
  assert(result.textContent.includes('Perlu remediasi'),
    'new result not preserved');
  assert(!result.textContent.includes('Menunggu tinjauan manusia'),
    'stale ready response incorrectly displayed');

  window.fetch = async () => response({
    ...ready, production_action_authorized:true
  });
  showRecord('brief', {brief_id:'BR-INVALID', issue_id:'IS-1'});
  await settle();
  assert(result.textContent.includes('Kontrak readiness tidak valid'),
    'invalid authority contract accepted');

  output.textContent = 'M3_CAP08_BROWSER_INTERACTION_PASS';
}
run().catch(error => {
  output.textContent = 'M3_CAP08_BROWSER_INTERACTION_FAIL: ' + error.message;
});
"""


class BrowserReadinessTests(unittest.TestCase):
    def test_actual_javascript_and_dom_interaction(self):
        if not CHROME.is_file():
            self.fail("Chrome Windows not found")

        source = APP.read_text(encoding="utf-8")
        self.assertEqual(source.count(START), 1)
        self.assertEqual(source.count(END), 1)

        beginning = source.index(START)
        ending = source.index(END)
        functions = source[beginning:ending]

        SCRATCH.mkdir(parents=True, exist_ok=True)

        with tempfile.TemporaryDirectory(
            prefix="CAP08_INTERACTION_", dir=SCRATCH
        ) as tmp:
            folder = Path(tmp)
            page = folder / "test.html"
            profile = folder / "chrome-profile"

            page.write_text(
                "<!doctype html><html><body>"
                '<div id="result"></div>'
                '<div id="exerciseMessage"></div>'
                '<div id="test-output">PENDING</div>'
                "<script>\n"
                + functions + "\n" + HARNESS + "\n"
                "</script></body></html>",
                encoding="utf-8",
            )

            win_page = subprocess.check_output(
                ["wslpath", "-w", str(page)], text=True
            ).strip()
            win_profile = subprocess.check_output(
                ["wslpath", "-w", str(profile)], text=True
            ).strip()

            url = PureWindowsPath(win_page).as_uri()
            command = [
                str(CHROME),
                "--headless",
                "--disable-gpu",
                "--no-first-run",
                "--no-default-browser-check",
                "--disable-extensions",
                "--disable-background-networking",
                f"--user-data-dir={win_profile}",
                "--virtual-time-budget=2500",
                "--dump-dom",
                url,
            ]
            completed = subprocess.run(
                command, capture_output=True, text=True,
                errors="replace", timeout=40,
            )

            self.assertEqual(
                completed.returncode, 0,
                completed.stderr[-1200:],
            )
            self.assertIn(
                "M3_CAP08_BROWSER_INTERACTION_PASS",
                html.unescape(completed.stdout),
                completed.stdout[-1500:],
            )


if __name__ == "__main__":
    unittest.main()
