const badge = document.querySelector('#healthBadge');
const integrity = document.querySelector('#integrity');
const result = document.querySelector('#result');
const catalog = document.querySelector('#catalog');
const recordCount = document.querySelector('#recordCount');

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
      meta.textContent = `${item.kind === 'session' ? 'Sesi' : 'Brief'} · ${item.issue_id}`;
      button.append(meta);
      button.addEventListener('click', () => openRecord(item.kind, item.id).catch(showError));
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
