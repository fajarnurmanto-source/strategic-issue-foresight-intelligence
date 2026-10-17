import shutil
import subprocess
import unittest
from pathlib import Path


STATIC = Path(__file__).resolve().parents[1] / "src/sifi/web/static"


class DashboardReadinessTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.html = (STATIC / "index.html").read_text(encoding="utf-8")
        cls.js = (STATIC / "app.js").read_text(encoding="utf-8")
        cls.css = (STATIC / "styles.css").read_text(encoding="utf-8")

    def test_readiness_only_for_brief_records(self):
        self.assertIn("if (kind === 'brief') {", self.js)
        self.assertIn("loadBriefReadiness(data.brief_id, readinessPanel)", self.js)
        self.assertIn("readinessPanel.className = 'readiness-panel'", self.js)

    def test_endpoint_and_error_handling(self):
        self.assertIn("/api/brief/readiness?id=", self.js)
        self.assertIn("encodeURIComponent(briefId)", self.js)
        self.assertIn("if (!response.ok)", self.js)
        self.assertIn("Penilaian belum tersedia", self.js)
        self.assertIn("Jangan menganggap brief siap", self.js)

    def test_restricted_and_missing_refs_are_visible(self):
        for field in (
            "checked_refs", "missing_refs", "restricted_refs"
        ):
            self.assertIn(f"assessment.{field}", self.js)
        self.assertIn("NEEDS_REMEDIATION", self.js)
        self.assertIn("READY_FOR_HUMAN_REVIEW", self.js)

    def test_authorization_boundaries_are_visible(self):
        self.assertIn("assessment.human_approval_granted !== false", self.js)
        self.assertIn("assessment.production_action_authorized !== false", self.js)
        self.assertIn("Tidak memberikan persetujuan", self.html)
        self.assertIn("readiness-note", self.css)

    def test_safe_dom_and_stale_response_guard(self):
        self.assertIn("value.textContent = refs.length", self.js)
        self.assertIn("if (!result.contains(panel)) return;", self.js)
        self.assertIn("panel.replaceChildren()", self.js)

    def test_javascript_syntax_when_node_available(self):
        node = shutil.which("node")
        if node is None:
            self.skipTest("Node.js unavailable; browser syntax check pending")
        subprocess.run(
            [node, "--check", str(STATIC / "app.js")],
            check=True, capture_output=True, text=True,
        )


if __name__ == "__main__":
    unittest.main()
