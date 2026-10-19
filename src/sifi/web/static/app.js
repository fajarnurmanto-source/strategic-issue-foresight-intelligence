const AppState = { activeIssue: null, workerActive: true, chartInstance: null };

async function getToken() {
  const res = await fetch('/api/local-token', { cache: 'no-store' });
  return (await res.json()).token;
}

// === NAVIGATION ===
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    
    // Sembunyikan SEMUA view secara paksa (termasuk Issue Workspace)
    document.querySelectorAll('.view').forEach(v => {
        v.classList.remove('active');
        v.style.display = 'none';
    });
    
    item.classList.add('active');
    const targetId = item.getAttribute('data-target');
    const targetView = document.getElementById(targetId);
    
    if (targetView) {
        targetView.classList.add('active');
        targetView.style.display = 'block';
    }
    
    // Auto-refresh data
    if (targetId === 'view-overview') loadChart();
    if (targetId === 'view-sys-health') loadSystemHealth();
    if (targetId === 'view-issue-desk') {
        loadIssueList();
        startLiveStream();
    } else {
        stopLiveStream();
    }
  });
});

// === UNIFIED CONTEXT ===
function setIssueContext(id) {
  AppState.activeIssue = id;
  document.getElementById('activeIssueDisplay').textContent = id;
  document.querySelectorAll('.issue-input').forEach(input => { input.value = id; });
  
  // Pindah ke Issue Workspace (S03/S04)
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelectorAll('.view').forEach(v => {
      v.classList.remove('active');
      if(v.id !== 'view-issue-workspace') v.style.display = 'none';
  });
  
  const workspaceView = document.getElementById('view-issue-workspace');
  workspaceView.classList.add('active');
  workspaceView.style.display = 'block';
  
  loadIssueWorkspaceData(id);
}

async function loadIssueWorkspaceData(id) {
    document.getElementById('wsTitle').textContent = "Memuat...";
    document.getElementById('wsId').textContent = id;
    
    try {
        // Fetch Foresight data (untuk rationale & risk)
        const resF = await fetch(`/api/foresight/${id}`);
        const dataF = await resF.json();
        
        // Fetch Issue data (untuk evidence & unknowns)
        // Karena endpoint issue detail belum ada, kita gunakan data dari allIssuesData
        const issue = allIssuesData.find(i => i.id === id);
        
        if (issue) {
            document.getElementById('wsTitle').textContent = issue.title;
            const dEl = document.getElementById('wsDomain');
            if (dEl) {
                dEl.innerHTML = issue.domain
                    ? `<span class="badge" style="background: rgba(139,195,74,0.15); color: var(--accent);">Domain: ${issue.domain}</span>`
                    : '';
            }
        }
        
        // --- S03: APA PERSOALANNYA? ---
        // Gunakan title sebagai summary sementara karena belum ada field "description"
        document.getElementById('wsSummary').textContent = issue ? issue.title : "Deskripsi belum tersedia.";
        
        // --- S03: YANG DIKETAHUI ---
        const knownsList = document.getElementById('wsKnowns');
        knownsList.innerHTML = '';
        if (issue) {
            const sup = issue.supporting?.length || 0;
            const con = issue.contradictory?.length || 0;
            const ctx = issue.context_only?.length || 0;
            const exc = issue.excluded?.length || 0;
            const total = sup + con + ctx + exc;
            
            if (total > 0) {
                knownsList.innerHTML += `<li>Terdapat <strong>${total} bukti</strong> yang tercatat di database.</li>`;
                knownsList.innerHTML += `<li style="font-size:0.85rem; color:var(--text-muted); margin-top:4px; margin-bottom:10px;">
                    (Pendukung: ${sup} | Kontradiksi: ${con} | Konteks: ${ctx} | Dikecualikan: ${exc})
                </li>`;
            } else {
                knownsList.innerHTML = '<li>Belum ada fakta yang terverifikasi.</li>';
            }
            
            if (dataF.risk && dataF.risk.level !== 'INSUFFICIENT') {
                knownsList.innerHTML += `<li>Tingkat risiko terdeteksi: <strong>${dataF.risk.level}</strong>.</li>`;
            }
        } else {
            knownsList.innerHTML = '<li>Gagal memuat data isu.</li>';
        }
        
        // --- S03: YANG BELUM DIKETAHUI ---
        const unknownsList = document.getElementById('wsUnknowns');
        unknownsList.innerHTML = '';
        if (dataF.risk && dataF.risk.abstention_reasons && dataF.risk.abstention_reasons.length > 0) {
            dataF.risk.abstention_reasons.forEach(reason => {
                unknownsList.innerHTML += `<li>${reason}</li>`;
            });
        } else if (dataF.risk.level === 'INSUFFICIENT') {
             unknownsList.innerHTML = '<li>Belum cukup bukti untuk menyimpulkan.</li>';
        } else {
             unknownsList.innerHTML = '<li>Belum ada catatan ketidakpastian dari sistem.</li>';
        }
        
        // --- S03: MENGAPA RELEVAN ---
        if (dataF.risk && dataF.risk.rationale) {
            document.getElementById('wsRationale').innerHTML = `<p>${dataF.risk.rationale}</p>`;
        } else {
            document.getElementById('wsRationale').innerHTML = `<p>Analisis belum tersedia.</p>`;
        }
        
        // --- S05: TIMELINE & PERKEMBANGAN ---
        const tl = document.getElementById('wsTimeline');
        if (tl) {
            tl.innerHTML = '';
            
            // Asumsi: Kita menggunakan data yang ada untuk membentuk alur
            let timeString = "Waktu tidak diketahui";
            if (issue && issue.recorded_at) {
                timeString = new Date(issue.recorded_at).toLocaleString('id-ID');
            }
            
            let evCount = dataF.risk.basis_evidence_ids ? dataF.risk.basis_evidence_ids.length : 0;
            let riskLevel = dataF.risk.level;
            
            // Titik 1: Isu Ditemukan
            tl.innerHTML += `
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-time">${timeString}</div>
                        <div class="timeline-title">Sinyal Awal Diterima</div>
                        <div class="timeline-desc">Sistem mendeteksi isu baru masuk ke dalam database dari sumber intelijen.</div>
                    </div>
                </div>
            `;
            
            // Titik 2: Bukti Terkumpul
            tl.innerHTML += `
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <div class="timeline-time">Fase Pengumpulan Bukti</div>
                        <div class="timeline-title">${evCount} Bukti Ditautkan</div>
                        <div class="timeline-desc">Sistem berhasil mengumpulkan bukti pendukung dan kontradiksi yang relevan dengan isu ini.</div>
                    </div>
                </div>
            `;

            // Titik 3: Penilaian AI (Hipotesis)
            tl.innerHTML += `
                <div class="timeline-item">
                    <div class="timeline-dot risk"></div>
                    <div class="timeline-content">
                        <div class="timeline-time">Penilaian Saat Ini</div>
                        <div class="timeline-title">Analisis Risiko: ${riskLevel}</div>
                        <div class="timeline-desc">AI menyimpulkan tingkat risiko saat ini berdasarkan bukti yang ada. <em>(Ini adalah hipotesis, bukan fakta final).</em></div>
                    </div>
                </div>
            `;
        }

        // --- S04: PEMERIKSAAN BUKTI ---
        const supList = document.getElementById('wsEvidenceSupporting');
        const conList = document.getElementById('wsEvidenceContradictory');
        const ctxList = document.getElementById('wsEvidenceContext');
        const excList = document.getElementById('wsEvidenceExcluded');
        
        supList.innerHTML = '<li style="color:#666;">Tidak ada bukti.</li>';
        conList.innerHTML = '<li style="color:#666;">Tidak ada bukti.</li>';
        ctxList.innerHTML = '<li style="color:#666;">Tidak ada bukti.</li>';
        excList.innerHTML = '<li style="color:#666;">Tidak ada bukti.</li>';
        
        if (issue) {
            if (issue.supporting && issue.supporting.length > 0) {
                supList.innerHTML = issue.supporting.map(ev => `<li>${ev}</li>`).join('');
            }
            if (issue.contradictory && issue.contradictory.length > 0) {
                conList.innerHTML = issue.contradictory.map(ev => `<li>${ev}</li>`).join('');
            }
            if (issue.context_only && issue.context_only.length > 0) {
                ctxList.innerHTML = issue.context_only.map(ev => `<li>${ev}</li>`).join('');
            }
            if (issue.excluded && issue.excluded.length > 0) {
                excList.innerHTML = issue.excluded.map(ev => `<li>${ev}</li>`).join('');
            }
        }
        
    } catch(e) {
        document.getElementById('wsTitle').textContent = "Gagal memuat data";
        document.getElementById('wsSummary').textContent = e.message;
    }
}

