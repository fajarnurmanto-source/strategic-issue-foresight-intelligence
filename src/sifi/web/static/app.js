const badge = document.querySelector('#healthBadge');
const integrity = document.querySelector('#integrity');
const result = document.querySelector('#result');
const catalog = document.querySelector('#catalog');
const recordCount = document.querySelector('#recordCount');
const exerciseStart = document.querySelector('#exerciseStart');
const exercisePanel = document.querySelector('#exercisePanel');
const exerciseProgress = document.querySelector('#exerciseProgress');
const stepCode = document.querySelector('#stepCode');
const stepTitle = document.querySelector('#stepTitle');
const stepGuidance = document.querySelector('#stepGuidance');
const stepNote = document.querySelector('#stepNote');
const advanceStep = document.querySelector('#advanceStep');
const exerciseMessage = document.querySelector('#exerciseMessage');
let localToken = '';
let activeExercise = null;
let activeStudy = null;
let studyTask = null;

const steps = {
  'WF-01':['Inspeksi cakupan','Tinjau profil cakupan dan keterbatasan skenario.'],
  'WF-02':['Tinjau sinyal yang diterima','Pastikan hanya bukti yang memenuhi keputusan hak digunakan.'],
  'WF-03':['Inspeksi Issue persisten','Tinjau identitas dan versi Issue tanpa membuat identitas otomatis.'],
  'WF-04':['Bukti dan kontradiksi','Periksa dukungan, kontradiksi, konteks, dan pengecualian secara terpisah.'],
  'WF-05':['Relevansi geografis','Periksa seluruh dimensi geografi tanpa menggabungkannya.'],
  'WF-06':['Ketidakpastian','Catat ketidakpastian, gap bukti, dan keterbatasan.'],
  'WF-07':['Trajektori','Periksa setiap dimensi trajektori secara terpisah.'],
  'WF-08':['Koreksi berotoritas','Pastikan koreksi memerlukan kewenangan manusia eksplisit.'],
  'WF-09':['Prioritisasi kandidat','Gunakan materialitas terbatas, bukan perhatian semata.'],
  'WF-10':['Brief berbasis bukti','Pastikan setiap pernyataan terhubung ke bukti yang diterima.'],
  'WF-11':['Penelusuran historis','Periksa keadaan pada cutoff waktu eksplisit.'],
};
const stepOrder = Object.keys(steps);

const text = (value) => value === null || value === undefined ? '—' :
  typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);

function showRecord(kind, data) {
  result.replaceChildren();
  const title = document.createElement('h3');
  title.className = 'record-title';
  title.textContent = kind === 'session' ? 'Sesi analisis' : kind === 'study' ? 'Observasi CB22' : 'Brief berbasis bukti';
  const grid = document.createElement('div');
  grid.className = 'record-grid';
  Object.entries(data).forEach(([key, value]) => {
    const box = document.createElement('div');
    box.className = 'field';
    const label = document.createElement('span');
    label.textContent = key.replaceAll('_', ' ');
    const content = document.createElement('code');
    content.textContent = text(value);
    box.append(label, content);
    grid.append(box);
  });
  result.append(title, grid);
  if (kind === 'brief') {
    const exportButton = document.createElement('button');
    exportButton.className = 'advance';
    exportButton.type = 'button';
    exportButton.textContent = 'Ekspor Markdown ke folder SIFI';
    exportButton.addEventListener('click', async () => {
      try {
        const exported = await postLocal('/api/brief/export', {brief_id:data.brief_id});
        exerciseMessage.textContent = `Brief tersimpan di ${exported.export}`;
      } catch (error) {
        showError(error);
      }
    });
    const readinessPanel = document.createElement('section');
    readinessPanel.className = 'readiness-panel';
    readinessPanel.setAttribute('aria-label', 'Kesiapan bukti historis');
    readinessPanel.setAttribute('aria-live', 'polite');
    result.append(readinessPanel);
    void loadBriefReadiness(data.brief_id, readinessPanel);
    result.append(exportButton);
  }
}

