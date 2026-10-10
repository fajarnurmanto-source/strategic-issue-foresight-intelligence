"""Explicit Issue identity assembly.

This module deliberately does not infer issue identity from text,
headline similarity, publication volume, or Event similarity.
"""

from __future__ import annotations

from dataclasses import dataclass

from .model import IssueObject


@dataclass(frozen=True, slots=True)
class IssueIdentityProposal:
    issue_id: str
    rationale: str
    related_event_ids: tuple[str, ...] = ()
    identity_review_state: str | None = None

    def __post_init__(self) -> None:
        if not self.issue_id.strip():
            raise ValueError(
                "issue_id must be supplied explicitly"
            )

        if not self.rationale.strip():
            raise ValueError(
                "identity rationale must not be blank"
            )

        if len(self.related_event_ids) != len(
            set(self.related_event_ids)
        ):
            raise ValueError(
                "related_event_ids must be unique"
            )


class ExplicitIssueAssembler:
    """Bind an explicitly supplied identity to an Issue Object.

    Identity authority is not implemented here.
    """

    @staticmethod
    def verify_identity(
        proposal: IssueIdentityProposal,
        issue: IssueObject,
    ) -> IssueObject:

        if proposal.issue_id != issue.issue_id:
            raise ValueError(
                "proposal issue_id must equal Issue Object issue_id"
            )

        if (
            tuple(proposal.related_event_ids)
            != tuple(issue.evidence.related_event_ids)
        ):
            raise ValueError(
                "proposal Event relationship must match "
                "Issue Object Event relationship"
            )

        if (
            proposal.identity_review_state
            != issue.identity_review_state
        ):
            raise ValueError(
                "proposal identity review state must match "
                "Issue Object identity review state"
            )

        return issue
