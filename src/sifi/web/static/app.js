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
loadCatalog();
