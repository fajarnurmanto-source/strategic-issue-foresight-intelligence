from __future__ import annotations

from dataclasses import dataclass

from sifi.workflow import WorkflowStep


@dataclass(frozen=True, slots=True)
class StudyStepOption:
    code: str
    label: str


@dataclass(frozen=True, slots=True)
class StudyStepSpec:
    step: WorkflowStep
    prompt: str
    options: tuple[StudyStepOption, ...]
    accepted_code: str


STUDY_STEP_SPECS = {
    WorkflowStep.COVERAGE_INSPECTION: StudyStepSpec(
        WorkflowStep.COVERAGE_INSPECTION,
        "Batas cakupan mana yang harus dipertahankan?",
        (StudyStepOption("COVERAGE-SYNTHETIC", "Paket ini sintetis dan tidak mewakili monitoring dunia nyata."),
         StudyStepOption("COVERAGE-GLOBAL", "Paket ini membuktikan cakupan global.")),
        "COVERAGE-SYNTHETIC",
    ),
    WorkflowStep.ADMITTED_SIGNAL_REVIEW: StudyStepSpec(
        WorkflowStep.ADMITTED_SIGNAL_REVIEW,
        "Bukti mana yang masuk sebagai evidence supporting yang admitted?",
        (StudyStepOption("ADMIT-EV-001-002", "CB22-EV-001 dan CB22-EV-002"),
         StudyStepOption("ADMIT-ALL", "Semua lima item"),
         StudyStepOption("ADMIT-RUMOR", "CB22-EV-Z101 saja")),
        "ADMIT-EV-001-002",
    ),
    WorkflowStep.PERSISTENT_ISSUE_INSPECTION: StudyStepSpec(
        WorkflowStep.PERSISTENT_ISSUE_INSPECTION,
        "Identitas Issue tetap yang digunakan paket ini?",
        (StudyStepOption("ISSUE-CB22-001", "CB22-ISSUE-001"),
         StudyStepOption("ISSUE-AUTO-NEW", "Buat Issue baru otomatis")),
        "ISSUE-CB22-001",
    ),
    WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION: StudyStepSpec(
        WorkflowStep.EVIDENCE_CONTRADICTION_INSPECTION,
        "Bagaimana lima item bukti harus diperiksa?",
        (StudyStepOption("CLASSIFY-ALL-FIVE", "Pisahkan supporting, contradictory, context-only, dan excluded."),
         StudyStepOption("MERGE-POSITIVE", "Gabungkan semuanya sebagai supporting.")),
        "CLASSIFY-ALL-FIVE",
    ),
    WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION: StudyStepSpec(
        WorkflowStep.GEOGRAPHIC_RELEVANCE_INSPECTION,
        "Perlakuan dimensi geografis yang benar?",
        (StudyStepOption("GEO-ALL-DIMENSIONS", "Periksa source, event, affected, policy, dan relevance secara terpisah."),
         StudyStepOption("GEO-ONE-LABEL", "Gunakan satu label lokasi gabungan.")),
        "GEO-ALL-DIMENSIONS",
    ),
    WorkflowStep.UNCERTAINTY_INSPECTION: StudyStepSpec(
        WorkflowStep.UNCERTAINTY_INSPECTION,
        "Apa yang dilakukan terhadap penjelasan musiman dan bukti kontradiktif?",
        (StudyStepOption("UNCERTAINTY-EXPLICIT", "Pertahankan sebagai ketidakpastian dan batas kesimpulan."),
         StudyStepOption("UNCERTAINTY-DROP", "Abaikan agar kesimpulan lebih tegas.")),
        "UNCERTAINTY-EXPLICIT",
    ),
    WorkflowStep.TRAJECTORY_INSPECTION: StudyStepSpec(
        WorkflowStep.TRAJECTORY_INSPECTION,
        "Bagaimana trajektori diperiksa?",
        (StudyStepOption("TRAJECTORY-ALL-DIMENSIONS", "Periksa semua dimensi tanpa menggabungkannya."),
         StudyStepOption("TRAJECTORY-SINGLE", "Gunakan satu skor arah.")),
        "TRAJECTORY-ALL-DIMENSIONS",
    ),
    WorkflowStep.AUTHORIZED_CORRECTION: StudyStepSpec(
        WorkflowStep.AUTHORIZED_CORRECTION,
        "Tidak ada koreksi berotoritas dalam task. Pilihan yang sesuai?",
        (StudyStepOption("NO-CORRECTION", "Jangan membuat koreksi."),
         StudyStepOption("AUTO-CORRECTION", "Buat koreksi otomatis.")),
        "NO-CORRECTION",
    ),
    WorkflowStep.CANDIDATE_PRIORITIZATION: StudyStepSpec(
        WorkflowStep.CANDIDATE_PRIORITIZATION,
        "Dasar prioritisasi yang diperbolehkan?",
        (StudyStepOption("PRIORITIZE-BOUNDED", "Materialitas terbatas dengan ketidakpastian eksplisit."),
         StudyStepOption("PRIORITIZE-ATTENTION", "Volume perhatian saja.")),
        "PRIORITIZE-BOUNDED",
    ),
    WorkflowStep.EVIDENCE_LINKED_BRIEF: StudyStepSpec(
        WorkflowStep.EVIDENCE_LINKED_BRIEF,
        "Evidence link untuk pernyataan supporting dalam brief?",
        (StudyStepOption("BRIEF-EV-001-002", "CB22-EV-001 dan CB22-EV-002"),
         StudyStepOption("BRIEF-Z101", "CB22-EV-Z101")),
        "BRIEF-EV-001-002",
    ),
    WorkflowStep.HISTORICAL_RETRIEVAL: StudyStepSpec(
        WorkflowStep.HISTORICAL_RETRIEVAL,
        "Cutoff historis mana yang sesuai task package?",
        (StudyStepOption("CUTOFF-2026-01-01", "2026-01-01T08:00:00Z"),
         StudyStepOption("CUTOFF-NOW", "Waktu sekarang tanpa cutoff tetap")),
        "CUTOFF-2026-01-01",
    ),
}


def public_step_spec(step: WorkflowStep) -> dict:
    spec = STUDY_STEP_SPECS[step]
    return {
        "step": step.value,
        "prompt": spec.prompt,
        "options": [{"code": item.code, "label": item.label} for item in spec.options],
    }


def require_accepted_answer(step: WorkflowStep, answer_code: str) -> str:
    answer = answer_code.strip().upper()
    if answer != STUDY_STEP_SPECS[step].accepted_code:
        raise ValueError("selected answer does not preserve the frozen workflow boundary")
    return answer
