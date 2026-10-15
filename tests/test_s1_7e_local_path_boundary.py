from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from sifi.local import LocalPathBoundaryError, LocalSIFIPaths
from sifi.workflow import LocalWorkflowRuntime


ROOT = Path(__file__).resolve().parents[1]


class LocalPathBoundaryTests(unittest.TestCase):
    def test_001_canonical_runtime_paths_are_under_workspace(self):
        paths = LocalSIFIPaths.from_root(ROOT)
        for item in (
            paths.runtime, paths.data, paths.logs, paths.backups,
            paths.config, paths.database,
        ):
            self.assertTrue(item.is_relative_to(ROOT))

    def test_002_initialization_creates_only_local_runtime_directories(self):
        paths = LocalSIFIPaths.from_root(ROOT)
        paths.initialize()
        self.assertTrue(paths.data.is_dir())
        self.assertTrue(paths.logs.is_dir())
        self.assertTrue(paths.backups.is_dir())
        self.assertTrue(paths.config.is_dir())

    def test_003_runtime_rejects_database_outside_workspace(self):
        with tempfile.TemporaryDirectory() as directory:
            outside = Path(directory) / "outside.sqlite"
            with self.assertRaises(LocalPathBoundaryError):
                LocalWorkflowRuntime(outside, workspace_root=ROOT)
            self.assertFalse(outside.exists())

    def test_004_runtime_accepts_canonical_database(self):
        paths = LocalSIFIPaths.from_root(ROOT)
        paths.initialize()
        with LocalWorkflowRuntime(paths.database, workspace_root=ROOT) as runtime:
            self.assertEqual(runtime.database_path, paths.database)

    def test_005_non_sifi_root_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaises(LocalPathBoundaryError):
                LocalSIFIPaths.from_root(directory)


if __name__ == "__main__":
    unittest.main()