async function loadBriefReadiness(briefId, panel) {
  panel.replaceChildren();

  const heading = document.createElement('h4');
  heading.textContent = 'Kesiapan bukti historis';
  const message = document.createElement('p');
  message.textContent = 'Memeriksa rekam bukti pada analytical cutoff…';
  panel.append(heading, message);

  try {
    const response = await fetch(
      `/api/brief/readiness?id=${encodeURIComponent(briefId)}`,
      {cache: 'no-store'}
    );
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error || 'Penilaian tidak tersedia');
    }

    const assessment = payload.assessment;
    if (!assessment ||
        !['READY_FOR_HUMAN_REVIEW', 'NEEDS_REMEDIATION'].includes(assessment.status) ||
        !Array.isArray(assessment.checked_refs) ||
        !Array.isArray(assessment.missing_refs) ||
        !Array.isArray(assessment.restricted_refs) ||
        assessment.human_approval_granted !== false ||
        assessment.production_action_authorized !== false ||
        payload.analytical_only !== true ||
        payload.human_approval_granted !== false ||
        payload.production_action_authorized !== false) {
      throw new Error('Kontrak readiness tidak valid');
    }

    if (!result.contains(panel)) return;
    message.textContent = assessment.status === 'READY_FOR_HUMAN_REVIEW'
      ? 'Referensi historis memenuhi preflight terbatas. Menunggu tinjauan manusia.'
      : 'Perlu remediasi: referensi historis belum memenuhi preflight.';

    const details = [
      ['Referensi diperiksa', assessment.checked_refs],
      ['Tidak tersedia pada cutoff', assessment.missing_refs],
      ['Admission dibatasi', assessment.restricted_refs],
    ];

    for (const [label, refs] of details) {
      const row = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = `${label}: `;
      const value = document.createElement('span');
      value.textContent = refs.length ? refs.join(', ') : 'Tidak ada';
      row.append(strong, value);
      panel.append(row);
    }

    const warning = document.createElement('p');
    warning.className = 'readiness-note';
    warning.textContent = 'Tidak membuktikan kebenaran empiris, kelengkapan provenance, '
      + 'persetujuan manusia, atau otorisasi produksi.';
    panel.append(warning);
  } catch (error) {
    if (!result.contains(panel)) return;
    message.textContent = `Penilaian belum tersedia: ${error.message}`;
    const warning = document.createElement('p');
    warning.className = 'readiness-note';
    warning.textContent = 'Status tidak dapat ditetapkan. Jangan menganggap brief siap.';
    panel.append(warning);
  }
}

async function loadStatus() {
  try {
    const response = await fetch('/api/status');
    const data = await response.json();
    integrity.textContent = data.integrity;
    badge.lastChild.textContent = data.integrity === 'OK' ? ' Sistem lokal siap' : ' Belum diinisialisasi';
    badge.classList.toggle('ok', data.integrity === 'OK');
  } catch {
    integrity.textContent = 'TIDAK TERHUBUNG';
    badge.lastChild.textContent = ' Server lokal tidak tersedia';
  }
}

