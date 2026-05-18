<?php
// ============================================================
//  Ame Co. Workspace — dashboard.php
//  Protected page. Requires an active session.
//  Handles task CRUD via POST actions:
//    action=add    → insert task
//    action=edit   → update task
//    action=delete → delete task
//    action=toggle → flip done status
// ============================================================
session_start();

// Guard: redirect unauthenticated users to login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$toast   = '';   // message passed to the JS toast system

// ── POST handler (CRUD) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title    = trim($_POST['title']    ?? '');
        $desc     = trim($_POST['desc']     ?? '');
        $priority = $_POST['priority']      ?? 'medium';
        $due      = $_POST['due']           ?? null;

        if (!empty($title)) {
            $stmt = $db->prepare(
                'INSERT INTO tasks (user_id, title, description, priority, due_date, done, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())'
            );
            $stmt->execute([$user_id, $title, $desc, $priority, $due ?: null]);
            $toast = 'Task added!|success';
        }

    } elseif ($action === 'edit') {
        $id       = (int) ($_POST['id']       ?? 0);
        $title    = trim($_POST['title']      ?? '');
        $desc     = trim($_POST['desc']       ?? '');
        $priority = $_POST['priority']        ?? 'medium';
        $due      = $_POST['due']             ?? null;

        if ($id && !empty($title)) {
            $stmt = $db->prepare(
                'UPDATE tasks SET title=?, description=?, priority=?, due_date=?
                 WHERE id=? AND user_id=?'
            );
            $stmt->execute([$title, $desc, $priority, $due ?: null, $id, $user_id]);
            $toast = 'Task updated.|success';
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare('DELETE FROM tasks WHERE id=? AND user_id=?');
            $stmt->execute([$id, $user_id]);
            $toast = 'Task deleted.|error';
        }

    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare(
                'UPDATE tasks SET done = NOT done WHERE id=? AND user_id=?'
            );
            $stmt->execute([$id, $user_id]);

            // Read new state to show correct toast
            $row = $db->prepare('SELECT done FROM tasks WHERE id=?');
            $row->execute([$id]);
            $done = (bool) ($row->fetchColumn());
            $toast = ($done ? 'Task marked complete.|success' : 'Task reopened.|success');
        }
    }

    // PRG — redirect to prevent re-submission on refresh
    $qs = $toast ? '?toast=' . urlencode($toast) : '';
    header('Location: dashboard.php' . $qs);
    exit;
}

// Carry toast from redirect
if (!empty($_GET['toast'])) {
    $toast = $_GET['toast'];
}

// ── Fetch tasks for this user ─────────────────────────────
$stmt = $db->prepare(
    'SELECT id, title, description AS `desc`, priority, due_date AS `due`, done
     FROM tasks
     WHERE user_id = ?
     ORDER BY done ASC, created_at DESC'
);
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────
$total   = count($tasks);
$done_c  = count(array_filter($tasks, fn($t) => $t['done']));
$pending = $total - $done_c;
$high    = count(array_filter($tasks, fn($t) => $t['priority'] === 'high' && !$t['done']));

// ── Sidebar counts ────────────────────────────────────────
$sb = [
    'all'    => $total,
    'pending'=> $pending,
    'done'   => $done_c,
    'high'   => count(array_filter($tasks, fn($t) => $t['priority'] === 'high')),
    'medium' => count(array_filter($tasks, fn($t) => $t['priority'] === 'medium')),
    'low'    => count(array_filter($tasks, fn($t) => $t['priority'] === 'low')),
];

// Helper: format ISO date
function fmtDate(?string $iso): string {
    if (!$iso) return '';
    return (new DateTime($iso))->format('M j, Y');
}

function isOverdue(?string $iso, bool $done): bool {
    if (!$iso || $done) return false;
    return new DateTime($iso) < new DateTime('today');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Co. Workspace — Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-body">

<?php include 'icons.php'; ?>

<!-- ── Top nav ── -->
<nav class="app-nav">
  <a href="dashboard.php" class="app-nav-brand">
    <div class="mark"><span>A</span></div>
    Ame Co.
  </a>

  <div class="nav-user-info">
    <span class="nav-role-badge"><?= htmlspecialchars(ucfirst($_SESSION['user_role'])) ?></span>
    <span class="nav-user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    <div class="nav-avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1) . (strpos($_SESSION['user_name'],' ')!==false ? substr($_SESSION['user_name'], strpos($_SESSION['user_name'],' ')+1, 1) : '')) ?></div>
  </div>

  <a href="logout.php" class="nav-logout">
    <svg><use href="#i-logout"/></svg>
    Log out
  </a>
