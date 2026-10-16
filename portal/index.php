<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$user = currentUser();
$today   = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ตารางนัด — <?= htmlspecialchars($user['display_name']) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:       #0f2447;
    --navy-mid:   #1a3a6b;
    --teal:       #0e8c7e;
    --teal-light: #12a899;
    --slate:      #4a5f7a;
    --muted:      #8a9bb0;
    --silver:     #e8edf3;
    --bg:         #f2f5f9;
    --white:      #ffffff;
    --row-alt:    #f8fafc;
    --font-th:    'Sarabun', 'Noto Sans Thai', sans-serif;
  }

  @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap');

  body {
    font-family: var(--font-th);
    background: var(--bg);
    color: var(--navy);
    min-height: 100vh;
  }

  /* ─── Top Bar ───────────────────────────────────────── */
  .topbar {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
    padding: 0 32px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.25);
  }

  .topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .topbar-left svg {
    width: 22px; height: 22px;
    fill: var(--teal-light);
    flex-shrink: 0;
  }

  .topbar-title {
    color: var(--white);
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: .02em;
  }

  .topbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
  }

  .doctor-badge {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .doctor-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(14,140,126,.25);
    border: 1.5px solid rgba(14,140,126,.5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--teal-light);
    font-size: .8rem;
    font-weight: 600;
  }

  .doctor-name {
    color: var(--white);
    font-size: .9rem;
    font-weight: 500;
  }

  .btn-logout {
    padding: 6px 16px;
    border: 1.5px solid rgba(255,255,255,.25);
    border-radius: 20px;
    background: transparent;
    color: rgba(255,255,255,.7);
    font-family: var(--font-th);
    font-size: .8rem;
    cursor: pointer;
    text-decoration: none;
    transition: all .2s;
  }

  .btn-logout:hover {
    border-color: rgba(255,255,255,.6);
    color: var(--white);
    background: rgba(255,255,255,.08);
  }

  /* ─── Main Content ──────────────────────────────────── */
  .container {
    max-width: 900px;
    margin: 0 auto;
    padding: 32px 20px 60px;
  }

  /* ─── Date Selector Card ────────────────────────────── */
  .date-card {
    background: var(--white);
    border-radius: 12px;
    padding: 24px 28px;
    box-shadow: 0 2px 12px rgba(15,36,71,.07);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
  }

  .date-card label {
    font-size: .82rem;
    font-weight: 500;
    color: var(--slate);
    letter-spacing: .04em;
    white-space: nowrap;
  }

  .date-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .btn-nav {
    width: 36px; height: 36px;
    border: 1.5px solid var(--silver);
    border-radius: 8px;
    background: var(--white);
    color: var(--slate);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all .15s;
  }

  .btn-nav:hover:not(:disabled) {
    border-color: var(--teal);
    color: var(--teal);
  }

  .btn-nav:disabled { opacity: .35; cursor: default; }

  input[type="date"] {
    padding: 8px 14px;
    border: 1.5px solid var(--silver);
    border-radius: 8px;
    font-family: var(--font-th);
    font-size: .95rem;
    color: var(--navy);
    background: #f8fafc;
    outline: none;
    transition: border-color .2s;
    cursor: pointer;
  }

  input[type="date"]:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(14,140,126,.1);
  }

  .btn-today {
    padding: 8px 16px;
    border: 1.5px solid var(--teal);
    border-radius: 8px;
    background: var(--white);
    color: var(--teal);
    font-family: var(--font-th);
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all .15s;
  }

  .btn-today:hover {
    background: var(--teal);
    color: var(--white);
  }

  /* ─── Updated At ────────────────────────────────────── */
  .update-info {
    margin-left: auto;
    font-size: .78rem;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }

  .update-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--teal-light);
    flex-shrink: 0;
  }

  /* ─── Appointment Table Card ────────────────────────── */
  .apt-card {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(15,36,71,.07);
    overflow: hidden;
  }

  .apt-header {
    padding: 20px 28px;
    border-bottom: 1px solid var(--silver);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .apt-date-display {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--navy);
  }

  .apt-count {
    font-size: .82rem;
    color: var(--white);
    background: var(--teal);
    padding: 3px 12px;
    border-radius: 20px;
    font-weight: 500;
  }

  .apt-count.zero { background: var(--muted); }

  /* Loading / Empty */
  .state-box {
    padding: 64px 28px;
    text-align: center;
    color: var(--muted);
  }

  .state-box svg {
    width: 48px; height: 48px;
    fill: var(--silver);
    margin-bottom: 16px;
  }

  .state-box p { font-size: .95rem; }

  .spinner {
    width: 36px; height: 36px;
    border: 3px solid var(--silver);
    border-top-color: var(--teal);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin: 0 auto 16px;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  /* Table */
  .table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  table {
    width: 100%;
    min-width: 560px;
    border-collapse: collapse;
  }

  thead th {
    background: #f2f5f9;
    padding: 11px 20px;
    font-size: .78rem;
    font-weight: 600;
    color: var(--slate);
    text-align: left;
    letter-spacing: .06em;
    text-transform: uppercase;
    border-bottom: 1px solid var(--silver);
  }

  tbody tr {
    border-bottom: 1px solid var(--silver);
    transition: background .12s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:nth-child(even) { background: var(--row-alt); }
  tbody tr:hover { background: #eef6f5; }

  td {
    padding: 14px 20px;
    font-size: .9rem;
    vertical-align: middle;
  }

  .time-cell {
    font-weight: 600;
    color: var(--teal);
    white-space: nowrap;
    font-size: .95rem;
    font-variant-numeric: tabular-nums;
    letter-spacing: .02em;
  }

  .row-num {
    color: var(--muted);
    font-size: .8rem;
    text-align: center;
    width: 40px;
  }

  .patient-name { font-weight: 500; }

  .hn-badge {
    display: inline-block;
    font-size: .72rem;
    padding: 2px 8px;
    border-radius: 4px;
    background: var(--silver);
    color: var(--slate);
    font-variant-numeric: tabular-nums;
    margin-left: 8px;
  }

  .tel-link {
    color: var(--slate);
    text-decoration: none;
    font-size: .85rem;
  }

  .tel-link:hover { color: var(--teal); }

  .note-cell {
    color: var(--slate);
    font-size: .85rem;
    font-style: italic;
  }

  /* ─── Mobile ─────────────────────────────────────────── */
  @media (max-width: 640px) {
    .topbar { padding: 0 14px; }
    .topbar-title { font-size: .85rem; }
    .doctor-name { display: none; }
    .container { padding: 20px 12px 40px; }
    .date-card { padding: 18px 16px; gap: 14px; }
    .apt-header { padding: 16px 18px; }
    .update-info { margin-left: 0; width: 100%; }
  }
</style>
</head>
<body>

<!-- Top Bar -->
<nav class="topbar">
  <div class="topbar-left">
    <svg viewBox="0 0 24 24"><path d="M12 2C9.5 2 7.5 3.5 6.5 5.5C5.5 3.5 3.5 2 2 3C.5 4 1 6.5 1.5 8.5C2 10.5 2 12 2 14C2 16 3 22 5 22C7 22 7 19 8 17C9 15 11 14 12 14C13 14 15 15 16 17C17 19 17 22 19 22C21 22 22 16 22 14C22 12 22 10.5 22.5 8.5C23 6.5 23.5 4 22 3C20.5 2 18.5 3.5 17.5 5.5C16.5 3.5 14.5 2 12 2Z"/></svg>
    <span class="topbar-title">คลินิกทันตกรรม — ตารางนัดหมาย</span>
  </div>
  <div class="topbar-right">
    <div class="doctor-badge">
      <div class="doctor-avatar" id="avatar-initials">—</div>
      <span class="doctor-name"><?= htmlspecialchars($user['display_name']) ?></span>
    </div>
    <a href="logout.php" class="btn-logout">ออกจากระบบ</a>
  </div>
</nav>

<!-- Main -->
<main class="container">

  <!-- Date Selector -->
  <div class="date-card">
    <label>เลือกวันที่</label>
    <div class="date-controls">
      <button class="btn-nav" id="btn-prev" title="วันก่อนหน้า">&#8592;</button>
      <input type="date"
             id="date-picker"
             min="<?= $today ?>"
             max="<?= $maxDate ?>"
             value="<?= $today ?>">
      <button class="btn-nav" id="btn-next" title="วันถัดไป">&#8594;</button>
      <button class="btn-today" id="btn-today">วันนี้</button>
    </div>
    <div class="update-info" id="update-info" style="display:none">
      <span class="update-dot"></span>
      <span id="update-text"></span>
    </div>
  </div>

  <!-- Appointment Card -->
  <div class="apt-card">
    <div class="apt-header">
      <span class="apt-date-display" id="apt-date-display">—</span>
      <span class="apt-count zero" id="apt-count">0 นัด</span>
    </div>

    <div id="apt-body">
      <div class="state-box"><div class="spinner"></div><p>กำลังโหลด...</p></div>
    </div>
  </div>

</main>

<script>
const TODAY    = '<?= $today ?>';
const MAX_DATE = '<?= $maxDate ?>';
const DOCTOR   = '<?= htmlspecialchars($user['display_name']) ?>';

// Set avatar initials
const parts = DOCTOR.split(/\s+/);
document.getElementById('avatar-initials').textContent =
  parts.length >= 2 ? (parts[0].charAt(0) + parts[1].charAt(0)) : DOCTOR.charAt(0);

// Thai date formatter
const thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
const thaiDays   = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];

function formatThaiDate(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  const dow = new Date(iso).getDay();
  return `วัน${thaiDays[dow]}ที่ ${d} ${thaiMonths[m]} ${y + 543}`;
}

function formatThaiDateTime(dtStr) {
  if (!dtStr) return '';
  const dt = new Date(dtStr);
  const d  = dt.getDate(), m = dt.getMonth() + 1, y = dt.getFullYear() + 543;
  const hh = String(dt.getHours()).padStart(2,'0'), mm = String(dt.getMinutes()).padStart(2,'0');
  return `${d} ${thaiMonths[m]} ${y} เวลา ${hh}:${mm} น.`;
}

// ─── Load appointments ───────────────────────────────
async function load(date) {
  document.getElementById('apt-body').innerHTML =
    '<div class="state-box"><div class="spinner"></div><p>กำลังโหลด...</p></div>';
  document.getElementById('apt-date-display').textContent = formatThaiDate(date);
  document.getElementById('update-info').style.display = 'none';

  try {
    const res  = await fetch(`api/appointments.php?date=${date}`);
    const data = await res.json();

    if (data.error) throw new Error(data.error);

    // Update info
    if (data.generated_at) {
      document.getElementById('update-text').textContent =
        'ข้อมูล ณ ' + formatThaiDateTime(data.generated_at);
      document.getElementById('update-info').style.display = 'flex';
    }

    const list = data.appointments || [];
    const countEl = document.getElementById('apt-count');
    countEl.textContent = list.length + ' นัด';
    countEl.className = 'apt-count' + (list.length === 0 ? ' zero' : '');

    if (list.length === 0) {
      const msg = data.message || 'ไม่มีนัดในวันนี้';
      document.getElementById('apt-body').innerHTML = `
        <div class="state-box">
          <svg viewBox="0 0 24 24"><path d="M19 3H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11H6v-2h12v2zm0-4H6V8h12v2z"/></svg>
          <p>${msg}</p>
        </div>`;
      return;
    }

    let rows = list.map((a, i) => {
      const tel = a.hometel
        ? `<a class="tel-link" href="tel:${a.hometel}">${a.hometel}</a>`
        : '<span style="color:var(--silver)">—</span>';
      const note = a.note
        ? `<span class="note-cell">${escHtml(a.note)}</span>`
        : '';
      return `
        <tr>
          <td class="row-num">${i + 1}</td>
          <td class="time-cell">${escHtml(a.nexttime)}</td>
          <td><span class="patient-name">${escHtml(a.patient_name)}</span><span class="hn-badge">${escHtml(a.hn)}</span></td>
          <td>${tel}</td>
          <td>${note}</td>
        </tr>`;
    }).join('');

    document.getElementById('apt-body').innerHTML = `
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th style="width:80px">เวลา</th>
              <th>ชื่อ-สกุล</th>
              <th style="width:130px">โทรศัพท์</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

  } catch(e) {
    document.getElementById('apt-body').innerHTML = `
      <div class="state-box"><p>เกิดข้อผิดพลาด: ${escHtml(e.message)}</p></div>`;
  }
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ─── Controls ────────────────────────────────────────
const picker = document.getElementById('date-picker');
const btnPrev = document.getElementById('btn-prev');
const btnNext = document.getElementById('btn-next');

function updateNav() {
  btnPrev.disabled = picker.value <= TODAY;
  btnNext.disabled = picker.value >= MAX_DATE;
}

picker.addEventListener('change', () => { updateNav(); load(picker.value); });

btnPrev.addEventListener('click', () => {
  const d = new Date(picker.value);
  d.setDate(d.getDate() - 1);
  picker.value = d.toISOString().slice(0,10);
  updateNav(); load(picker.value);
});

btnNext.addEventListener('click', () => {
  const d = new Date(picker.value);
  d.setDate(d.getDate() + 1);
  picker.value = d.toISOString().slice(0,10);
  updateNav(); load(picker.value);
});

document.getElementById('btn-today').addEventListener('click', () => {
  picker.value = TODAY;
  updateNav(); load(TODAY);
});

// Init
updateNav();
load(TODAY);
</script>
</body>
</html>