async function loadStudyStatus() {
  const target = document.querySelector('#studyStatus');
  try {
    const response = await fetch('/api/cb22/status');
    const data = await response.json();
    const gate = data.gate.ready_for_counted_sessions ? 'COUNTED SIAP' : `COUNTED TERKUNCI · ${data.gate.reasons.join(', ')}`;
    const rate = data.assessment.completion_rate === null ? '—' : `${Math.round(data.assessment.completion_rate * 100)}% selesai`;
    target.textContent = `${gate} · asesmen otomatis ${data.assessment.result} · n=${data.assessment.analyzable_participants} · ${rate} · klaim menunggu receipt dua peran`;
    const active = document.querySelector('#studyActive');
    active.replaceChildren();
    data.active_sessions.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = `Lanjutkan ${item.participant_code} · ${item.mode} · ${item.completed_stage_ids.length}/11`;
      button.addEventListener('click', () => renderStudy({
        observation:item,
        session:{completed_steps:item.completed_stage_ids},
        next_step:stepOrder[item.completed_stage_ids.length] || null,
        brief:null,
      }));
      active.append(button);
    });
    const history = document.querySelector('#studyHistory');
    history.replaceChildren();
    data.observations.forEach((item) => {
      const button = document.createElement('button'); button.type = 'button';
      button.textContent = `${item.observation_id} · ${item.mode} · ${item.task_completed ? 'selesai' : item.ended ? 'berakhir' : 'aktif'}`;
      button.addEventListener('click', async () => {
        try {
          const response = await fetch(`/api/cb22/observation?id=${encodeURIComponent(item.observation_id)}`);
          const observation = await response.json();
          if (!response.ok) throw new Error(observation.error || 'Observasi tidak tersedia');
          showRecord('study', observation);
          result.scrollIntoView({behavior:'smooth', block:'start'});
        } catch (error) { showError(error); }
      });
      history.append(button);
    });
  } catch { target.textContent = 'Status studi tidak tersedia'; }
}

async function loadStudyTask() {
  const response = await fetch('/api/cb22/task');
  studyTask = await response.json();
  const board = document.querySelector('#studyEvidence');
  board.replaceChildren();
  studyTask.evidence.forEach((item) => {
    const card = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = `${item.id} · ${item.class}`;
    const body = document.createElement('span'); body.textContent = item.text;
    card.append(heading, body); board.append(card);
  });
}

async function openRecord(kind, id) {
  result.innerHTML = '<p class="empty">Membaca rekam lokal…</p>';
  const response = await fetch(`/api/${kind}?id=${encodeURIComponent(id)}`);
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Rekam tidak dapat dibuka');
  showRecord(kind, data);
  result.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function resumeExercise(item) {
  const response = await fetch(`/api/session?id=${encodeURIComponent(item.id)}`);
  const session = await response.json();
  if (!response.ok) throw new Error(session.error || 'Sesi tidak dapat dilanjutkan');
  document.querySelector('#analystId').value = session.analyst_id;
  renderExercise({session, next_step:item.next_step, brief:null, synthetic:true});
  document.querySelector('#exerciseTitle').scrollIntoView({behavior:'smooth', block:'start'});
  exerciseMessage.textContent = 'Sesi lokal dipulihkan · lanjutkan dari tahap tersimpan.';
}

async function loadCatalog() {
  try {
    const response = await fetch('/api/catalog');
    const data = await response.json();
    const records = [
      ...data.sessions.map((item) => ({ ...item, kind: 'session' })),
      ...data.briefs.map((item) => ({ ...item, kind: 'brief' })),
    ];
    catalog.replaceChildren();
    recordCount.textContent = `${records.length} rekam`;
    if (!records.length) {
      const empty = document.createElement('p');
      empty.className = 'empty';
      empty.textContent = 'Belum ada rekam. Jalankan scripts\\demo-local.ps1 untuk data demonstrasi sintetis.';
      catalog.append(empty);
      return;
    }
    records.forEach((item) => {
      const button = document.createElement('button');
      button.className = 'record-link';
      button.type = 'button';
      button.textContent = item.id;
      const meta = document.createElement('small');
      const resumable = item.kind === 'session' && !item.complete;
      meta.textContent = `${resumable ? 'Lanjutkan sesi' : item.kind === 'session' ? 'Sesi selesai' : 'Brief'} · ${item.issue_id}`;
      button.append(meta);
      button.addEventListener('click', () => (
        resumable ? resumeExercise(item) : openRecord(item.kind, item.id)
      ).catch(showError));
      catalog.append(button);
    });
  } catch {
    recordCount.textContent = 'Tidak tersedia';
  }
}

function showError(error) {
  result.replaceChildren();
  const message = document.createElement('p');
  message.className = 'error';
  message.textContent = error.message;
  result.append(message);
}

async function postLocal(path, body) {
  if (!localToken) {
    const tokenResponse = await fetch('/api/local-token');
    localToken = (await tokenResponse.json()).token;
  }
  const response = await fetch(path, {
    method:'POST',
    headers:{'Content-Type':'application/json','X-SIFI-Token':localToken},
    body:JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Latihan tidak dapat diproses');
  return data;
}

function renderExercise(data) {
  activeExercise = data;
  const completed = data.session.completed_steps.length;
  exerciseProgress.style.width = `${Math.round(completed / 11 * 100)}%`;
  if (!data.next_step) {
    exercisePanel.hidden = true;
    exerciseStart.hidden = false;
    exerciseMessage.textContent = `Latihan selesai. Brief ${data.brief.brief_id} tersimpan secara lokal.`;
    loadCatalog();
    return;
  }
  const [title, guidance] = steps[data.next_step];
  stepCode.textContent = `${data.next_step} · tahap ${completed + 1} dari 11`;
  stepTitle.textContent = title;
  stepGuidance.textContent = guidance;
  stepNote.value = '';
  exercisePanel.hidden = false;
  exerciseStart.hidden = true;
  exerciseMessage.textContent = 'Skenario sintetis · tidak ada data yang dikirim keluar.';
}

exerciseStart.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const analystId = document.querySelector('#analystId').value.trim();
    renderExercise(await postLocal('/api/demo/start', {analyst_id:analystId}));
  } catch (error) {
    exerciseMessage.textContent = error.message;
  }
});

