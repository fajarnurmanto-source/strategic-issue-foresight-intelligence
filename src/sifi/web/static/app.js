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
  title.textContent = kind === 'session' ? 'Sesi analisis' : 'Brief berbasis bukti';
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
    result.append(exportButton);
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
    target.textContent = `${gate} · hasil ${data.assessment.result} · n=${data.assessment.analyzable_participants}`;
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