// Event Listener Tombol di Workspace
document.getElementById('wsCloseBtn').addEventListener('click', () => {
    document.querySelector('[data-target="view-issue-desk"]').click();
});

document.getElementById('wsGoForesightBtn').addEventListener('click', () => {
    document.querySelector('[data-target="view-foresight"]').click();
    document.getElementById('inputForesight').value = AppState.activeIssue;
    document.getElementById('btnForesight').click();
});

document.getElementById('wsGoBriefBtn').addEventListener('click', () => {
    document.querySelector('[data-target="view-briefs"]').click();
    document.getElementById('inputBrief').value = AppState.activeIssue;
});

// === 1. EXECUTIVE BRIEFING (Chart) ===
async function loadChart() {
  const ctx = document.getElementById('riskChart');
  if (!ctx) return;
  try {
    const res = await fetch('/api/stats');
    const data = await res.json();
    if (AppState.chartInstance) AppState.chartInstance.destroy();
    
    AppState.chartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['HIGH', 'ELEVATED', 'LOW', 'INSUFFICIENT'],
        datasets: [{ data: [data.HIGH, data.ELEVATED, data.LOW, data.INSUFFICIENT],
          backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#64748b'], borderWidth: 0 }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff' } } } }
    });
  } catch (e) { console.error(e); }
}
document.getElementById('refreshChartBtn').addEventListener('click', loadChart);

// === 2. ISSUE DESK (Brand24 Stream) ===
let allIssuesData = [];
let liveStreamInterval = null;
let lastTopIssueId = null;
let activeRiskFilter = 'all';
let globalWatchFilter = '';

async function loadIssueList() {
  const container = document.getElementById('issueListContainer');
  if (!container) return;
  container.innerHTML = '<p>Memuat isu...</p>';
  
  try {
    const res = await fetch('/api/issues');
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "API Error");
    allIssuesData = data;
    renderIssueStream();
  } catch (e) { 
    container.innerHTML = `<p style="color:red;">Gagal memuat: ${e.message}</p>`; 
  }
}

function renderIssueStream(animateNew = false) {
  const container = document.getElementById('issueListContainer');
  const query = document.getElementById('searchIssue').value.toLowerCase();
  const filterType = document.getElementById('filterType').value;

  const filtered = allIssuesData.filter(issue => {
    const matchSearch = issue.id.toLowerCase().includes(query) || issue.title.toLowerCase().includes(query);
    const matchType = filterType === 'all' || issue.type === filterType;
    return matchSearch && matchType;
  });

  // Update Metrics
  let highCount = 0, elevCount = 0;
  filtered.forEach(i => {
      if(i.risk === 'HIGH') highCount++;
      if(i.risk === 'ELEVATED') elevCount++;
  });
  document.getElementById('metricTotal').textContent = filtered.length;
  document.getElementById('metricHigh').textContent = highCount;
  document.getElementById('metricElevated').textContent = elevCount;

  if (filtered.length === 0) {
    container.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding: 40px;">Tidak ada isu yang cocok dengan filter.</p>';
    return;
  }

  container.innerHTML = '';
  filtered.forEach((issue, index) => {
    const div = document.createElement('div');
    
    // Jika animasi aktif dan ini adalah kartu teratas, dan ID-nya beda dari sebelumnya
    if (animateNew && index === 0 && issue.id !== lastTopIssueId) {
        div.className = 'issue-card new-item';
        lastTopIssueId = issue.id; // Update pelacak
    } else {
        div.className = 'issue-card';
    }
    
    let badgeClass = 'badge-low';
    if (issue.risk === 'HIGH') badgeClass = 'badge-high';
    if (issue.risk === 'ELEVATED') badgeClass = 'badge-elevated';

    div.innerHTML = `
      <div class="issue-content">
        <h3>${issue.title}</h3>
        <div class="issue-meta">
          <span style="font-family: monospace;">${issue.id}</span>
          <span class="badge badge-source">${issue.type}</span>
          ${issue.domain ? `<span class="badge" style="background: rgba(139,195,74,0.15); color: var(--accent);">${issue.domain}</span>` : ''}
        </div>
      </div>
      <div style="text-align: right;">
        <span class="badge ${badgeClass}">${issue.risk}</span>
        <div style="margin-top: 8px; color: var(--accent); font-weight: 600; font-size: 0.85rem;">Investigasi &rarr;</div>
      </div>
    `;
    div.onclick = () => setIssueContext(issue.id);
    container.appendChild(div);
  });
}
document.getElementById('searchIssue').addEventListener('input', renderIssueStream);
document.getElementById('filterType').addEventListener('change', renderIssueStream);

