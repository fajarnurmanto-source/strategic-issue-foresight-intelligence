from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from sifi.local import LocalPathBoundaryError
from sifi.local_cli import main
from sifi.local_ops import LocalOperations, LocalOperationsError


class LocalOperationsTests(unittest.TestCase):
    def root(self, directory):
        root = Path(directory) / "SIFI"
        (root / "src" / "sifi").mkdir(parents=True)
        return root

    def test_001_initialize_status_backup_and_restore(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            operations = LocalOperations(root)
            status = operations.initialize()
            self.assertTrue(status.database_exists)
            self.assertEqual(status.integrity, "OK")
            backup = operations.backup(label="TEST_001")
            self.assertTrue(backup.is_file())
            safety = operations.restore(backup)
            self.assertTrue(safety.is_file())
            self.assertEqual(operations.status().integrity, "OK")

    def test_002_restore_outside_workspace_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            operations = LocalOperations(root)
            operations.initialize()
            outside = Path(directory) / "outside.sqlite"
            outside.touch()
            with self.assertRaises(LocalPathBoundaryError):
                operations.restore(outside)

    def test_003_duplicate_backup_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            operations = LocalOperations(self.root(directory))
            operations.initialize()
            operations.backup(label="DUPLICATE")
            with self.assertRaises(LocalOperationsError):
                operations.backup(label="DUPLICATE")

    def test_004_cli_initializes_local_workspace(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            self.assertEqual(main(["init", "--root", str(root)]), 0)
            self.assertTrue((root / "runtime" / "data" / "sifi.sqlite").is_file())


if __name__ == "__main__":
    unittest.main()