</nav>

<!-- ── App body ── -->
<div class="app-body">

  <!-- Sidebar -->
  <aside class="sidebar">
    <span class="sidebar-label">Menu</span>

    <button class="sidebar-link active" onclick="setView('all',this)" type="button">
      <svg><use href="#i-grid"/></svg>
      All Tasks
      <span class="sidebar-badge"><?= $sb['all'] ?></span>
    </button>

    <button class="sidebar-link" onclick="setView('pending',this)" type="button">
      <svg><use href="#i-tasks"/></svg>
      Pending
      <span class="sidebar-badge"><?= $sb['pending'] ?></span>
    </button>

    <button class="sidebar-link" onclick="setView('done',this)" type="button">
      <svg><use href="#i-done-tasks"/></svg>
      Completed
      <span class="sidebar-badge"><?= $sb['done'] ?></span>
    </button>

    <span class="sidebar-label">Priority</span>

    <button class="sidebar-link" onclick="setView('high',this)" type="button">
      <svg><use href="#i-flag"/></svg>
      High Priority
      <span class="sidebar-badge"><?= $sb['high'] ?></span>
    </button>

    <button class="sidebar-link" onclick="setView('medium',this)" type="button">
      <svg><use href="#i-flag"/></svg>
      Medium
      <span class="sidebar-badge"><?= $sb['medium'] ?></span>
    </button>

    <button class="sidebar-link" onclick="setView('low',this)" type="button">
      <svg><use href="#i-flag"/></svg>
      Low
      <span class="sidebar-badge"><?= $sb['low'] ?></span>
    </button>
  </aside>

  <!-- Main content -->
  <main class="main-content">

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h2 id="view-title">All Tasks</h2>
        <p id="view-sub">Manage and track everything in one place.</p>
      </div>
      <button class="btn-accent" onclick="openAddModal()" type="button">
        <svg style="width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round"><use href="#i-plus"/></svg>
        Add Task
      </button>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-label">Total Tasks</div>
        <div class="stat-value"><?= $total ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Completed</div>
        <div class="stat-value green"><?= $done_c ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value amber"><?= $pending ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">High Priority</div>
        <div class="stat-value accent"><?= $high ?></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-search">
        <svg><use href="#i-search"/></svg>
        <input type="text" id="search-input" placeholder="Search tasks…" oninput="filterTasks()" />
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all"     onclick="setTabFilter(this)">All</button>
        <button class="filter-tab"        data-filter="pending" onclick="setTabFilter(this)">Pending</button>
        <button class="filter-tab"        data-filter="done"    onclick="setTabFilter(this)">Done</button>
      </div>
    </div>

    <!-- Task list -->
    <div class="task-list" id="task-list">
      <?php foreach ($tasks as $t):
        $overdue = isOverdue($t['due'], (bool)$t['done']);
        $done    = (bool)$t['done'];
      ?>
      <div class="task-card<?= $done ? ' done' : '' ?>"
           data-id="<?= $t['id'] ?>"
           data-done="<?= $done ? '1' : '0' ?>"
           data-priority="<?= htmlspecialchars($t['priority']) ?>">

        <!-- Toggle done (POST form) -->
        <form method="POST" action="dashboard.php" style="display:contents">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id"     value="<?= $t['id'] ?>">
          <button type="submit"
                  class="task-check<?= $done ? ' checked' : '' ?>"
                  aria-label="Mark complete">
            <?php if ($done): ?><svg><use href="#i-check"/></svg><?php endif; ?>
          </button>
        </form>

        <div class="task-body">
          <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
          <?php if ($t['desc']): ?>
          <div class="task-desc"><?= htmlspecialchars($t['desc']) ?></div>
          <?php endif; ?>
          <div class="task-meta">
            <span class="badge <?= htmlspecialchars($t['priority']) ?>">
              <svg style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.5"><use href="#i-flag"/></svg>
              <?= ucfirst(htmlspecialchars($t['priority'])) ?>
            </span>
            <?php if ($done): ?>
            <span class="badge done">
              <svg style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.5"><use href="#i-check"/></svg>
              Done
            </span>
            <?php endif; ?>
            <?php if ($t['due']): ?>
            <span class="task-due<?= $overdue ? ' overdue' : '' ?>">
              <svg><use href="#i-clock"/></svg>
              <?= fmtDate($t['due']) ?><?= $overdue ? ' · Overdue' : '' ?>
            </span>
            <?php endif; ?>
          </div>
        </div>

        <div class="task-actions">
          <!-- Edit button — opens modal pre-filled via JS -->
          <button class="btn-icon"
                  onclick="openEditModal(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode([
                    'title'    => $t['title'],
                    'desc'     => $t['desc'],
                    'priority' => $t['priority'],
                    'due'      => $t['due'] ?? '',
                  ]), ENT_QUOTES) ?>)"
                  title="Edit"
                  type="button">
            <svg><use href="#i-edit"/></svg>
          </button>
          <!-- Delete button — opens confirm modal -->
          <button class="btn-icon"
                  onclick="openDeleteModal(<?= $t['id'] ?>, <?= htmlspecialchars(json_encode($t['title']), ENT_QUOTES) ?>)"
                  title="Delete"
                  type="button"
                  style="color:var(--red);border-color:rgba(220,38,38,.2)">
            <svg><use href="#i-trash"/></svg>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Empty state -->
    <div class="empty-state" id="empty-state" <?= $total ? 'style="display:none"' : '' ?>>
      <svg><use href="#i-tasks"/></svg>
      <h3>No tasks here yet</h3>
      <p>Click <strong>Add Task</strong> to get started.</p>
    </div>

  </main>