advanceStep.addEventListener('click', async () => {
  advanceStep.disabled = true;
  try {
    renderExercise(await postLocal('/api/demo/advance', {
      session_id:activeExercise.session.session_id,
      analyst_id:activeExercise.session.analyst_id,
      note:stepNote.value,
    }));
  } catch (error) {
    exerciseMessage.textContent = error.message;
  } finally {
    advanceStep.disabled = false;
  }
});

function renderStudy(data) {
  activeStudy = data;
  const completed = data.session.completed_steps.length;
  document.querySelector('#studyProgress').style.width = `${Math.round(completed / 11 * 100)}%`;
  if (!data.next_step) {
    document.querySelector('#studyPanel').hidden = true;
    document.querySelector('#studyStart').hidden = false;
    document.querySelector('#studyMessage').textContent = `Sesi selesai · observasi ${data.observation.observation_id} tersimpan.`;
    loadStudyStatus(); loadCatalog(); return;
  }
  const [title, guidance] = steps[data.next_step];
  const spec = studyTask.steps[data.next_step];
  document.querySelector('#studyStepCode').textContent = `${data.next_step} · tahap ${completed + 1} dari 11`;
  document.querySelector('#studyStepTitle').textContent = title;
  document.querySelector('#studyStepGuidance').textContent = `${guidance} ${spec.prompt}`;
  const answer = document.querySelector('#studyAnswer');
  answer.replaceChildren();
  spec.options.forEach((item) => {
    const option = document.createElement('option');
    option.value = item.code; option.textContent = item.label; answer.append(option);
  });
  document.querySelector('#studyPanel').hidden = false;
  document.querySelector('#studyStart').hidden = true;
  document.querySelector('#studyMessage').textContent = data.observation.mode === 'COUNTED' ? 'Sesi dihitung · ikuti protokol beku.' : 'Rehearsal · tidak masuk hasil CB22.';
}