// === 3. FORESIGHT ===
document.getElementById('btnForesight').addEventListener('click', async () => {
  const id = document.getElementById('inputForesight').value.trim();
  const out = document.getElementById('resultForesight');
  const self = document.getElementById('btnForesight');
  const originalLabel = self ? self.textContent : 'Analisis Isu';
  if (self) { self.textContent = 'Memproses...'; self.disabled = true; }
  if (!id) return;
  out.innerHTML = "<p style='color:var(--text-muted);'>Menganalisis...</p>";
  
  try {
    const res = await fetch(`/api/foresight/${id}`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Gagal");
    
    if (data.abstained) {
        out.innerHTML = `
            <div class="panel" style="border-left: 4px solid var(--risk-elevated);">
                <h2 style="color: var(--risk-elevated); margin-bottom: 10px;">Analisis Ditunda (Insufficient Data)</h2>
                <p>Sistem memilih untuk tidak memberikan penilaian risiko karena alasan berikut:</p>
                <ul style="margin-top: 10px; padding-left: 20px;">
                    ${data.abstention_reasons.map(r => `<li>${r}</li>`).join('')}
                </ul>
            </div>
        `;
        return;
    }

    let badgeClass = 'badge-low';
    if (data.risk.level === 'HIGH') badgeClass = 'badge-high';
    if (data.risk.level === 'ELEVATED') badgeClass = 'badge-elevated';

    let html = `
      <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        
        <div>
            <div class="panel" style="text-align: center;">
                <p style="color: var(--text-muted); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Tingkat Risiko</p>
                <h1 class="badge ${badgeClass}" style="font-size: 2rem; display: inline-block; margin: 15px 0; padding: 10px 20px;">${data.risk.level}</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Arah: <strong style="color: var(--text-main);">${data.risk.direction}</strong></p>
            </div>

            <div class="panel">
                <h3 style="margin-bottom: 15px; color: var(--accent);">Rekomendasi Strategis</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    ${data.recommendation_proposals.map(rec => `
                        <div style="background: var(--bg-body); padding: 12px; border-radius: 6px; border-left: 3px solid var(--accent); font-size: 0.9rem;">
                            ${rec}
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>

        <div>
            <div class="panel">
                <h3 style="margin-bottom: 10px;">Mengapa AI Mengambil Kesimpulan Ini? (Rationale)</h3>
                <p style="line-height: 1.6; color: var(--text-muted);">${data.risk.rationale}</p>
            </div>

            <div class="panel" style="border-left: 4px solid var(--risk-elevated);">
                <h3 style="margin-bottom: 10px; color: var(--risk-elevated);">Watch Conditions (Kondisi Pemantauan)</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">
                    <em>Hipotesis ini dapat berubah jika kondisi berikut terpenuhi:</em>
                </p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    ${data.watch_conditions.map(w => `
                        <div style="background: var(--bg-body); padding: 12px; border-radius: 6px; font-size: 0.9rem;">
                            <strong style="color: var(--text-main);">${w.condition}</strong><br>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">Trigger: ${w.trigger}</span><br>
                            <span style="color: var(--risk-high); font-size: 0.8rem;">Falsifikasi: ${w.falsification_condition}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>

      </div>
    `;
    out.innerHTML = html;
  } catch (e) { 
      out.innerHTML = `<div class="panel" style="border-left: 4px solid var(--risk-high);"><p style="color:var(--risk-high);">Error: ${e.message}</p></div>`; 
  } finally {
      if (self) { self.textContent = originalLabel; self.disabled = false; }
  }
});

// === 4. SYSTEMIC ===
document.getElementById('btnSystemic').addEventListener('click', async () => {
  const out = document.getElementById('resultSystemic');
  out.innerHTML = "<p>Memindai...</p>";
  try {
    const res = await fetch('/api/systemic-report');
    const data = await res.json();
    let html = `<h3>Sistem memindai ${data.issues_analyzed} Isu</h3>`;
    if(data.systemic_alerts.length === 0) html += `<p>Tidak ada peringatan.</p>`;
    else html += `<ul>${data.systemic_alerts.map(a => `<li><strong>${a.alert_type}</strong>: ${a.description}</li>`).join('')}</ul>`;
    out.innerHTML = html;
  } catch (e) { out.innerHTML = `<p style="color:red;">Error: ${e.message}</p>`; }
});

// === 5. BRIEFS ===
document.getElementById('btnViewBrief').addEventListener('click', async () => {
  let id = document.getElementById('inputBrief').value.trim();
  const out = document.getElementById('resultBrief');
  if (!id) return;
  if (!id.startsWith("BRIEF-")) id = `BRIEF-${id}`;
  
  out.innerHTML = "<p style='color:#475569;'>Memuat dokumen...</p>";
  try {
    const res = await fetch(`/api/brief/view?id=${id}`);
    const data = await res.json();
    if (res.ok) {
        // Render markdown menjadi HTML cantik
        let htmlContent = renderMarkdown(data.content);
        
        // Tambahkan stempel waktu dan status rahasia/simulasi (Sesuai Handoff S07)
        htmlContent += `
            <div class="footer-note">
                <hr>
                <p>Dokumen ini dihasilkan secara otomatis oleh SIFI Intelligence Engine.</p>
                <p><strong>Status:</strong> DRAFT / SIMULASI (Data Sintetis)</p>
                <p>Analytical Cutoff: ${new Date().toLocaleString('id-ID')}</p>
            </div>
        `;
        out.innerHTML = htmlContent;
    } else {
        out.innerHTML = `<p style="color:var(--risk-high);">Brief tidak ditemukan untuk ID ini.</p>`;
    }
  } catch (e) { 
      out.innerHTML = `<p style="color:var(--risk-high);">Error: ${e.message}</p>`; 
  }
});
document.getElementById('btnDownloadBrief').addEventListener('click', () => {
  let id = document.getElementById('inputBrief').value.trim();
  if (!id) return;
  if (!id.startsWith("BRIEF-")) id = `BRIEF-${id}`;
  window.location.href = `/api/brief/download?id=${id}`;
});
document.getElementById('btnGenerateBrief').addEventListener('click', async () => {
  const id = document.getElementById('inputBrief').value.trim();
  if (!id) return;
  try {
    const token = await getToken();
    const res = await fetch('/api/brief/generate', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: JSON.stringify({issue_id: id}) });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error);
    alert("✅ Brief berhasil disusun oleh AI!"); 
    document.getElementById('btnViewBrief').click();
  } catch(e) { alert("Error: " + e.message); }
});

// === 6. DEV INGEST ===
document.getElementById('btnIngestFeed').addEventListener('click', async () => {
  const msg = document.getElementById('devIngestMsg');
  msg.textContent = "Mengimpor..."; msg.style.color="#fff";
  try {
    const token = await getToken();
    const res = await fetch('/api/ingest/feed', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: '{}' });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error);
    msg.textContent = `✅ Berhasil impor ${data.ingested} isu.`; msg.style.color="var(--risk-low)";
  } catch(e) { msg.textContent = "Error: "+e.message; msg.style.color="var(--risk-high)"; }
});
document.getElementById('btnHistorical').addEventListener('click', async () => {
  const msg = document.getElementById('devIngestMsg');
  const start = document.getElementById('devStartDate').value;
  const end = document.getElementById('devEndDate').value;
  if(!start || !end) { msg.textContent="Pilih tanggal"; msg.style.color="var(--risk-high)"; return; }
  msg.textContent = "Menarik data (mohon tunggu)..."; msg.style.color="#fff";
  try {
    const token = await getToken();
    const res = await fetch('/api/ingest/historical', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: JSON.stringify({start_date: start, end_date: end}) });
    const data = await res.json();
    if(!res.ok) throw new Error(data.error);
    msg.textContent = `✅ ${data.message}`; msg.style.color="var(--risk-low)";
    
    // Auto-filter logic
    setTimeout(() => {
        document.getElementById('filterType').value = 'historical_crawl';
        document.getElementById('searchIssue').value = '';
        document.querySelector('[data-target="view-issue-desk"]').click();
    }, 1000);
  } catch(e) { msg.textContent = "Error: "+e.message; msg.style.color="var(--risk-high)"; }
});
document.getElementById('btnToggleWorker').addEventListener('click', async (e) => {
  const action = AppState.workerActive ? "stop" : "start";
  try {
    const token = await getToken();
    const res = await fetch('/api/worker/toggle', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: JSON.stringify({action}) });
    const data = await res.json();
    if(res.ok) {
      AppState.workerActive = !AppState.workerActive;
      e.target.textContent = AppState.workerActive ? "Hentikan Auto-Ingest" : "Mulai Auto-Ingest";
      e.target.style.background = AppState.workerActive ? "#ef4444" : "var(--risk-low)";
    } else { alert("Server menolak perintah: " + (data.error || "Unknown error")); }
  } catch(err) { alert("Kesalahan jaringan: " + err.message); }
});

