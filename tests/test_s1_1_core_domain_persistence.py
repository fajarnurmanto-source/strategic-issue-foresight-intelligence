import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path

from sifi.domain import (
    AdmissionZone,
    ClaimType,
    ObservabilityState,
    OntologyType,
    PersistenceClass,
    ReviewDisposition,
    TrajectoryDimension,
    VersionedSnapshot,
)
from sifi.geography import GeographyContext
from sifi.persistence import (
    DuplicateVersionError,
    SQLiteVersionStore,
)


UTC = timezone.utc


def dt(day: int) -> datetime:
    return datetime(2026, 1, day, 12, 0, tzinfo=UTC)


def snap(
    version: int,
    effective_day: int,
    recorded_day: int,
    value: str,
) -> VersionedSnapshot:
    return VersionedSnapshot(
        object_type="TEST_OBJECT",
        object_id="OBJ-001",
        version=version,
        effective_at=dt(effective_day),
        recorded_at=dt(recorded_day),
        payload={"value": value},
    )


class CanonicalVocabularyTests(unittest.TestCase):

    def test_canonical_enum_values(self):
        self.assertEqual(
            [x.value for x in AdmissionZone],
            ["Z0", "Z1", "Z2", "Z3"],
        )

        self.assertEqual(
            [x.value for x in ClaimType],
            [f"CT-{i:02d}" for i in range(1, 12)],
        )

        self.assertEqual(
            [x.value for x in ObservabilityState],
            [f"OBS-{i:02d}" for i in range(1, 7)],
        )

        self.assertEqual(
            [x.value for x in OntologyType],
            [f"OT-{i:02d}" for i in range(1, 7)],
        )

        self.assertEqual(
            [x.value for x in PersistenceClass],
            [f"PC-{i:02d}" for i in range(1, 4)],
        )

        self.assertEqual(
            [x.value for x in TrajectoryDimension],
            [f"TR-{i:02d}" for i in range(1, 6)],
        )

        self.assertEqual(
            [x.value for x in ReviewDisposition],
            [f"RD-{i:02d}" for i in range(1, 10)],
        )


class GeographyTests(unittest.TestCase):

    def test_geography_dimensions_remain_independent(self):
        value = GeographyContext(
            source_geography="SOURCE-GEO",
            event_geography="EVENT-GEO",
            affected_geography="AFFECTED-GEO",
            policy_jurisdiction="POLICY-J",
            relevance_geography="RELEVANCE-GEO",
            rights_jurisdiction="RIGHTS-J",
            coverage_profile_id="PROFILE-01",
        )

        result = value.as_dict()

        self.assertEqual(
            len(set(result.values())),
            7,
        )

        self.assertEqual(
            result["policy_jurisdiction"],
            "POLICY-J",
        )

        self.assertEqual(
            result["rights_jurisdiction"],
            "RIGHTS-J",
        )

    def test_blank_geography_dimension_is_rejected(self):
        with self.assertRaises(ValueError):
            GeographyContext(
                relevance_geography="   "
            )


class VersionedSnapshotTests(unittest.TestCase):

    def test_snapshot_requires_timezone_aware_times(self):
        with self.assertRaises(ValueError):
            VersionedSnapshot(
                object_type="TEST",
                object_id="X",
                version=1,
                effective_at=datetime(2026, 1, 1),
                recorded_at=dt(1),
                payload={},
            )

    def test_version_must_be_positive(self):
        with self.assertRaises(ValueError):
            VersionedSnapshot(
                object_type="TEST",
                object_id="X",
                version=0,
                effective_at=dt(1),
                recorded_at=dt(1),
                payload={},
            )


class SQLiteVersionStoreTests(unittest.TestCase):

    def test_append_is_non_destructive(self):
        with SQLiteVersionStore() as store:
            store.append(snap(1, 1, 2, "first"))

            with self.assertRaises(DuplicateVersionError):
                store.append(
                    snap(1, 1, 3, "overwrite-attempt")
                )

            stored = store.get(
                "TEST_OBJECT",
                "OBJ-001",
                1,
            )

            self.assertEqual(
                stored.payload["value"],
                "first",
            )

    def test_history_preserves_all_versions(self):
        with SQLiteVersionStore() as store:
            store.append(snap(1, 1, 2, "v1"))
            store.append(snap(2, 3, 4, "v2"))

            history = store.history(
                "TEST_OBJECT",
                "OBJ-001",
            )

            self.assertEqual(
                [x.version for x in history],
                [1, 2],
            )

            self.assertEqual(
                [x.payload["value"] for x in history],
                ["v1", "v2"],
            )

    def test_latest_uses_highest_version(self):
        with SQLiteVersionStore() as store:
            store.append(snap(1, 1, 2, "v1"))
            store.append(snap(2, 3, 4, "v2"))

            latest = store.latest(
                "TEST_OBJECT",
                "OBJ-001",
            )

            self.assertEqual(latest.version, 2)
            self.assertEqual(
                latest.payload["value"],
                "v2",
            )

    def test_as_of_respects_effective_and_recorded_cutoff(self):
        with SQLiteVersionStore() as store:
            store.append(
                snap(1, 1, 2, "known-by-day-2")
            )

            # Historical-effective information recorded later
            # must not leak into an earlier point-in-time view.
            store.append(
                snap(2, 1, 10, "known-only-by-day-10")
            )

            result_day_5 = store.as_of(
                "TEST_OBJECT",
                "OBJ-001",
                dt(5),
            )

            result_day_11 = store.as_of(
                "TEST_OBJECT",
                "OBJ-001",
                dt(11),
            )

            self.assertEqual(
                result_day_5.version,
                1,
            )

            self.assertEqual(
                result_day_11.version,
                2,
            )

    def test_file_backed_store_survives_reopen(self):
        with tempfile.TemporaryDirectory() as td:
            db = Path(td) / "sifi-test.sqlite"

            with SQLiteVersionStore(db) as store:
                store.append(
                    snap(1, 1, 2, "persisted")
                )

            with SQLiteVersionStore(db) as store:
                recovered = store.latest(
                    "TEST_OBJECT",
                    "OBJ-001",
                )

                self.assertIsNotNone(recovered)
                self.assertEqual(
                    recovered.payload["value"],
                    "persisted",
                )


if __name__ == "__main__":
    unittest.main()
