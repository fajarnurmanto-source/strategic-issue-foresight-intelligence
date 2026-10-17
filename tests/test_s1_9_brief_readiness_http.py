import http.client
import json
import tempfile
import threading
import unittest
from datetime import datetime, timezone
from pathlib import Path

from sifi.domain import AdmissionZone
from sifi.evidence.model import EvidenceItem
from sifi.evidence.repository import EvidenceItemRepository
from sifi.local import LocalSIFIPaths
from sifi.web.server import (
    LocalSIFIRequestHandler,
    LocalSIFIWebServer,
)
from sifi.workflow.brief import EvidenceLinkedBrief
from sifi.workflow.runtime import LocalWorkflowRuntime


def at(day):
    return datetime(2026, 10, day, tzinfo=timezone.utc)


class BriefReadinessHTTPTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / "SIFI"
        (self.root / "src" / "sifi").mkdir(parents=True)
        self.paths = LocalSIFIPaths.from_root(self.root)
        self.paths.initialize()

        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.root
        ) as runtime:
            runtime.record_brief(
                EvidenceLinkedBrief(
                    brief_id="BR-HTTP-001",
                    issue_id="IS-HTTP-001",
                    summary="Synthetic HTTP test brief",
                    evidence_refs=("EV-1", "EV-2"),
                    created_at=at(16),
                    analytical_cutoff=at(15),
                ),
                recorded_at=at(16),
            )
            repository = EvidenceItemRepository(runtime.version_store)

            for identifier, zone in (
                ("EV-1", AdmissionZone.Z2_PRODUCTION_ADMITTED),
                (
                    "EV-2",
                    next(
                        zone for zone in AdmissionZone
                        if zone != AdmissionZone.Z2_PRODUCTION_ADMITTED
                    ),
                ),
            ):
                repository.append(
                    EvidenceItem(
                        evidence_id=identifier,
                        source_id="SYNTHETIC-SOURCE",
                        evidence_kind="SYNTHETIC",
                        recorded_at=at(12),
                        admission_zone=zone,
                        origin_key="SYNTHETIC-ORIGIN",
                    ),
                    version=1,
                    effective_at=at(12),
                    recorded_at=at(12),
                )

        self.server = LocalSIFIWebServer(
            ("127.0.0.1", 0),
            LocalSIFIRequestHandler,
            workspace_root=self.root,
        )
        self.thread = threading.Thread(
            target=self.server.serve_forever, daemon=True
        )
        self.thread.start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=5)
        self.temp.cleanup()

    def request(self, path, host="127.0.0.1"):
        conn = http.client.HTTPConnection(
            "127.0.0.1", self.server.server_port, timeout=5
        )
        try:
            conn.request("GET", path, headers={"Host": host})
            response = conn.getresponse()
            status = response.status
            headers = dict(response.getheaders())
            payload = json.loads(response.read())
            return status, headers, payload
        finally:
            conn.close()

    def test_restricted_evidence_returns_remediation(self):
        status, headers, payload = self.request(
            "/api/brief/readiness?id=BR-HTTP-001"
        )
        self.assertEqual(status, 200)
        self.assertEqual(
            payload["assessment"]["status"],
            "NEEDS_REMEDIATION",
        )
        self.assertEqual(
            payload["assessment"]["restricted_refs"], ["EV-2"]
        )
        self.assertEqual(
            payload["assessment"]["checked_refs"], ["EV-1", "EV-2"]
        )
        self.assertFalse(payload["human_approval_granted"])
        self.assertFalse(payload["production_action_authorized"])
        self.assertEqual(headers["Cache-Control"], "no-store")

    def test_unknown_brief_returns_404(self):
        status, _, payload = self.request(
            "/api/brief/readiness?id=UNKNOWN"
        )
        self.assertEqual(status, 404)
        self.assertEqual(payload["error"], "brief not found")

    def test_bad_query_returns_400(self):
        for path in (
            "/api/brief/readiness",
            "/api/brief/readiness?id=A&id=B",
            "/api/brief/readiness?id=",
        ):
            with self.subTest(path=path):
                status, _, _ = self.request(path)
                self.assertEqual(status, 400)

    def test_invalid_host_is_rejected(self):
        status, _, _ = self.request(
            "/api/brief/readiness?id=BR-HTTP-001",
            host="untrusted.example",
        )
        self.assertEqual(status, 403)

    def test_readiness_does_not_append_object_versions(self):
        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.root
        ) as runtime:
            before = {
                (object_type, identifier): runtime.version_store.history(
                    object_type, identifier
                )
                for object_type, identifier in (
                    ("EVIDENCE_LINKED_ANALYTICAL_BRIEF", "BR-HTTP-001"),
                    ("EVIDENCE_ITEM", "EV-1"),
                    ("EVIDENCE_ITEM", "EV-2"),
                )
            }

        status, _, _ = self.request(
            "/api/brief/readiness?id=BR-HTTP-001"
        )
        self.assertEqual(status, 200)

        with LocalWorkflowRuntime(
            self.paths.database, workspace_root=self.root
        ) as runtime:
            for (object_type, identifier), history in before.items():
                self.assertEqual(
                    runtime.version_store.history(
                        object_type, identifier
                    ),
                    history,
                )


if __name__ == "__main__":
    unittest.main()