document.querySelector('#studyStart').addEventListener('submit', async (event) => {
  event.preventDefault();
  const local = document.querySelector('#consentAt').value;
  try {
    renderStudy(await postLocal('/api/cb22/start', {
      participant_code:document.querySelector('#participantCode').value.trim().toUpperCase(),
      screening_record_id:document.querySelector('#screeningCode').value.trim().toUpperCase(),
      consent_record_id:document.querySelector('#consentCode').value.trim().toUpperCase(),
      consent_confirmed_at:new Date(local).toISOString(),
      governance_authorization_id:document.querySelector('#governanceCode').value.trim().toUpperCase(),
      representative_profile_met:document.querySelector('#profileMet').checked,
      participant_is_sifi_developer:!document.querySelector('#notDeveloper').checked,
      mode:document.querySelector('#studyMode').value,
    }));
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
});

document.querySelector('#studyAdvance').addEventListener('click', async () => {
  const button = document.querySelector('#studyAdvance'); button.disabled = true;
  try {
    renderStudy(await postLocal('/api/cb22/advance', {
      observation_id:activeStudy.observation.observation_id,
      participant_code:activeStudy.observation.participant_code,
      answer_code:document.querySelector('#studyAnswer').value,
    }));
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
  finally { button.disabled = false; }
});

document.querySelector('#exportStudy').addEventListener('click', async () => {
  try {
    const data = await postLocal('/api/cb22/export', {});
    document.querySelector('#studyMessage').textContent = `Bundle tersimpan di ${data.export}`;
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
});

document.querySelector('#recordIntervention').addEventListener('click', async () => {
  try {
    const data = await postLocal('/api/cb22/intervention', {
      observation_id:activeStudy.observation.observation_id,
      category:document.querySelector('#interventionCategory').value,
      reason_code:document.querySelector('#interventionReason').value.trim().toUpperCase(),
      duration_seconds:Number(document.querySelector('#interventionDuration').value),
      routine_developer_help:document.querySelector('#routineHelp').checked,
    });
    activeStudy.observation = data.observation;
    document.querySelector('#studyMessage').textContent = 'Intervensi tercatat secara lokal.';
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
});

document.querySelector('#recordIncident').addEventListener('click', async () => {
  try {
    const data = await postLocal('/api/cb22/incident', {
      observation_id:activeStudy.observation.observation_id,
      kind:document.querySelector('#incidentKind').value,
      code:document.querySelector('#incidentCode').value.trim().toUpperCase() || 'OBSERVED',
    });
    activeStudy.observation = data.observation;
    document.querySelector('#studyMessage').textContent = 'Insiden tercatat secara lokal.';
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
});

document.querySelector('#stopStudy').addEventListener('click', async () => {
  try {
    const data = await postLocal('/api/cb22/stop', {
      observation_id:activeStudy.observation.observation_id,
      participant_code:activeStudy.observation.participant_code,
      reason_code:document.querySelector('#stopReason').value.trim().toUpperCase(),
    });
    document.querySelector('#studyPanel').hidden = true;
    document.querySelector('#studyStart').hidden = false;
    document.querySelector('#studyMessage').textContent = `Sesi diakhiri · ${data.observation.noncompletion_reason_code}`;
    loadStudyStatus();
  } catch (error) { document.querySelector('#studyMessage').textContent = error.message; }
});

document.querySelectorAll('form[data-kind]').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const kind = form.dataset.kind;
    const id = new FormData(form).get('id').trim();
    try {
      await openRecord(kind, id);
    } catch (error) {
      showError(error);
    }
  });
});

loadStatus();
loadStudyTask().then(loadStudyStatus);
loadCatalog();

// ==========================================
// FITUR FORESIGHT ANALYTICS
// ==========================================
const fetchForesightBtn = document.querySelector('#fetchForesight');
const issueIdInput = document.querySelector('#issueIdInput');
const foresightResult = document.querySelector('#foresightResult');

if (fetchForesightBtn && issueIdInput && foresightResult) {
  fetchForesightBtn.addEventListener('click', async () => {
    const issueId = issueIdInput.value.trim();
    if (!issueId) return;
    
    foresightResult.innerHTML = '<p>Menganalisis isu...</p>';
    
    try {
      const res = await fetch(`/api/foresight/${issueId}`);
      const data = await res.json();
      
      if (!res.ok) throw new Error(data.error || 'Gagal mengambil data');
      
      // Format hasil
      let html = `<h3>Risk Level: <span style="color:${data.risk.level === 'HIGH' ? 'red' : 'orange'}">${data.risk.level}</span> (${data.risk.direction})</h3>`;
      html += `<p><strong>Rationale:</strong> ${data.risk.rationale}</p>`;
      
      if (data.abstained) {
        html += `<p style="color:red;"><strong>ABSTAINED:</strong> ${data.abstention_reasons.join(', ')}</p>`;
      } else {
        html += `<h4>Rekomendasi Strategis:</h4><ul>` + data.recommendation_proposals.map(p => `<li>${p}</li>`).join('') + `</ul>`;
        html += `<h4>Watch Conditions:</h4><ul>` + data.watch_conditions.map(w => `<li><strong>${w.condition}</strong> — ${w.trigger}</li>`).join('') + `</ul>`;
      }
      
      foresightResult.innerHTML = html;
    } catch (err) {
      foresightResult.innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
    }
  });
}


// ==========================================
// FITUR CREATE ISSUE
// ==========================================
const createIssueBtn = document.querySelector('#createIssueBtn');
if (createIssueBtn) {
  createIssueBtn.addEventListener('click', async () => {
    const id = document.querySelector('#newIssueId').value.trim();
    const title = document.querySelector('#newIssueTitle').value.trim();
    const supp = document.querySelector('#newIssueSupp').value.split(',').map(s => s.trim()).filter(Boolean);
    const cont = document.querySelector('#newIssueCont').value.split(',').map(s => s.trim()).filter(Boolean);
    const msg = document.querySelector('#createIssueMsg');
    
    if (!id || !title) {
      msg.textContent = "ID dan Judul wajib diisi.";
      msg.style.color = "red";
      return;
    }

    msg.textContent = "Menyimpan...";
    msg.style.color = "black";

    try {
      // 1. Ambil token CSRF terbaru dari server
      const tokenRes = await fetch('/api/local-token');
      const tokenData = await tokenRes.json();
      const token = tokenData.token;

      // 2. Kirim data beserta token
      const res = await fetch('/api/issue/create', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-SIFI-Token': token
        },
        body: JSON.stringify({
          issue_id: id,
          title: title,
          supporting_evidence: supp,
          contradictory_evidence: cont
        })
      });
      
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal menyimpan');
      
      msg.textContent = "✅ Isu berhasil disimpan! Silakan cek di panel Foresight.";
      msg.style.color = "green";
      document.querySelector('#issueIdInput').value = id; 
    } catch (err) {
      msg.textContent = "Error: " + err.message;
      msg.style.color = "red";
    }
  });
}

