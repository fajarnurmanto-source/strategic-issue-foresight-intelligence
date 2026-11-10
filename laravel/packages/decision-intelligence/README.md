# Decision Intelligence — reusable Laravel module (prototype v0.1)

A self-contained Composer path package for SIFI or PI. **Not yet integrated into either live repository.**
No database migrations, automatic requests, scheduler, or routes; default AI driver `off`.

## Install in a disposable branch / staging copy of the desired Laravel application

1. Extract this folder to `<laravel-root>/packages/decision-intelligence`.
2. Edit the host app `composer.json` to add an entry in `repositories`:
   `{"type":"path","url":"packages/decision-intelligence","options":{"symlink":false}}`
3. Run `composer require local/decision-intelligence:@dev` in the **host Laravel root**.
4. Optional: `php artisan vendor:publish --tag=decision-intelligence-config`.
5. No live HTTP calls occur until your code explicitly calls `ExecutiveSummary::generate()` and driver is enabled.
6. To uninstall: `composer remove local/decision-intelligence` and remove the path repository entry.

## Validate filter input

```php
use DecisionIntelligence\IssueFilter;
$filter = IssueFilter::validate([
 'query'=>'Citra negatif Kota Yogyakarta',
 'period'=>['start'=>'2025-01-01','end_exclusive'=>'2026-01-01'],
 'region'=>'KOTA_YOGYAKARTA',
 'media'=>['news','video','audio','image','document'],
 'sentiment'=>'positive',
]);
```

Adapt `$filter` into existing Monitoring Object / Research Session contracts; it does **not** modify DB by itself.

## Advisory executive summary (explicit call)

```php
use DecisionIntelligence\ExecutiveSummary;
$result = app(ExecutiveSummary::class)->generate([
 ['id'=>'obs-1','source'=>'https://example.org','headline'=>'Contoh','status'=>'UNVERIFIED'],
], 'Apa langkah penanganan yang masuk akal?');
// status ADVISORY_UNVERIFIED means HUMAN REVIEW REQUIRED.
```

## Provider options (choose one, keep secrets out of Git)

- Off (default): `DECISION_AI_DRIVER=off`
- Local Ollama: `DECISION_AI_DRIVER=ollama`; set `DECISION_OLLAMA_MODEL` to locally installed model. URL defaults to localhost:11434. Local only.
- Gemini: `DECISION_AI_DRIVER=gemini`; `DECISION_GEMINI_API_KEY` + `DECISION_GEMINI_MODEL`.
- DeepSeek: `DECISION_AI_DRIVER=deepseek`; `DECISION_DEEPSEEK_API_KEY` + `DECISION_DEEPSEEK_MODEL`.

Remote providers receive the evidence content provided to `generate()`. Exclude confidential and personal information unless its use is authorized. Local models need sufficient RAM and may be slow. No automatic fallbacks or paid usage; verify provider rates and terms first.

## Work needed for production integration

- Match host Laravel/PHP versions and Composer lock before install.
- Map canonical filtering fields into host MonitoringObjectRequest / controller and Research Session (including existing revision/audit policies); do not bypass them.
- Add only UI controls that map to supported backend filters; indicate unsupported provider fields.
- Protect evidence against prompt injection; build a server-side evidence registry and verify model-cited IDs against it.
- Store AI advisory + model/version + input hashes with provenance, separate from verified evidence.
- Confirm the client host (PI vs SIFI) before editing its code; never copy a SIFI-specific controller into PI.
- Minimum acceptance: one filter save/reopen and one summary preview with review status. Do not repeat accepted test suites.