// === 7. DEV NLP & SIM ===
document.getElementById('btnNlp').addEventListener('click', async () => {
  const text = document.getElementById('nlpText').value;
  const out = document.getElementById('nlpResult');
  out.textContent = "Menganalisis...";
  try {
    const token = await getToken();
    const res = await fetch('/api/semantic/test', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: JSON.stringify({text}) });
    const data = await res.json();
    out.innerHTML = `<strong>Threat Score:</strong> ${data.threat_score}/10<br><strong>Entities:</strong> ${data.detected_entities.join(', ') || '-'}`;
  } catch(e) { out.textContent = "Error: "+e.message; }
});
document.getElementById('btnSimulate').addEventListener('click', async () => {
  const id = document.getElementById('inputSim').value;
  const out = document.getElementById('simResult');
  if(!id) return;
  out.textContent = "Mensimulasikan...";
  try {
    const token = await getToken();
    const res = await fetch('/api/foresight/simulate', { method: 'POST', headers: {'Content-Type':'application/json', 'X-SIFI-Token': token}, body: JSON.stringify({issue_id: id}) });
    const data = await res.json();
    out.innerHTML = `Risiko Awal: <span class="badge badge-${data.current_risk.toLowerCase()}">${data.current_risk}</span> &rarr; Risiko Simulasi: <span class="badge badge-${data.simulated_risk.toLowerCase()}">${data.simulated_risk}</span>`;
  } catch(e) { out.textContent = "Error: "+e.message; }
});

setTimeout(loadChart, 500);