// ==========================================
// FITUR SYSTEMIC ANALYSIS
// ==========================================
const fetchSystemicBtn = document.querySelector('#fetchSystemicBtn');
if (fetchSystemicBtn) {
  fetchSystemicBtn.addEventListener('click', async () => {
    const resultDiv = document.querySelector('#systemicResult');
    resultDiv.innerHTML = '<p>Memindai seluruh isu di database...</p>';
    
    try {
      const res = await fetch('/api/systemic-report');
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal mengambil laporan');
      
      let html = `<h3>Sistem memindai ${data.issues_analyzed} Isu</h3>`;
      html += `<p><strong>Isu Berisiko Tinggi:</strong> ${data.high_risk_count}</p>`;
      
      if (data.systemic_alerts.length === 0) {
        html += `<p style="color:green;">Tidak ada pola sistemik atau tumpang tindih bukti yang terdeteksi.</p>`;
      } else {
        html += `<h4>Peringatan Sistemik:</h4><ul>`;
        data.systemic_alerts.forEach(alert => {
          html += `<li><strong style="color:red;">${alert.alert_type}</strong>: ${alert.description}<br/><em>Isu Terkait: ${alert.involved_issue_ids.join(', ')}</em></li>`;
        });
        html += `</ul>`;
      }
      resultDiv.innerHTML = html;
    } catch (err) {
      resultDiv.innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
    }
  });
}

