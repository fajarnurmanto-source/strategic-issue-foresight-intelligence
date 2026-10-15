from __future__ import annotations

import shutil
import tempfile
import unittest
from pathlib import Path

from sifi.release_check import check_local_release


class LocalReleaseCheckTests(unittest.TestCase):
    def test_001_release_check_passes_with_required_local_package(self):
        source = Path(__file__).resolve().parents[1]
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "SIFI"
            for relative in (
                "scripts/setup-local.ps1", "scripts/demo-local.ps1",
                "scripts/start-local.ps1", "scripts/backup-local.ps1",
                "scripts/start-sifi.ps1", "scripts/stop-sifi.ps1",
                "scripts/restore-local.ps1", "src/sifi/web/static/index.html",
                "LOCAL_OPERATOR_GUIDE.md", "scripts/prepare-cb22.ps1",
                "scripts/status-cb22.ps1", "scripts/export-cb22.ps1",
                "scripts/adjudicate-cb22.ps1",
                "scripts/configure-cb22.ps1",
                "scripts/rehearse-cb22.ps1",
                "05_VALIDATION/CB22_PREP/CB22_7_FROZEN_PROTOCOL_v1_0.md",
                "05_VALIDATION/CB22_PREP/CB22_10_OBSERVATION_RECORD_SCHEMA_v1_0.json",
                "05_VALIDATION/CB22_PREP/CB22_TASK_PACKAGE_v1_0.json",
            ):
                target = root / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(source / relative, target)
            (root / "src" / "sifi").mkdir(parents=True, exist_ok=True)
            result = check_local_release(root)
            self.assertEqual(result.status, "PASS")
            self.assertEqual(result.database_integrity, "OK")
            self.assertTrue(result.loopback_health)
            self.assertTrue(result.write_methods_rejected)
            self.assertTrue(result.required_files_present)
            self.assertTrue(result.cb22_task_available)
            self.assertFalse(result.production_ready_claimed)


if __name__ == "__main__":
    unittest.main()
