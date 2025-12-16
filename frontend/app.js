// Cấu hình đường dẫn API theo cách deploy của bạn
// Nếu upload cả 2 folder dưới public_html/: dùng "../backend/api.php"
// Nếu frontend nằm root và backend ở /backend: dùng "./backend/api.php"
const API_URL = "../backend/api.php";

// Đặt giống với backend/config.php
const API_KEY = "DEMO_KEY_123456";

const $ = (id) => document.getElementById(id);
const logEl = $("log");
const listEl = $("list");
const statsEl = $("stats");
const apiStatusEl = $("apiStatus");

let allTasks = [];

function log(obj) {
  logEl.textContent = typeof obj === "string" ? obj : JSON.stringify(obj, null, 2);
}

async function api(action, method = "GET", body = null) {
  const url = `${API_URL}?action=${encodeURIComponent(action)}`;
  const opt = {
    method,
    headers: {
      "X-API-KEY": API_KEY,
      "Content-Type": "application/json",
    },
  };
  if (method !== "GET" && body) opt.body = JSON.stringify(body);

  const res = await fetch(url, opt);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);
  return data;
}

async function ping() {
  try {
    const res = await fetch(`${API_URL}?action=ping`);
    const data = await res.json();
    apiStatusEl.textContent = "API: OK";
    log({ ping: data });
  } catch (e) {
    apiStatusEl.textContent = "API: ERROR";
    log("Ping error: " + e.message);
  }
}

function renderStats(s) {
  statsEl.innerHTML = `
    <div class="kpi"><div>Total</div><div><b>${s.total}</b></div></div>
    <div class="kpi"><div>Done</div><div><b>${s.done}</b></div></div>
    <div class="kpi"><div>Todo</div><div><b>${s.todo}</b></div></div>
    <div class="kpi"><div>High</div><div><b>${s.high_priority}</b></div></div>
  `;
}

function filteredTasks() {
  const q = $("search").value.trim().toLowerCase();
  const fd = $("filterDone").value;

  return allTasks.filter(t => {
    const matchQ = t.title.toLowerCase().includes(q);
    const matchDone =
      fd === "all" ? true :
      fd === "done" ? t.done :
      !t.done;
    return matchQ && matchDone;
  });
}

function renderList() {
  const tasks = filteredTasks();
  if (tasks.length === 0) {
    listEl.innerHTML = `<div class="muted">Không có task phù hợp.</div>`;
    return;
  }

  listEl.innerHTML = tasks.map(t => `
    <div class="item">
      <div>
        <div>
          <b>${t.done ? "✅" : "⬜"} ${escapeHtml(t.title)}</b>
          <span class="badge">${t.priority}</span>
        </div>
        <div class="meta">#${t.id} • created ${t.created_at}</div>
      </div>
      <div class="actions">
        <button class="secondary" data-act="toggle" data-id="${t.id}">${t.done ? "Undo" : "Done"}</button>
        <button data-act="edit" data-id="${t.id}">Edit</button>
        <button class="danger" data-act="delete" data-id="${t.id}">Delete</button>
      </div>
    </div>
  `).join("");
}

function escapeHtml(s) {
  return s.replace(/[&<>"']/g, (c) => ({
    "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
  }[c]));
}

async function refresh() {
  try {
    const [listRes, statsRes] = await Promise.all([api("list"), api("stats")]);
    allTasks = listRes.tasks || [];
    renderStats(statsRes.stats);
    renderList();
    log({ list: { count: allTasks.length }, stats: statsRes.stats });
  } catch (e) {
    log("Refresh error: " + e.message);
  }
}

$("createForm").addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const f = ev.target;
  const title = f.title.value.trim();
  const priority = f.priority.value;
  try {
    const res = await api("create", "POST", { title, priority });
    f.reset();
    log(res);
    await refresh();
  } catch (e) {
    log("Create error: " + e.message);
  }
});

$("btnRefresh").addEventListener("click", refresh);
$("search").addEventListener("input", renderList);
$("filterDone").addEventListener("change", renderList);

listEl.addEventListener("click", async (ev) => {
  const btn = ev.target.closest("button");
  if (!btn) return;
  const id = Number(btn.dataset.id);
  const act = btn.dataset.act;

  try {
    if (act === "toggle") {
      const res = await api("toggle", "POST", { id });
      log(res);
      await refresh();
    }
    if (act === "delete") {
      if (!confirm("Xóa task #" + id + " ?")) return;
      const res = await api("delete", "POST", { id });
      log(res);
      await refresh();
    }
    if (act === "edit") {
      openEdit(id);
    }
  } catch (e) {
    log(`${act} error: ` + e.message);
  }
});

const dlg = $("editDlg");
const editForm = $("editForm");

function openEdit(id) {
  const t = allTasks.find(x => x.id === id);
  if (!t) return;
  editForm.id.value = String(t.id);
  editForm.title.value = t.title;
  editForm.priority.value = t.priority;
  dlg.showModal();
}

editForm.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const id = Number(editForm.id.value);
  const title = editForm.title.value.trim();
  const priority = editForm.priority.value;

  try {
    const res = await api("update", "POST", { id, title, priority });
    log(res);
    dlg.close();
    await refresh();
  } catch (e) {
    log("Update error: " + e.message);
  }
});

// Init
(async function init() {
  await ping();
  await refresh();
})();