// ==========================================
// FITUR EKSPLORASI (Ingest & Generate Brief)
// ==========================================
const ingestFeedBtn = document.querySelector('#ingestFeedBtn');
if (ingestFeedBtn) {
  ingestFeedBtn.addEventListener('click', async () => {
    const msg = document.querySelector('#exploreMsg');
    msg.textContent = "Menyerap data...";
    try {
      const tokenRes = await fetch('/api/local-token');
      const token = (await tokenRes.json()).token;
      
      const res = await fetch('/api/ingest/feed', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-SIFI-Token': token},
        body: JSON.stringify({})
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal ingest');
      msg.textContent = `✅ Berhasil menyerap ${data.ingested} isu baru dari Feed!`;
      msg.style.color = "green";
    } catch (err) {
      msg.textContent = "Error: " + err.message;
      msg.style.color = "red";
    }
  });
}

const generateBriefBtn = document.querySelector('#generateBriefBtn');
if (generateBriefBtn) {
  generateBriefBtn.addEventListener('click', async () => {
    const id = document.querySelector('#briefIssueId').value.trim();
    const msg = document.querySelector('#exploreMsg');
    if (!id) return;
    
    msg.textContent = "Membuat Brief...";
    try {
      const tokenRes = await fetch('/api/local-token');
      const token = (await tokenRes.json()).token;
      
      const res = await fetch('/api/brief/generate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-SIFI-Token': token},
        body: JSON.stringify({issue_id: id})
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal generate');
      msg.textContent = `✅ Brief ${data.brief_id} berhasil dibuat dan disimpan!`;
      msg.style.color = "green";
    } catch (err) {
      msg.textContent = "Error: " + err.message;
      msg.style.color = "red";
    }
  });
}

// ==========================================
// FITUR VIEW BRIEF
// ==========================================
const viewBriefBtn = document.querySelector('#viewBriefBtn');
if (viewBriefBtn) {
  viewBriefBtn.addEventListener('click', async () => {
    const id = document.querySelector('#viewBriefId').value.trim();
    const contentArea = document.querySelector('#briefContent');
    if (!id) return;
    
    contentArea.textContent = "Memuat...";
    try {
      const res = await fetch(`/api/brief/view?id=${encodeURIComponent(id)}`);
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Gagal memuat brief');
      
      contentArea.textContent = data.content;
    } catch (err) {
      contentArea.textContent = "Error: " + err.message;
    }
  });
}

// ==========================================
// FITUR SEMANTIC TESTING
// ==========================================
const analyzeTextBtn = document.querySelector('#analyzeTextBtn');
if (analyzeTextBtn) {
  analyzeTextBtn.addEventListener('click', async () => {
    const text = document.querySelector('#semanticText').value.trim();
    const resDiv = document.querySelector('#semanticResult');
    if (!text) return;

    resDiv.innerHTML = "Menganalisis...";
    try {
      const tokenRes = await fetch('/api/local-token');
      const token = (await tokenRes.json()).token;
      
      const res = await fetch('/api/semantic/test', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-SIFI-Token': token},
        body: JSON.stringify({text: text})
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error);
      
      const color = data.threat_score > 5 ? 'red' : (data.threat_score > 0 ? 'orange' : 'green');
      
      let html = `<h3>Threat Score: <span style="color:${color}">${data.threat_score} / 10</span></h3>`;
      html += `<p><strong>Negatif:</strong> ${data.is_negative} | <strong>Hedging (Ketidakpastian):</strong> ${data.is_hedged}</p>`;
      html += `<p><strong>Entitas Terdeteksi:</strong> ${data.detected_entities.length > 0 ? data.detected_entities.join(', ') : '<em>Tidak ada</em>'}</p>`;
      
      resDiv.innerHTML = html;
    } catch (err) {
      resDiv.innerHTML = `<span style="color:red;">Error: ${err.message}</span>`;
    }
  });
}