</div><!-- /.app-body -->


<!-- ════════════════════════════════════════
     MODAL · ADD / EDIT TASK
════════════════════════════════════════ -->
<div class="modal-backdrop" id="task-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">

    <div class="modal-header">
      <h3 id="modal-title">Add Task</h3>
      <button class="modal-close" onclick="closeModal('task-modal')" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>

    <form method="POST" action="dashboard.php" id="task-form">
      <input type="hidden" name="action" id="form-action" value="add">
      <input type="hidden" name="id"     id="form-id"     value="">

      <div class="field">
        <label for="m-title">Task title <span style="color:var(--red)">*</span></label>
        <input type="text" id="m-title" name="title" placeholder="e.g. Fix the login bug" required />
        <div class="field-err" id="m-title-err">
          <svg><use href="#i-alert"/></svg><span>Title is required.</span>
        </div>
      </div>

      <div class="field">
        <label for="m-desc">Description</label>
        <textarea id="m-desc" name="desc" placeholder="Optional details…"></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field" style="margin-bottom:0">
          <label for="m-priority">Priority</label>
          <select id="m-priority" name="priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="field" style="margin-bottom:0">
          <label for="m-due">Due date</label>
          <input type="date" id="m-due" name="due" />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-ghost"  onclick="closeModal('task-modal')" type="button">Cancel</button>
        <button class="btn-accent" type="submit" id="modal-save-btn">Add Task</button>
      </div>
    </form>

  </div>
</div>


<!-- ════════════════════════════════════════
     MODAL · CONFIRM DELETE
════════════════════════════════════════ -->
<div class="modal-backdrop" id="delete-modal">
  <div class="modal" style="max-width:380px" role="dialog" aria-modal="true">

    <div class="modal-header">
      <h3>Delete Task</h3>
      <button class="modal-close" onclick="closeModal('delete-modal')" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>

    <div class="confirm-body">
      <div class="confirm-icon"><svg><use href="#i-warn"/></svg></div>
      <p>Are you sure you want to delete <strong id="del-task-name"></strong>? This cannot be undone.</p>
    </div>

    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id"     id="del-task-id" value="">
      <div class="modal-footer">
        <button class="btn-ghost"  onclick="closeModal('delete-modal')" type="button">Cancel</button>
        <button class="btn-danger" type="submit">
          <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><use href="#i-trash"/></svg>
          Delete
        </button>
      </div>
    </form>

  </div>
</div>


<!-- ════════════════════════════════════════
     TOAST CONTAINER
════════════════════════════════════════ -->
<div class="toast-container" id="toast-container"></div>


