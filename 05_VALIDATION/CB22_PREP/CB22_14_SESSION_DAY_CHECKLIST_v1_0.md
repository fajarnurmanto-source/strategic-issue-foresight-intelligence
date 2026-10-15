# CB22 Session-Day Checklist v1.0

## Before the participant arrives

- Run `scripts\verify-local.ps1` and retain the PASS output outside participant data.
- Run `scripts\status-cb22.ps1`; counted gate must report ready.
- Confirm protocol `CB22-v1.0` and task package `CB22-TASK-v1.0`.
- Confirm the computer is local, private, and not screen-sharing or recording.
- Close unrelated applications and notifications.
- Confirm the participant has not previously completed a counted SIFI session.

## Before timing starts

- Complete the approved screening process.
- Provide study information and answer questions.
- Obtain affirmative consent and record its timezone-aware timestamp.
- Enter only pseudonymous codes into SIFI.
- Confirm the participant—not the facilitator—controls the workflow.

## During the task

- Read only the standardized task introduction.
- Record every intervention, including duration and routine-help classification.
- Record application failures, blockers, safety stops, and forbidden outputs.
- Do not coach analytical answers or silently correct participant choices.
- If the participant stops, use `Akhiri tanpa selesai` and a structured code.

## After the task

- Ask for participant-reported blockers and record structured blocker codes.
- Do not collect free-text identity or employer details.
- Run `scripts\status-cb22.ps1` and confirm the session state.
- Back up the local database using `scripts\backup-local.ps1`.
- After the final session, export one adjudication bundle and submit that exact
  file to both assigned reviewers.
