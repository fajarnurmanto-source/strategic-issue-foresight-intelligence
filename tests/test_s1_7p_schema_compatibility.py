from __future__ import annotations

import sqlite3
from contextlib import closing
import tempfile
import unittest
from pathlib import Path

from sifi.persistence import CURRENT_SCHEMA_VERSION, SQLiteVersionStore, UnsupportedSchemaVersionError


class SchemaCompatibilityTests(unittest.TestCase):
    def test_001_new_and_legacy_databases_are_stamped_current(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "sifi.sqlite"
            with SQLiteVersionStore(database):
                pass
            with closing(sqlite3.connect(database)) as connection:
                self.assertEqual(
                    connection.execute("PRAGMA user_version").fetchone()[0],
                    CURRENT_SCHEMA_VERSION,
                )

    def test_002_future_database_fails_closed(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "future.sqlite"
            with closing(sqlite3.connect(database)) as connection:
                connection.execute("PRAGMA user_version = 999")
            with self.assertRaises(UnsupportedSchemaVersionError):
                SQLiteVersionStore(database)


if __name__ == "__main__":
    unittest.main()
