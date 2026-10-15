const badge = document.querySelector('#healthBadge');
const integrity = document.querySelector('#integrity');
const result = document.querySelector('#result');

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

document.querySelectorAll('form[data-kind]').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const kind = form.dataset.kind;
    const id = new FormData(form).get('id').trim();
    result.innerHTML = '<p class="empty">Membaca rekam lokal…</p>';
    try {
      const response = await fetch(`/api/${kind}?id=${encodeURIComponent(id)}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Rekam tidak dapat dibuka');
      showRecord(kind, data);
    } catch (error) {
      result.innerHTML = '';
      const message = document.createElement('p');
      message.className = 'error';
      message.textContent = error.message;
      result.append(message);
    }
  });
});

loadStatus();