<!-- ════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════ -->
<script>
/* ── View meta ── */
const VIEW_META = {
  all:    { title: 'All Tasks',       sub: 'Manage and track everything in one place.' },
  pending:{ title: 'Pending',         sub: 'Tasks still in progress.' },
  done:   { title: 'Completed',       sub: "Tasks you've finished." },
  high:   { title: 'High Priority',   sub: 'Urgent tasks that need attention.' },
  medium: { title: 'Medium Priority', sub: 'Tasks with moderate urgency.' },
  low:    { title: 'Low Priority',    sub: 'Tasks that can wait a little longer.' },
};

let activeView   = 'all';
let activeFilter = 'all';

/* ── Sidebar view ── */
function setView(v, btn) {
  activeView = v;
  document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  const meta = VIEW_META[v] || VIEW_META.all;
  document.getElementById('view-title').textContent = meta.title;
  document.getElementById('view-sub').textContent   = meta.sub;
  filterTasks();
}

/* ── Tab filter ── */
function setTabFilter(btn) {
  document.querySelectorAll('.filter-tab').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = btn.dataset.filter;
  filterTasks();
}

/* ── Client-side filter (no page reload) ── */
function filterTasks() {
  const q = document.getElementById('search-input').value.toLowerCase();
  let visible = 0;

  document.querySelectorAll('.task-card').forEach(card => {
    const done     = card.dataset.done === '1';
    const priority = card.dataset.priority;
    const text     = card.textContent.toLowerCase();

    let show = true;

    // Sidebar view
    if (activeView === 'pending' && done)  show = false;
    if (activeView === 'done'    && !done) show = false;
    if (['high','medium','low'].includes(activeView) && priority !== activeView) show = false;

    // Tab filter
    if (activeFilter === 'pending' && done)  show = false;
    if (activeFilter === 'done'    && !done) show = false;

    // Search
    if (q && !text.includes(q)) show = false;

    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('empty-state').style.display = visible ? 'none' : 'block';
}

/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-backdrop').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

/* ── Add modal ── */
function openAddModal() {
  document.getElementById('form-action').value   = 'add';
  document.getElementById('form-id').value       = '';
  document.getElementById('m-title').value       = '';
  document.getElementById('m-desc').value        = '';
  document.getElementById('m-priority').value    = 'medium';
  document.getElementById('m-due').value         = '';
  document.getElementById('modal-title').textContent    = 'Add Task';
  document.getElementById('modal-save-btn').textContent = 'Add Task';
  document.getElementById('m-title-err').style.display  = 'none';
  openModal('task-modal');
}

/* ── Edit modal ── */
function openEditModal(id, data) {
  document.getElementById('form-action').value   = 'edit';
  document.getElementById('form-id').value       = id;
  document.getElementById('m-title').value       = data.title;
  document.getElementById('m-desc').value        = data.desc || '';
  document.getElementById('m-priority').value    = data.priority;
  document.getElementById('m-due').value         = data.due   || '';
  document.getElementById('modal-title').textContent    = 'Edit Task';
  document.getElementById('modal-save-btn').textContent = 'Save Changes';
  document.getElementById('m-title-err').style.display  = 'none';
  openModal('task-modal');
}

/* ── Client-side title validation ── */
document.getElementById('task-form').addEventListener('submit', function(e) {
  const title = document.getElementById('m-title').value.trim();
  if (!title) {
    e.preventDefault();
    document.getElementById('m-title').classList.add('err');
    document.getElementById('m-title-err').style.display = 'flex';
    document.getElementById('m-title').focus();
  }
});

/* ── Delete modal ── */
function openDeleteModal(id, name) {
  document.getElementById('del-task-id').value   = id;
  document.getElementById('del-task-name').textContent = '"' + name + '"';
  openModal('delete-modal');
}

/* ── Toast ── */
function toast(msg, type) {
  const icon = type === 'success' ? '#i-check-c' : type === 'error' ? '#i-warn' : '#i-alert';
  const el   = document.createElement('div');
  el.className = 'toast' + (type ? ' ' + type : '');
  el.innerHTML = `<svg><use href="${icon}"/></svg>${msg}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

/* ── Fire toast from PHP redirect param ── */
<?php if ($toast):
  [$tmsg, $ttype] = explode('|', $toast . '|');
?>
window.addEventListener('DOMContentLoaded', () => {
  toast(<?= json_encode($tmsg) ?>, <?= json_encode(trim($ttype)) ?>);
});
<?php endif; ?>
</script>

</body>
</html>