// === MARKDOWN PARSER ===
function renderMarkdown(md) {
    let html = md
        // Headings
        .replace(/^# (.*$)/gim, '<h1>$1</h1>')
        .replace(/^## (.*$)/gim, '<h2>$1</h2>')
        .replace(/^### (.*$)/gim, '<h3>$1</h3>')
        // Bold
        .replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>')
        // Lists
        .replace(/^\- (.*$)/gim, '<li>$1</li>')
        // Code blocks
        .replace(/`([^`]+)`/gim, '<code>$1</code>')
        // Line breaks
        .replace(/\n$/gim, '<br />');

    // Bungkus list items dengan <ul>
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    // Ganti baris baru murni dengan paragraf
    html = html.split('\n\n').map(p => {
        if (p.trim().startsWith('<h') || p.trim().startsWith('<ul')) return p;
        return `<p>${p}</p>`;
    }).join('');

    // Ganti HR (---)
    html = html.replace(/^---$/gim, '<hr>');

    return html;
}

// === LIVE STREAM POLLING ===
function startLiveStream() {
    if (liveStreamInterval) clearInterval(liveStreamInterval);
    
    // Set lastTopIssueId awal agar tidak berkedip saat pertama load
    if (allIssuesData.length > 0) lastTopIssueId = allIssuesData[0].id;

    liveStreamInterval = setInterval(async () => {
        try {
            const res = await fetch('/api/issues');
            const data = await res.json();
            
            // Jika ada data baru (panjang array beda, atau ID teratas beda)
            if (Array.isArray(data) && data.length > 0) {
                if (!allIssuesData.length || data[0].id !== allIssuesData[0].id) {
                    allIssuesData = data;
                    renderIssueStream(true); // Trigger animasi
                }
            }
        } catch(e) {
            console.error("[LIVE STREAM] Gagal polling:", e);
        }
    }, 5000); // Cek setiap 5 detik
}

function stopLiveStream() {
    if (liveStreamInterval) {
        clearInterval(liveStreamInterval);
        liveStreamInterval = null;
    }
}

// === 8. SYSTEM HEALTH (S08) ===
async function loadSystemHealth() {
    try {
        const res = await fetch('/api/system-health');
        const data = await res.json();
        
        if (!res.ok) throw new Error(data.error || "Gagal mengambil data");

        // 1. Worker Status
        const isWorkerRunning = data.worker_running;
        const workerIcon = document.getElementById('healthWorkerIcon');
        const workerText = document.getElementById('healthWorkerStatus');
        
        if (isWorkerRunning) {
            workerIcon.textContent = "🟢";
            workerText.textContent = "Aktif (Menyuntik Data)";
            workerText.style.color = "var(--risk-low)";
        } else {
            workerIcon.textContent = "🔴";
            workerText.textContent = "Berhenti";
            workerText.style.color = "var(--risk-high)";
        }

        // 2. Database Status
        const dbText = document.getElementById('healthDbStatus');
        if (data.database_status === "HEALTHY") {
            dbText.textContent = "Terhubung (Healthy)";
            dbText.style.color = "var(--risk-low)";
        } else {
            dbText.textContent = "Bermasalah";
            dbText.style.color = "var(--risk-high)";
        }

        // 3. Counts
        document.getElementById('healthCountIssues').textContent = data.counts.ISSUE || 0;
        document.getElementById('healthCountBriefs').textContent = data.counts.EVIDENCE_LINKED_ANALYTICAL_BRIEF || 0;
        document.getElementById('healthCountSessions').textContent = data.counts.BOUNDED_ANALYST_WORKFLOW_SESSION || 0;

    } catch(e) {
        console.error("[HEALTH] Error:", e);
        document.getElementById('healthWorkerStatus').textContent = "Gagal memuat";
        document.getElementById('healthDbStatus').textContent = "Gagal memuat";
    }
}

document.getElementById('btnRefreshHealth').addEventListener('click', loadSystemHealth);
setInterval(() => {
    // Auto refresh jika tab sedang terbuka
    if (document.getElementById('view-sys-health').classList.contains('active')) {
        loadSystemHealth();
    }
}, 5000);

// === RESET SIMULATION (RISK-01) ===


// === NARRATIVE MODE (RISK-03) ===
document.getElementById('wsGoNarrativeBtn').addEventListener('click', () => {
    document.querySelectorAll('.view').forEach(v => { v.classList.remove('active'); v.style.display = 'none'; });
    document.getElementById('view-narrative').classList.add('active');
    document.getElementById('view-narrative').style.display = 'block';
    buildNarrative(AppState.activeIssue);
});

document.getElementById('btnExitNarrative').addEventListener('click', () => {
    document.querySelectorAll('.view').forEach(v => { v.classList.remove('active'); v.style.display = 'none'; });
    document.getElementById('view-issue-workspace').classList.add('active');
    document.getElementById('view-issue-workspace').style.display = 'block';
});

async function buildNarrative(issueId) {
    const container = document.getElementById('narrativeContent');
    container.innerHTML = "<p style='color:#fff; text-align:center;'>Membangun narasi...</p>";
    
    try {
        const res = await fetch(`/api/foresight/${issueId}`);
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);
        
        const riskLevel = data.risk.level;
        
        let html = `
            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 1: Sinyal Awal</h2>
                <p class="narrative-text">Sistem mendeteksi anomali pada isu <strong>${issueId}</strong>.</p>
                <p class="narrative-subtext">Analisis awal menunjukkan tingkat risiko <strong>${riskLevel}</strong> dengan arah tren <strong>${data.risk.direction}</strong>.</p>
            </div>
            
            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 2: Perakitan Bukti</h2>
                <p class="narrative-text">Bukti yang mendukung kesimpulan ini:</p>
                <ul style="color:#94a3b8; font-family:monospace; font-size:1.1rem;">
                    ${data.risk.basis_evidence_ids.map(ev => `<li>${ev}</li>`).join('')}
                </ul>
                <p class="narrative-subtext">Catatan: Saat ini terdapat ${data.risk.basis_evidence_ids.length} bukti valid yang terkumpul.</p>
            </div>

            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 3: Konvergensi Risiko</h2>
                <p class="narrative-text">Mengapa AI menilai level ini?</p>
                <p class="narrative-subtext">${data.risk.rationale}</p>
            </div>

            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 4: Koneksi Sistemik</h2>
                <p class="narrative-text">Isu ini tidak berdiri sendiri.</p>
                <p class="narrative-subtext">Gunakan menu Analisis Sistemik untuk melihat apakah ada entitas atau bukti yang tumpang tindih dengan isu lain di database.</p>
            </div>

            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 5: Transisi Skenario</h2>
                <p class="narrative-text">Apa yang bisa membatalkan hipotesis ini?</p>
                <ul style="color:#ef4444; font-size:1.1rem;">
                    ${data.watch_conditions.map(w => `<li><strong>${w.condition}</strong> — Trigger: ${w.trigger}</li>`).join('')}
                </ul>
            </div>

            <div class="narrative-stage">
                <h2 class="narrative-title">Tahap 6: Resolusi Keputusan</h2>
                <p class="narrative-text">Langkah selanjutnya yang direkomendasikan:</p>
                <ul style="color:#10b981; font-size:1.1rem; font-weight:bold;">
                    ${data.recommendation_proposals.map(rec => `<li>${rec}</li>`).join('')}
                </ul>
                <button id="btnExitNarrativeInline" type="button" style="margin-top: 20px;">Kembali ke Operational Workspace</button>
            </div>
        `;
        container.innerHTML = html;
    } catch(e) {
        container.innerHTML = `<p style="color:red;">Gagal memuat narasi: ${e.message}</p>`;
    }
}

// === SCROLL NARRATIVE OBSERVER ===
function setupNarrativeObserver() {
    const stages = document.querySelectorAll('.narrative-stage');
    
    // Reset state jika ada animasi sebelumnya
    stages.forEach(stage => stage.classList.remove('is-visible'));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Opsional: observer.unobserve(entry.target); // Jika ingin animasi sekali saja
            } else {
                // Hapus class jika ingin animasi bisa diputar ulang saat scroll naik-turun
                entry.target.classList.remove('is-visible'); 
            }
        });
    }, {
        root: document.getElementById('view-narrative'), // Wadah scroll
        threshold: 0.2 // Picu saat 20% elemen terlihat
    });

    stages.forEach(stage => observer.observe(stage));
}

// Panggil observer setelah narasi selesai dibuat
const originalBuildNarrative = buildNarrative;
buildNarrative = async function(id) {
    await originalBuildNarrative(id);
    // Beri sedikit jeda agar DOM selesai dirender
    setTimeout(setupNarrativeObserver, 100);
};

document.getElementById('btnResetSim').addEventListener('click', async () => {
    if (!confirm("Peringatan: Tindakan ini akan menghapus semua data simulasi. Lanjutkan?")) return;
    
    const msg = document.getElementById('devIngestMsg');
    msg.textContent = "Menghapus data simulasi...";
    msg.style.color = "var(--risk-elevated)";
    
    try {
        const token = await getToken();
        const res = await fetch('/api/system/reset-simulation', {
            method: 'POST',
            headers: {'Content-Type':'application/json', 'X-SIFI-Token': token},
            body: '{}'
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error);
        msg.textContent = "Berhasil: " + data.message;
        msg.style.color = "var(--risk-low)";
    } catch(e) {
        msg.textContent = "Error: " + e.message;
        msg.style.color = "var(--risk-high)";
    }
});

// === INTERACTIVE METRICS LOGIC ===
function toggleRiskFilter(level) {
    if (activeRiskFilter === level) {
        activeRiskFilter = 'all'; // Toggle off jika diklik lagi
    } else {
        activeRiskFilter = level;
    }

    // Update visual class
    document.querySelectorAll('.metric-box').forEach(b => b.classList.remove('active-filter'));
    if (activeRiskFilter === 'HIGH') document.getElementById('boxHigh').classList.add('active-filter');
    else if (activeRiskFilter === 'ELEVATED') document.getElementById('boxElevated').classList.add('active-filter');
    else document.getElementById('boxTotal').classList.add('active-filter');

    // Render ulang
    renderIssueStream();
}

document.getElementById('boxTotal').addEventListener('click', () => toggleRiskFilter('all'));
document.getElementById('boxHigh').addEventListener('click', () => toggleRiskFilter('HIGH'));
document.getElementById('boxElevated').addEventListener('click', () => toggleRiskFilter('ELEVATED'));


// ==========================================
// DEFINITIVE FILTER & RENDER LOGIC (Overrides old)
// ==========================================
function renderIssueStream(animateNew = false) {
  const container = document.getElementById('issueListContainer');
  const searchEl = document.getElementById('searchIssue');
  const filterEl = document.getElementById('filterType');
  
  if (!container || !searchEl || !filterEl) return;

  const query = searchEl.value.toLowerCase();
  const filterType = filterEl.value;

  // 1. Terapkan Semua Filter
  const filtered = allIssuesData.filter(issue => {
    const id = (issue.id || "").toLowerCase();
    const title = (issue.title || "").toLowerCase();
    const type = issue.type || "";
    const risk = issue.risk || "";

    const matchSearch = id.includes(query) || title.includes(query);
    const matchType = filterType === 'all' || type === filterType;
    const matchRisk = activeRiskFilter === 'all' || risk === activeRiskFilter;
    
    const domainStr = (typeof issue === 'object' && issue.domain) ? String(issue.domain).toLowerCase() : '';
    const watchTerms = expandWatchTerms(globalWatchFilter);
    const matchWatch = matchesWatch([title, id, String(type).toLowerCase(), domainStr], watchTerms);

    return matchSearch && matchType && matchRisk && matchWatch;
  });

  // 2. Update Metrik (Berdasarkan data yang sedang difilter)
  document.getElementById('metricTotal').textContent = filtered.length;
  document.getElementById('metricHigh').textContent = filtered.filter(i => i.risk === 'HIGH').length;
  document.getElementById('metricElevated').textContent = filtered.filter(i => i.risk === 'ELEVATED').length;

  // 3. Render Kartu Isu
  if (filtered.length === 0) {
    container.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding: 40px;">Tidak ada isu yang cocok dengan filter.</p>';
    return;
  }

  container.innerHTML = '';
  filtered.forEach((issue, index) => {
    const div = document.createElement('div');
    
    // Animasi slide-down untuk data teratas
    if (animateNew && index === 0 && issue.id !== lastTopIssueId) {
        div.className = 'issue-card new-item';
        lastTopIssueId = issue.id;
    } else {
        div.className = 'issue-card';
    }
    
    let badgeClass = 'badge-low';
    if (issue.risk === 'HIGH') badgeClass = 'badge-high';
    if (issue.risk === 'ELEVATED') badgeClass = 'badge-elevated';

    div.innerHTML = `
      <div class="issue-content">
        <h3>${issue.title}</h3>
        <div class="issue-meta">
          <span style="font-family: monospace;">${issue.id}</span>
          <span class="badge badge-source">${issue.type}</span>
          ${issue.domain ? `<span class="badge" style="background: rgba(139,195,74,0.15); color: var(--accent);">${issue.domain}</span>` : ''}
        </div>
      </div>
      <div style="text-align: right;">
        <span class="badge ${badgeClass}">${issue.risk}</span>
        <div style="margin-top: 8px; color: var(--accent); font-weight: 600; font-size: 0.85rem;">Investigasi &rarr;</div>
      </div>
    `;
    div.onclick = () => setIssueContext(issue.id);
    container.appendChild(div);
  });
}


// === SAFE UX FIX B/C/D ===
// Pendekatan: event delegation, tidak mengubah kode lama.
document.addEventListener('DOMContentLoaded', () => {

    // FIX B: tombol di card narasi (yang di dalam #narrativeContent)
    const narrativeContainer = document.getElementById('narrativeContent');
    if (narrativeContainer) {
        narrativeContainer.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' &&
                e.target.textContent.includes('Operational Workspace')) {
                const exitBtn = document.getElementById('btnExitNarrative');
                if (exitBtn) exitBtn.click();
            }
        });
    }

    // FIX C: label tombol Analisis Isu saat loading
    const btnForesight = document.getElementById('btnForesight');
    if (btnForesight) {
        btnForesight.addEventListener('click', () => {
            btnForesight.textContent = 'Memproses...';
            setTimeout(() => { btnForesight.textContent = 'Analisis Isu'; }, 1000);
        });
    }

    // FIX D: entity link dari Analisis Sistemik
    const resultSystemic = document.getElementById('resultSystemic');
    if (resultSystemic) {
        resultSystemic.addEventListener('click', (e) => {
            const li = e.target.closest('li');
            if (!li) return;
            const text = li.textContent || '';
            if (!text.includes('SHARED_ENTITY')) return;

            const match = text.match(/same entity: '([^']+)'/);
            if (!match) return;

            const entity = match[1];

            // Buat visual feedback: cetak underline pada li yang diklik
            li.style.cursor = 'pointer';

            // Pindah tab + isi pencarian
            document.querySelector('[data-target="view-issue-desk"]').click();
            setTimeout(() => {
                document.getElementById('searchIssue').value = entity;
                document.getElementById('filterType').value = 'all';
                if (typeof renderIssueStream === 'function') renderIssueStream();
            }, 150);
        });

        // Cursor pointer untuk semua li SHARED_ENTITY (visual hint)
        const observer = new MutationObserver(() => {
            document.querySelectorAll('#resultSystemic li').forEach(li => {
                if (li.textContent.includes('SHARED_ENTITY')) {
                    li.style.cursor = 'pointer';
                    li.style.textDecoration = 'underline';
                    li.title = 'Klik untuk memfilter isu dengan entitas ini';
                }
            });
        });
        observer.observe(resultSystemic, { childList: true, subtree: true });
    }
});


// === FIX D v3: Shared Entity clickable ===
document.addEventListener('DOMContentLoaded', () => {
    const out = document.getElementById('resultSystemic');
    if (!out) return;

    const markLinks = () => {
        out.querySelectorAll('li').forEach(li => {
            const txt = li.textContent || '';
            if (txt.includes('SHARED_ENTITY') && !li.dataset.linkified) {
                li.dataset.linkified = 'true';
                li.style.cursor = 'pointer';
                li.style.textDecoration = 'underline';
                li.title = 'Klik untuk memfilter isu dengan entitas ini';
            }
        });
    };

    new MutationObserver(markLinks).observe(out, { childList: true, subtree: true });

    out.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        const txt = li.textContent || '';
        if (!txt.includes('SHARED_ENTITY')) return;
        const m = txt.match(/same entity: '([^']+)'/);
        if (!m) return;
        const entity = m[1];

        document.querySelector('[data-target="view-issue-desk"]').click();
        setTimeout(() => {
            const s = document.getElementById('searchIssue');
            const f = document.getElementById('filterType');
            if (s) s.value = entity;
            if (f) f.value = 'all';
            if (typeof renderIssueStream === 'function') renderIssueStream();
        }, 200);
    });
});


// === GLOBAL WATCH FILTER ===
async function ensureIssuesLoaded() {
    if (allIssuesData && allIssuesData.length > 0) return;
    try {
        const res = await fetch('/api/issues');
        const data = await res.json();
        if (Array.isArray(data)) allIssuesData = data;
    } catch(e) { console.error('[WATCH] Gagal memuat isu:', e); }
}

function applyWatchToArray(issues) {
    if (!globalWatchFilter.trim()) return issues;
    const terms = expandWatchTerms(globalWatchFilter);
    return issues.filter(issue => {
        const texts = [
            String(issue.title || '').toLowerCase(),
            String(issue.id || '').toLowerCase(),
            String(issue.type || '').toLowerCase(),
            String(issue.domain || '').toLowerCase()
        ];
        return matchesWatch(texts, terms);
    });
}

async function loadChart() {
    await ensureIssuesLoaded();
    const ctx = document.getElementById('riskChart');
    if (!ctx) return;

    const filtered = applyWatchToArray(allIssuesData);
    const stats = { HIGH: 0, ELEVATED: 0, LOW: 0, INSUFFICIENT: 0 };
    filtered.forEach(i => {
        const r = i.risk || 'INSUFFICIENT';
        if (stats[r] !== undefined) stats[r]++;
    });

    const label = document.getElementById('chartTitleLabel');
    if (label) {
        label.textContent = globalWatchFilter.trim()
            ? `Distribusi Risiko — Objek Pantauan: "${globalWatchFilter}" (${filtered.length} isu)`
            : `Distribusi Risiko (Seluruh Database, ${filtered.length} isu)`;
    }

    if (AppState.chartInstance) AppState.chartInstance.destroy();
    AppState.chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['HIGH', 'ELEVATED', 'LOW', 'INSUFFICIENT'],
            datasets: [{
                data: [stats.HIGH, stats.ELEVATED, stats.LOW, stats.INSUFFICIENT],
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#64748b'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff' } } } }
    });
}

function applyGlobalFilter() {
    const input = document.getElementById('globalWatchInput');
    if (!input) return;
    globalWatchFilter = input.value.trim();

    const activeView = document.querySelector('.view.active');
    if (!activeView) return;

    if (activeView.id === 'view-overview') {
        loadChart();
    } else if (activeView.id === 'view-issue-desk') {
        if (typeof renderIssueStream === 'function') renderIssueStream();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btnApply = document.getElementById('btnApplyWatch');
    const btnReset = document.getElementById('btnResetWatch');
    const input = document.getElementById('globalWatchInput');

    if (btnApply) btnApply.addEventListener('click', applyGlobalFilter);
    if (btnReset) btnReset.addEventListener('click', () => {
        if (input) input.value = '';
        globalWatchFilter = '';
        applyGlobalFilter();
    });
    if (input) input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') applyGlobalFilter();
    });
});


// === WATCH SYNONYMS ===
// Bilingual bridge: user mengetik bahasa Indonesia, judul isu bahasa Inggris.
const WATCH_SYNONYMS = {
    'logistik': ['logistic', 'supply chain', 'port', 'transport', 'cargo'],
    'logistics': ['logistik', 'supply chain', 'port', 'transport'],
    'supply chain': ['logistik', 'logistics', 'port', 'transport'],
    'keamanan': ['cyber', 'attack', 'hack', 'security'],
    'security': ['keamanan', 'cyber', 'attack'],
    'cyber': ['keamanan', 'security', 'attack'],
    'ekonomi': ['market', 'economic', 'financial', 'finance'],
    'economy': ['ekonomi', 'market', 'economic'],
    'market': ['ekonomi', 'economic'],
    'politik': ['political', 'unrest', 'protest', 'politic'],
    'political': ['politik', 'unrest', 'protest'],
    'lingkungan': ['natural disaster', 'flood', 'earthquake', 'disaster', 'natural'],
    'environment': ['lingkungan', 'disaster', 'natural'],
    'disaster': ['lingkungan', 'natural disaster', 'flood']
};

function expandWatchTerms(query) {
    const q = String(query || '').toLowerCase().trim();
    if (!q) return [];

    // --- 1. Tokenize with phrase detection (longest-match first) ---
    const phraseKeys = Object.keys(WATCH_SYNONYMS).sort((a, b) => b.length - a.length);
    const units = [];
    let remaining = q;
    while (remaining.length > 0) {
        remaining = remaining.replace(/^\s+/, '');
        if (!remaining) break;

        let matchedPhrase = null;
        for (const key of phraseKeys) {
            if (key.includes(' ') && remaining.startsWith(key)) {
                matchedPhrase = key;
                break;
            }
        }

        if (matchedPhrase) {
            units.push(matchedPhrase);
            remaining = remaining.slice(matchedPhrase.length);
        } else {
            const m = remaining.match(/^\S+/);
            if (!m) break;
            units.push(m[0]);
            remaining = remaining.slice(m[0].length);
        }
    }

    // --- 2. Expand each unit to a synonym group ---
    return units.map(unit => {
        const group = new Set([unit]);
        for (const [key, syns] of Object.entries(WATCH_SYNONYMS)) {
            if (key === unit || syns.includes(unit)) {
                group.add(key);
                syns.forEach(s => group.add(s));
            } else if (!unit.includes(' ') && !key.includes(' ')
                       && (key.includes(unit) || unit.includes(key))) {
                group.add(key);
                syns.forEach(s => group.add(s));
            }
        }
        return Array.from(group).filter(Boolean);
    });
}

function matchesWatch(texts, tokensExpanded) {
    if (!tokensExpanded || tokensExpanded.length === 0) return true;
    // AND across token groups: every group must have at least one match
    return tokensExpanded.every(group => {
        return group.some(term => {
            return texts.some(text => text && text.includes(term));
        });
    });
}


// === CB22 STUDY MODE ===
const CB22_WORKFLOW_STEPS = {
  'WF-01': { name: 'Inspeksi cakupan', desc: 'Tinjau profil cakupan dan keterbatasan skenario.', views: ['Issue Desk'] },
  'WF-02': { name: 'Tinjau sinyal yang diterima', desc: 'Pastikan hanya bukti yang memenuhi keputusan hak digunakan.', views: ['Issue Desk', 'Foresight'] },
  'WF-03': { name: 'Inspeksi Issue persisten', desc: 'Tinjau identitas dan versi Issue tanpa membuat identitas otomatis.', views: ['Issue Desk'] },
  'WF-04': { name: 'Bukti dan kontradiksi', desc: 'Periksa dukungan, kontradiksi, konteks, dan pengecualian secara terpisah.', views: ['Issue Workspace (Pemeriksaan Bukti)'] },
  'WF-05': { name: 'Relevansi geografis', desc: 'Periksa seluruh dimensi geografi tanpa menggabungkannya.', views: ['Issue Workspace'] },
  'WF-06': { name: 'Ketidakpastian', desc: 'Catat ketidakpastian, gap bukti, dan keterbatasan.', views: ['Issue Workspace'] },
  'WF-07': { name: 'Trajektori', desc: 'Periksa setiap dimensi trajektori secara terpisah.', views: ['Foresight & Risiko'] },
  'WF-08': { name: 'Koreksi berotoritas', desc: 'Pastikan koreksi memerlukan kewenangan manusia eksplisit.', views: ['Developer / Sistem'] },
  'WF-09': { name: 'Prioritisasi kandidat', desc: 'Gunakan materialitas terbatas, bukan perhatian semata.', views: ['Foresight & Risiko'] },
  'WF-10': { name: 'Brief berbasis bukti', desc: 'Pastikan setiap pernyataan terhubung ke bukti yang diterima.', views: ['Dokumen Brief'] },
  'WF-11': { name: 'Penelusuran historis', desc: 'Periksa keadaan pada cutoff waktu eksplisit.', views: ['Issue Workspace'] }
};
const CB22_STEP_ORDER = Object.keys(CB22_WORKFLOW_STEPS);

const cb22State = {
  sessionId: null,
  analystId: null,
  currentStepIdx: 0,
  lastResponse: null
};

function _cb22UpdateDebug(data) {
  const el = document.getElementById('cb22Debug');
  if (el) el.textContent = JSON.stringify(data, null, 2);
  cb22State.lastResponse = data;
}

function _cb22ShowView(which) {
  const setup = document.getElementById('cb22Setup');
  const progress = document.getElementById('cb22Progress');
  const complete = document.getElementById('cb22Complete');
  if (!setup || !progress || !complete) return;
  setup.hidden = which !== 'setup';
  progress.hidden = which !== 'progress';
  complete.hidden = which !== 'complete';
}

function _cb22RenderStep(idx) {
  if (idx >= CB22_STEP_ORDER.length) return;
  const code = CB22_STEP_ORDER[idx];
  const step = CB22_WORKFLOW_STEPS[code];

  document.getElementById('cb22StepCounter').textContent = `${idx + 1} / 11`;
  document.getElementById('cb22StepName').textContent = `${code} — ${step.name}`;
  document.getElementById('cb22StepDesc').textContent = step.desc;
  document.getElementById('cb22ProgressBar').style.width = `${((idx) / 11) * 100}%`;
  document.getElementById('cb22StepViews').textContent = step.views.join(', ');
}

async function cb22StartSession() {
  const analystId = document.getElementById('cb22AnalystId').value.trim();
  if (!/^P-0\d{2}$/.test(analystId)) {
    alert('Participant code harus format P-001 s/d P-099 (pseudonym).');
    return;
  }

  try {
    const token = await getToken();
    const res = await fetch('/api/demo/start', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-SIFI-Token': token},
      body: JSON.stringify({analyst_id: analystId})
    });
    const data = await res.json();
    _cb22UpdateDebug(data);

    if (!res.ok) throw new Error(data.error || data.message || 'Gagal memulai sesi');

    const sessionId = data.session_id || data.session?.session_id || data.session?.sessionId || data.sessionId;
    if (!sessionId) throw new Error('Response tidak mengandung session_id. Lihat Debug.');

    cb22State.sessionId = sessionId;
    cb22State.analystId = analystId;
    cb22State.currentStepIdx = 0;

    document.getElementById('cb22SessionId').textContent = sessionId;
    _cb22ShowView('progress');
    _cb22RenderStep(0);
  } catch (e) {
    alert('Gagal: ' + e.message);
  }
}

async function cb22AdvanceSession() {
  if (!cb22State.sessionId) return;
  const note = document.getElementById('cb22Note').value;

  try {
    const token = await getToken();
    const res = await fetch('/api/demo/advance', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-SIFI-Token': token},
      body: JSON.stringify({
        session_id: cb22State.sessionId,
        analyst_id: cb22State.analystId,
        note: note || ''
      })
    });
    const data = await res.json();
    _cb22UpdateDebug(data);

    if (!res.ok) throw new Error(data.error || data.message || 'Gagal advance');

    const session = data.session || data;
    // next_step ada di TOP-LEVEL response, bukan di dalam session
    const nextStep = data.next_step ?? session.next_step;
    // Completion eksplisit dari session.completed_at
    const isComplete = Boolean(session.completed_at || data.completed_at);
    // brief ada di top-level `data.brief` (objek lengkap) saat complete
    const briefId =
      (data.brief && data.brief.brief_id) ||
      data.brief_id ||
      session.brief_id ||
      null;

    document.getElementById('cb22Note').value = '';

    if (isComplete) {
      // Brief ID: jika server tidak mengirim, prediksi berdasarkan konvensi
      let resolvedBriefId = briefId;
      if (!resolvedBriefId && cb22State.sessionId) {
        resolvedBriefId = 'BRIEF-' + cb22State.sessionId;
      }
      document.getElementById('cb22FinalSession').textContent = cb22State.sessionId;
      document.getElementById('cb22FinalBrief').textContent = resolvedBriefId || '(tidak ditemukan — cek /api/session)';
      document.getElementById('cb22InspectCmd').textContent =
        `curl -s "http://127.0.0.1:8765/api/session?id=${cb22State.sessionId}" | python3 -m json.tool`;
      _cb22ShowView('complete');
      return;
    }

    if (!nextStep) {
      alert('Server tidak mengirim next_step tetapi sesi belum complete. Cek Debug.');
      return;
    }

    const idx = CB22_STEP_ORDER.indexOf(nextStep);
    cb22State.currentStepIdx = idx >= 0 ? idx : cb22State.currentStepIdx + 1;
    _cb22RenderStep(cb22State.currentStepIdx);
  } catch (e) {
    alert('Gagal advance: ' + e.message);
  }
}

function cb22Reset() {
  cb22State.sessionId = null;
  cb22State.analystId = null;
  cb22State.currentStepIdx = 0;
  cb22State.lastResponse = null;
  const note = document.getElementById('cb22Note');
  const debug = document.getElementById('cb22Debug');
  if (note) note.value = '';
  if (debug) debug.textContent = '';
  _cb22ShowView('setup');
}

function cb22Abort() {
  if (!confirm('Batalkan sesi ini? Sesi yang dibatalkan tidak dapat dilanjutkan.')) return;
  cb22Reset();
}

document.addEventListener('DOMContentLoaded', () => {
  const startBtn = document.getElementById('cb22StartBtn');
  const advanceBtn = document.getElementById('cb22AdvanceBtn');
  const resetBtn = document.getElementById('cb22ResetBtn');
  const abortBtn = document.getElementById('cb22AbortBtn');

  if (startBtn) startBtn.addEventListener('click', cb22StartSession);
  if (advanceBtn) advanceBtn.addEventListener('click', cb22AdvanceSession);
  if (resetBtn) resetBtn.addEventListener('click', cb22Reset);
  if (abortBtn) abortBtn.addEventListener('click', cb22Abort);
});
