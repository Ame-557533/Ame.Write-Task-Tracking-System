<?php
// ============================================================
//  Ame Writer — dashboard.php
//  Protected page. Shows writing projects.
//  Only the project owner can delete or mark complete.
//  Collaborators can view and edit but not delete/complete.
//  Non-members cannot open the project at all.
//
//  POST actions:
//    action=add_project    → create new project
//    action=edit_project   → update project details
//    action=delete_project → owner only
//    action=complete       → owner only, sets status=complete
//    action=reopen         → owner only, sets status=in_progress
//    action=add_collab     → owner adds collaborator by email
//    action=remove_collab  → owner removes collaborator
// ============================================================
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$toast   = '';

// ── POST handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add project ──────────────────────────────────────
    if ($action === 'add_project') {
        $title   = trim($_POST['title']   ?? '');
        $desc    = trim($_POST['desc']    ?? '');
        $type    = $_POST['type']         ?? 'other';
        $due     = $_POST['due']          ?? null;
        $is_solo = isset($_POST['is_solo']) ? 1 : 0;

        $allowed_types = ['essay','journal','speech','article','blog','report','other'];
        if (!in_array($type, $allowed_types)) $type = 'other';

        if (!empty($title)) {
            $stmt = $db->prepare(
                'INSERT INTO projects (owner_id, title, description, type, status, is_solo, due_date, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$user_id, $title, $desc, $type, 'draft', $is_solo, $due ?: null]);
            $toast = 'Project created!|success';
        }

    // ── Edit project ─────────────────────────────────────
    } elseif ($action === 'edit_project') {
        $id    = (int) ($_POST['id']    ?? 0);
        $title = trim($_POST['title']   ?? '');
        $desc  = trim($_POST['desc']    ?? '');
        $type  = $_POST['type']         ?? 'other';
        $due   = $_POST['due']          ?? null;

        // Owner only can edit
        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$id, $user_id]);

        if ($stmt->fetch() && !empty($title)) {
            $allowed_types = ['essay','journal','speech','article','blog','report','other'];
            if (!in_array($type, $allowed_types)) $type = 'other';
            $stmt = $db->prepare(
                'UPDATE projects SET title=?, description=?, type=?, due_date=?
                 WHERE id=?'
            );
            $stmt->execute([$title, $desc, $type, $due ?: null, $id]);
            $toast = 'Project updated.|success';
        }

    // ── Delete project (owner only) ───────────────────────
    } elseif ($action === 'delete_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$id, $user_id]);
        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
            $toast = 'Project deleted.|error';
        } else {
            $toast = 'Only the project owner can delete this project.|error';
        }

    // ── Mark complete (owner only) ────────────────────────
    } elseif ($action === 'complete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$id, $user_id]);
        if ($stmt->fetch()) {
            $db->prepare('UPDATE projects SET status=? WHERE id=?')->execute(['complete', $id]);
            $toast = 'Project marked complete!|success';
        } else {
            $toast = 'Only the project owner can mark this complete.|error';
        }

    // ── Reopen project (owner only) ───────────────────────
    } elseif ($action === 'reopen') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$id, $user_id]);
        if ($stmt->fetch()) {
            $db->prepare('UPDATE projects SET status=? WHERE id=?')->execute(['in_progress', $id]);
            $toast = 'Project reopened.|success';
        }

    // ── Add collaborator (owner only) ─────────────────────
    } elseif ($action === 'add_collab') {
        $project_id    = (int) ($_POST['project_id']    ?? 0);
        $collab_email  = trim($_POST['collab_email']    ?? '');

        // Must be owner
        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$project_id, $user_id]);
        if ($stmt->fetch()) {
            // Find user by email
            $stmt = $db->prepare('SELECT id, name FROM users WHERE email=?');
            $stmt->execute([$collab_email]);
            $collab_user = $stmt->fetch();

            if (!$collab_user) {
                $toast = 'No active user found with that email.|error';
            } elseif ((int)$collab_user['id'] === $user_id) {
                $toast = 'You are already the project owner.|error';
            } else {
                // Check not already added
                $stmt = $db->prepare('SELECT id FROM project_collaborators WHERE project_id=? AND user_id=?');
                $stmt->execute([$project_id, $collab_user['id']]);
                if ($stmt->fetch()) {
                    $safe_name = str_replace('|', '', htmlspecialchars($collab_user['name']));
                    $toast = $safe_name . ' is already a collaborator.|error';
                } else {
                    $db->prepare('INSERT INTO project_collaborators (project_id, user_id) VALUES (?,?)')->execute([$project_id, $collab_user['id']]);
                    // Notify the added user
                    $proj_title = $db->prepare('SELECT title FROM projects WHERE id=?');
                    $proj_title->execute([$project_id]);
                    $ptitle = $proj_title->fetchColumn();
                    $msg = $_SESSION['user_name'] . ' added you to "' . $ptitle . '"';
                    sendNotification($collab_user['id'], $user_id, $project_id, 'added_to_project', $msg);
                    $safe_name = str_replace('|', '', htmlspecialchars($collab_user['name']));
                    $toast = $safe_name . ' added as collaborator!|success';
                }
            }
        }

    // ── Remove collaborator (owner only) ──────────────────
    } elseif ($action === 'remove_collab') {
        $project_id  = (int) ($_POST['project_id']  ?? 0);
        $collab_id   = (int) ($_POST['collab_id']   ?? 0);

        $stmt = $db->prepare('SELECT id FROM projects WHERE id=? AND owner_id=?');
        $stmt->execute([$project_id, $user_id]);
        if ($stmt->fetch() && $collab_id !== $user_id) {
            $db->prepare('DELETE FROM project_collaborators WHERE project_id=? AND user_id=?')->execute([$project_id, $collab_id]);
            $toast = 'Collaborator removed.|success';
        }
    }

    $qs = $toast ? '?toast=' . urlencode($toast) : '';
    header('Location: dashboard.php' . $qs);
    exit;
}

// Carry toast from redirect
if (!empty($_GET['toast'])) {
    $toast = $_GET['toast'];
}

// ── Fetch ALL projects — mark membership ─────────────────
$stmt = $db->prepare(
    'SELECT p.id, p.title, p.description AS `desc`, p.type, p.status,
            p.is_solo, p.due_date AS `due`, p.owner_id, p.created_at,
            u.name AS owner_name,
            MAX(CASE WHEN pc.user_id = ? THEN 1 ELSE 0 END) AS is_member
     FROM projects p
     JOIN users u ON u.id = p.owner_id
     LEFT JOIN project_collaborators pc ON pc.project_id = p.id
     GROUP BY p.id
     ORDER BY is_member DESC, p.status ASC, p.created_at DESC'
);
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll();

// Split for stats (only projects user is part of)
$my_projects = array_filter($projects, fn($p) => $p['is_member']);

// ── Collaborators per project ─────────────────────────────
$project_ids = array_column($projects, 'id');
$collabs_map = [];
if ($project_ids) {
    $in  = implode(',', array_fill(0, count($project_ids), '?'));
    $stmt = $db->prepare(
        "SELECT pc.project_id, u.id, u.name, u.role
         FROM project_collaborators pc
         JOIN users u ON u.id = pc.user_id
         WHERE pc.project_id IN ($in)"
    );
    $stmt->execute($project_ids);
    foreach ($stmt->fetchAll() as $row) {
        $collabs_map[$row['project_id']][] = $row;
    }
}

// ── Stats (based on user's own projects) ─────────────────
$total      = count($my_projects);
$complete   = count(array_filter($my_projects, fn($p) => $p['status'] === 'complete'));
$in_prog    = count(array_filter($my_projects, fn($p) => $p['status'] === 'in_progress'));
$draft      = count(array_filter($my_projects, fn($p) => $p['status'] === 'draft'));
$collab_cnt = count(array_filter($my_projects, fn($p) => !$p['is_solo']));

// ── Sidebar counts ─────────────────────────────────────────
$sb = [
    'all'      => count($projects),
    'mine'     => count(array_filter($my_projects, fn($p) => $p['owner_id'] == $user_id)),
    'collab'   => count(array_filter($my_projects, fn($p) => $p['owner_id'] != $user_id)),
    'complete' => $complete,
    'draft'    => $draft + $in_prog,
];

// ── All users for collaborator search ─────────────────────
$all_users = $db->query('SELECT id, name, email, role FROM users ORDER BY name ASC')->fetchAll();

// ── Helpers ───────────────────────────────────────────────
function fmtDate(?string $iso): string {
    if (!$iso) return '';
    return (new DateTime($iso))->format('M j, Y');
}
function isOverdue(?string $iso, string $status): bool {
    if (!$iso || $status === 'complete') return false;
    return new DateTime($iso) < new DateTime('today');
}
function statusLabel(string $s): string {
    return match($s) {
        'draft'       => 'Draft',
        'in_progress' => 'In Progress',
        'review'      => 'Review',
        'complete'    => 'Complete',
        default       => ucfirst($s),
    };
}
function typeLabel(string $t): string {
    return match($t) {
        'essay'   => 'Essay',
        'journal' => 'Journal',
        'speech'  => 'Speech',
        'article' => 'Article',
        'blog'    => 'Blog Post',
        'report'  => 'Report',
        default   => 'Other',
    };
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr($parts[count($parts)-1], 0, 1));
    return $i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-body">

<?php include 'nav.php'; ?>

<!--Body-->
<div class="app-body">

  <!--Sidebar-->
  <aside class="sidebar">
    <span class="sidebar-label">Projects</span>
    <button class="sidebar-link active" onclick="setView('all',this)" type="button">
      <svg><use href="#i-grid"/></svg> All Projects <span class="sidebar-badge"><?= $sb['all'] ?></span>
    </button>
    <button class="sidebar-link" onclick="setView('mine',this)" type="button">
      <svg><use href="#i-solo"/></svg> My Projects <span class="sidebar-badge"><?= $sb['mine'] ?></span>
    </button>
    <button class="sidebar-link" onclick="setView('collab',this)" type="button">
      <svg><use href="#i-collab"/></svg> Collaborating <span class="sidebar-badge"><?= $sb['collab'] ?></span>
    </button>
    <span class="sidebar-label">Status</span>
    <button class="sidebar-link" onclick="setView('active',this)" type="button">
      <svg><use href="#i-pen"/></svg> Active <span class="sidebar-badge"><?= $sb['draft'] ?></span>
    </button>
    <button class="sidebar-link" onclick="setView('complete',this)" type="button">
      <svg><use href="#i-done-tasks"/></svg> Complete <span class="sidebar-badge"><?= $sb['complete'] ?></span>
    </button>

    <!--Bottom user panel (Claude-style)-->
    <div class="sidebar-user-panel">
      <a href="profile.php" class="sidebar-user-info">
        <div class="sidebar-avatar"><?= htmlspecialchars(initials($_SESSION['user_name'])) ?></div>
        <div class="sidebar-user-text">
          <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
          <span class="sidebar-user-role"><?php
            $rl = ['speechwriter'=>'Speech Writer','ghostwriter'=>'Ghostwriter','copywriter'=>'Copywriter','journalist'=>'Journalist'];
            echo htmlspecialchars($rl[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']));
          ?></span>
        </div>
      </a>
      <a href="settings.php" class="sidebar-icon-btn" title="Settings">
        <svg><use href="#i-settings"/></svg>
      </a>
      <a href="logout.php" class="sidebar-logout-btn sidebar-logout-visible" title="Log out">
        <svg><use href="#i-logout"/></svg>
        <span>Log out</span>
      </a>
    </div>
  </aside>

  <!--Main-->
  <main class="main-content">

    <!--Page header-->
    <div class="page-header">
      <div>
        <h2 id="view-title">All Projects</h2>
        <p id="view-sub">Manage your writing projects and collaborations.</p>
      </div>
      <button class="btn-accent" onclick="openAddModal()" type="button">
        <svg style="width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round"><use href="#i-plus"/></svg>
        New Project
      </button>
    </div>

    <!--Stats-->
    <div class="stats-row">
      <div class="stat-card"><div class="stat-label">Total Projects</div><div class="stat-value"><?= $total ?></div></div>
      <div class="stat-card"><div class="stat-label">Complete</div><div class="stat-value green"><?= $complete ?></div></div>
      <div class="stat-card"><div class="stat-label">In Progress</div><div class="stat-value amber"><?= $in_prog ?></div></div>
      <div class="stat-card"><div class="stat-label">Collaborations</div><div class="stat-value accent"><?= $collab_cnt ?></div></div>
    </div>

    <!--Toolbar-->
    <div class="toolbar">
      <div class="toolbar-search">
        <svg><use href="#i-search"/></svg>
        <input type="text" id="search-input" placeholder="Search projects…" oninput="filterProjects()" />
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all"      onclick="setTabFilter(this)">All</button>
        <button class="filter-tab"        data-filter="solo"     onclick="setTabFilter(this)">Solo</button>
        <button class="filter-tab"        data-filter="collab"   onclick="setTabFilter(this)">Collaborative</button>
      </div>
    </div>

    <!--Project list-->
    <div class="task-list" id="project-list">
      <?php foreach ($projects as $p):
        $overdue  = isOverdue($p['due'], $p['status']);
        $is_owner = ($p['owner_id'] == $user_id);
        $collabs  = $collabs_map[$p['id']] ?? [];
        $complete = $p['status'] === 'complete';
      ?>
      <?php $is_member = (bool)$p['is_member']; ?>
      <div class="task-card project-card<?= $complete ? ' done' : '' ?><?= !$is_member ? ' read-only-card' : '' ?>"
           data-id="<?= $p['id'] ?>"
           data-owner="<?= $p['owner_id'] ?>"
           data-status="<?= htmlspecialchars($p['status']) ?>"
           data-solo="<?= $p['is_solo'] ? '1' : '0' ?>"
           data-member="<?= $is_member ? '1' : '0' ?>">

        <!--Complete toggle — owner only-->
        <?php if ($is_owner): ?>
        <form method="POST" action="dashboard.php" style="display:contents">
          <input type="hidden" name="action" value="<?= $complete ? 'reopen' : 'complete' ?>">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="task-check<?= $complete ? ' checked' : '' ?>"
                  title="<?= $complete ? 'Reopen project' : 'Mark complete' ?>" aria-label="Toggle complete">
            <?php if ($complete): ?><svg><use href="#i-check"/></svg><?php endif; ?>
          </button>
        </form>
        <?php else: ?>
        <!-- Non-owners see a locked indicator -->
        <div class="task-check locked" title="Only the owner can mark this complete">
          <svg><use href="#i-lock"/></svg>
        </div>
        <?php endif; ?>

        <!--Project body-->
        <div class="task-body">
          <div class="task-title"><?= htmlspecialchars($p['title']) ?></div>
          <?php if ($p['desc']): ?>
          <div class="task-desc"><?= htmlspecialchars($p['desc']) ?></div>
          <?php endif; ?>

          <div class="task-meta">
            <!--Type badge-->
            <span class="badge type-<?= htmlspecialchars($p['type']) ?>">
              <svg style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.5"><use href="#i-file-text"/></svg>
              <?= typeLabel($p['type']) ?>
            </span>

            <!--Status badge-->
            <span class="badge status-<?= htmlspecialchars($p['status']) ?>">
              <?= statusLabel($p['status']) ?>
            </span>

            <!--Solo / Collab-->
            <span class="badge <?= $p['is_solo'] ? 'solo' : 'collab-badge' ?>">
              <svg style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.5">
                <use href="<?= $p['is_solo'] ? '#i-solo' : '#i-collab' ?>"/>
              </svg>
              <?= $p['is_solo'] ? 'Solo' : 'Collaborative' ?>
            </span>

            <!--Due date-->
            <?php if ($p['due']): ?>
            <span class="task-due<?= $overdue ? ' overdue' : '' ?>">
              <svg><use href="#i-clock"/></svg>
              <?= fmtDate($p['due']) ?><?= $overdue ? ' · Overdue' : '' ?>
            </span>
            <?php endif; ?>

            <!--Owner info-->
            <?php if (!$is_owner): ?>
            <span class="task-due">
              <svg><use href="#i-user"/></svg>
              <?= htmlspecialchars($p['owner_name']) ?>
            </span>
            <?php endif; ?>
          </div>

          <!--Collaborator avatars-->
          <?php if (!$p['is_solo'] && count($collabs) > 0): ?>
          <div class="collab-avatars">
            <?php foreach (array_slice($collabs, 0, 5) as $c): ?>
            <div class="collab-avatar" title="<?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars(ucfirst($c['role'])) ?>)">
              <?= htmlspecialchars(initials($c['name'])) ?>
            </div>
            <?php endforeach; ?>
            <?php if (count($collabs) > 5): ?>
            <div class="collab-avatar more">+<?= count($collabs) - 5 ?></div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <!--Actions-->
        <div class="task-actions">
          <!--View detail-->
          <a href="project.php?id=<?= $p['id'] ?>" class="btn-icon" title="View project">
            <svg><use href="#i-file-text"/></svg>
          </a>

          <?php if ($is_member): ?>
          <!--Start Writing — members only-->
          <button class="btn-icon start-writing-btn" title="Start Writing"
                  onclick='openWritingModal(<?= $p["id"] ?>, <?= htmlspecialchars(json_encode($p["title"]), ENT_QUOTES) ?>)'
                  type="button">
            <svg><use href="#i-pen"/></svg>
          </button>

          <!--Edit — owner only-->
          <?php if ($is_owner): ?>
          <button class="btn-icon"
                  onclick='openEditModal(<?= $p["id"] ?>, <?= htmlspecialchars(json_encode([
                    "title" => $p["title"],
                    "desc"  => $p["desc"],
                    "type"  => $p["type"],
                    "due"   => $p["due"] ?? "",
                  ]), ENT_QUOTES) ?>)'
                  title="Edit project" type="button">
            <svg><use href="#i-edit"/></svg>
          </button>
          <?php endif; ?>

          <!--Manage collaborators — owner only-->
          <?php if ($is_owner && !$p['is_solo']): ?>
          <button class="btn-icon"
                  onclick='openCollabModal(<?= $p["id"] ?>, <?= htmlspecialchars(json_encode($collabs_map[$p["id"]] ?? []), ENT_QUOTES) ?>)'
                  title="Manage collaborators" type="button">
            <svg><use href="#i-users"/></svg>
          </button>
          <?php endif; ?>

          <!--Delete — owner only-->
          <?php if ($is_owner): ?>
          <button class="btn-icon"
                  onclick='openDeleteModal(<?= $p["id"] ?>, <?= htmlspecialchars(json_encode($p["title"]), ENT_QUOTES) ?>)'
                  title="Delete project" type="button"
                  style="color:var(--red);border-color:rgba(220,38,38,.2)">
            <svg><use href="#i-trash"/></svg>
          </button>
          <?php endif; ?>
          <?php else: ?>
          <!--Read-only badge for non-members-->
          <span class="badge read-only-badge">
            <svg style="width:9px;height:9px;stroke:currentColor;fill:none;stroke-width:2.5"><use href="#i-lock"/></svg>
            View only
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!--Empty state-->
    <div class="empty-state" id="empty-state" <?= $total ? 'style="display:none"' : '' ?>>
      <svg><use href="#i-pen"/></svg>
      <h3>No projects yet</h3>
      <p>Click <strong>New Project</strong> to get started.</p>
    </div>

  </main>
</div>


<!--MODAL · ADD / EDIT PROJECT-->
<div class="modal-backdrop" id="project-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-header">
      <h3 id="modal-title">New Project</h3>
      <button class="modal-close" onclick="closeModal('project-modal')" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>

    <form method="POST" action="dashboard.php" id="project-form">
      <input type="hidden" name="action" id="form-action" value="add_project">
      <input type="hidden" name="id"     id="form-id"     value="">

      <div class="field">
        <label for="m-title">Project title <span style="color:var(--red)">*</span></label>
        <input type="text" id="m-title" name="title" placeholder="e.g. Annual Report Speech" required />
        <div class="field-err" id="m-title-err">
          <svg><use href="#i-alert"/></svg><span>Title is required.</span>
        </div>
      </div>

      <div class="field">
        <label for="m-desc">Description</label>
        <textarea id="m-desc" name="desc" placeholder="What is this project about?"></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="field" style="margin-bottom:0">
          <label for="m-type">Writing type</label>
          <select id="m-type" name="type">
            <option value="essay">Essay</option>
            <option value="journal">Journal</option>
            <option value="speech">Speech</option>
            <option value="article">Article</option>
            <option value="blog">Blog Post</option>
            <option value="report">Report</option>
            <option value="other" selected>Other</option>
          </select>
        </div>
        <div class="field" style="margin-bottom:0">
          <label for="m-due">Due date</label>
          <input type="date" id="m-due" name="due" />
        </div>
      </div>

      <div class="field" style="margin-top:.75rem">
        <label class="solo-toggle-label">
          <input type="checkbox" id="m-solo" name="is_solo" value="1" onchange="toggleSoloHint(this)" />
          <span>Solo project <span class="hint-text">(no collaborators)</span></span>
        </label>
      </div>

      <div class="modal-footer">
        <button class="btn-ghost" onclick="closeModal('project-modal')" type="button">Cancel</button>
        <button class="btn-accent" type="submit" id="modal-save-btn">Create Project</button>
      </div>
    </form>
  </div>
</div>


<!--MODAL · MANAGE COLLABORATORS-->
<div class="modal-backdrop" id="collab-modal">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h3>Manage Collaborators</h3>
      <button class="modal-close" onclick="closeModal('collab-modal')" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>

    <!--Add collaborator with live search-->
    <form method="POST" action="dashboard.php" id="collab-form">
      <input type="hidden" name="action"     value="add_collab">
      <input type="hidden" name="project_id" id="collab-project-id" value="">
      <input type="hidden" name="collab_email" id="collab-email-hidden" value="">

      <div class="field">
        <label for="collab-search">Add collaborator</label>
        <div style="position:relative">
          <input type="text" id="collab-search" autocomplete="off"
                 placeholder="Search by name or email…"
                 oninput="searchCollabs(this.value)"
                 onfocus="searchCollabs(this.value)" />
          <div id="collab-dropdown" class="collab-dropdown" style="display:none"></div>
        </div>
        <div id="collab-selected" class="collab-selected-user" style="display:none"></div>
      </div>
      <button class="btn-accent" type="submit" id="collab-add-btn" disabled
              style="width:100%">
        <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5"><use href="#i-plus"/></svg>
        Add Collaborator
      </button>
    </form>

    <!--Current collaborators list-->
    <div class="collab-list-label">Current collaborators</div>
    <div id="collab-list-container"></div>

    <div class="modal-footer">
      <button class="btn-ghost" onclick="closeModal('collab-modal')" type="button">Done</button>
    </div>
  </div>
</div>


<!--MODAL · CONFIRM DELETE-->
<div class="modal-backdrop" id="delete-modal">
  <div class="modal" style="max-width:380px" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h3>Delete Project</h3>
      <button class="modal-close" onclick="closeModal('delete-modal')" type="button" aria-label="Close">
        <svg><use href="#i-x"/></svg>
      </button>
    </div>
    <div class="confirm-body">
      <div class="confirm-icon"><svg><use href="#i-warn"/></svg></div>
      <p>Are you sure you want to delete <strong id="del-project-name"></strong>?
         This will remove all collaborators and cannot be undone.</p>
    </div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="delete_project">
      <input type="hidden" name="id"     id="del-project-id" value="">
      <div class="modal-footer">
        <button class="btn-ghost" onclick="closeModal('delete-modal')" type="button">Cancel</button>
        <button class="btn-danger" type="submit">
          <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><use href="#i-trash"/></svg>
          Delete
        </button>
      </div>
    </form>
  </div>
</div>


<!--MODAL · START WRITING (Notepad)-->
<div class="modal-backdrop" id="writing-modal">
  <div class="modal modal-notepad" role="dialog" aria-modal="true">
    <div class="modal-header">
      <div>
        <h3 id="writing-modal-title">Start Writing</h3>
        <p id="writing-modal-sub" style="font-size:12.5px;color:var(--ink-3);margin-top:2px"></p>
      </div>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span id="writing-save-status" class="writing-save-status"></span>
        <button class="modal-close" onclick="closeWritingModal()" type="button" aria-label="Close">
          <svg><use href="#i-x"/></svg>
        </button>
      </div>
    </div>
    <div class="writing-toolbar">
      <button type="button" onclick="wFmt('bold')"      title="Bold"><strong>B</strong></button>
      <button type="button" onclick="wFmt('italic')"    title="Italic"><em>I</em></button>
      <button type="button" onclick="wFmt('underline')" title="Underline"><u>U</u></button>
      <div class="writing-toolbar-sep"></div>
      <button type="button" onclick="wBlock('h2')"      title="Heading">H</button>
      <button type="button" onclick="wBlock('p')"       title="Paragraph">¶</button>
      <div class="writing-toolbar-sep"></div>
      <span id="writing-wordcount" class="writing-wordcount">0 words</span>
    </div>
    <div id="writing-area" class="writing-area" contenteditable="true"
         data-placeholder="Start writing your project here…"
         oninput="onWritingInput()"></div>
    <div class="modal-footer">
      <button class="btn-ghost" onclick="closeWritingModal()" type="button">Close</button>
      <button class="btn-accent" type="button" onclick="saveWriting()" id="writing-save-btn">
        <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5"><use href="#i-check"/></svg>
        Save
      </button>
    </div>
  </div>
</div>

<!--TOAST CONTAINER-->
<div class="toast-container" id="toast-container"></div>


<!--JAVASCRIPT-->
<script>
const VIEW_META = {
  all:      { title: 'All Projects',    sub: 'All your writing projects and collaborations.' },
  mine:     { title: 'My Projects',     sub: 'Projects you created and own.' },
  collab:   { title: 'Collaborating',   sub: 'Projects you were invited to.' },
  active:   { title: 'Active',          sub: 'Projects in draft or in progress.' },
  complete: { title: 'Complete',        sub: 'Finished writing projects.' },
};

let activeView   = 'all';
let activeFilter = 'all';
const ME = <?= $user_id ?>;

/* ── Sidebar view ── */
function setView(v, btn) {
  activeView = v;
  document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  const meta = VIEW_META[v] || VIEW_META.all;
  document.getElementById('view-title').textContent = meta.title;
  document.getElementById('view-sub').textContent   = meta.sub;
  filterProjects();
}

/* ── Tab filter ── */
function setTabFilter(btn) {
  document.querySelectorAll('.filter-tab').forEach(el => el.classList.remove('active'));
  btn.classList.add('active');
  activeFilter = btn.dataset.filter;
  filterProjects();
}

/* ── Filter projects ── */
function filterProjects() {
  const q = document.getElementById('search-input').value.toLowerCase();
  let visible = 0;

  document.querySelectorAll('.project-card').forEach(card => {
    const owner  = parseInt(card.dataset.owner);
    const status = card.dataset.status;
    const solo   = card.dataset.solo === '1';
    const text   = card.textContent.toLowerCase();
    let show     = true;

    if (activeView === 'mine'     && owner !== ME)              show = false;
    if (activeView === 'collab'   && owner === ME)              show = false;
    if (activeView === 'active'   && status === 'complete')     show = false;
    if (activeView === 'complete' && status !== 'complete')     show = false;
    if (activeFilter === 'solo'   && !solo)                     show = false;
    if (activeFilter === 'collab' && solo)                      show = false;
    if (q && !text.includes(q))                                 show = false;

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
  document.getElementById('form-action').value       = 'add_project';
  document.getElementById('form-id').value           = '';
  document.getElementById('m-title').value           = '';
  document.getElementById('m-desc').value            = '';
  document.getElementById('m-type').value            = 'other';
  document.getElementById('m-due').value             = '';
  document.getElementById('m-solo').checked          = false;
  document.getElementById('modal-title').textContent    = 'New Project';
  document.getElementById('modal-save-btn').textContent = 'Create Project';
  document.getElementById('m-title-err').style.display  = 'none';
  openModal('project-modal');
}

/* ── Edit modal ── */
function openEditModal(id, data) {
  document.getElementById('form-action').value       = 'edit_project';
  document.getElementById('form-id').value           = id;
  document.getElementById('m-title').value           = data.title;
  document.getElementById('m-desc').value            = data.desc || '';
  document.getElementById('m-type').value            = data.type;
  document.getElementById('m-due').value             = data.due  || '';
  document.getElementById('modal-title').textContent    = 'Edit Project';
  document.getElementById('modal-save-btn').textContent = 'Save Changes';
  document.getElementById('m-title-err').style.display  = 'none';
  openModal('project-modal');
}

/* ── Collaborator modal ── */
function openCollabModal(projectId, collabs) {
  document.getElementById('collab-project-id').value = projectId;
  document.getElementById('collab-search').value     = '';
  clearCollab();

  const container = document.getElementById('collab-list-container');
  if (!collabs || collabs.length === 0) {
    container.innerHTML = '<p style="font-size:13px;color:var(--ink-3);padding:.5rem 0">No collaborators yet.</p>';
  } else {
    container.innerHTML = collabs.map(c => `
      <div class="collab-row" data-uid="${c.id}">
        <div class="collab-avatar sm">${esc(initials(c.name))}</div>
        <div class="collab-info">
          <span class="collab-name">${esc(c.name)}</span>
          <span class="collab-role">${esc(cap(c.role))}</span>
        </div>
        ${c.id !== ME ? `
        <form method="POST" action="dashboard.php" style="margin-left:auto">
          <input type="hidden" name="action"     value="remove_collab">
          <input type="hidden" name="project_id" value="${projectId}">
          <input type="hidden" name="collab_id"  value="${c.id}">
          <button class="btn-icon" type="submit" title="Remove"
                  style="color:var(--red);border-color:rgba(220,38,38,.2)">
            <svg style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><use href="#i-x"/></svg>
          </button>
        </form>` : '<span style="font-size:11px;color:var(--ink-3);margin-left:auto">Owner</span>'}
      </div>`).join('');
  }
  openModal('collab-modal');
}

/* ── Delete modal ── */
function openDeleteModal(id, name) {
  document.getElementById('del-project-id').value         = id;
  document.getElementById('del-project-name').textContent = '"' + name + '"';
  openModal('delete-modal');
}

/* ── Form validation ── */
document.getElementById('project-form').addEventListener('submit', function(e) {
  const title = document.getElementById('m-title').value.trim();
  if (!title) {
    e.preventDefault();
    document.getElementById('m-title').classList.add('err');
    document.getElementById('m-title-err').style.display = 'flex';
    document.getElementById('m-title').focus();
  }
});

function toggleSoloHint(cb) {
  const hint = cb.closest('.field').querySelector('.hint-text');
  if (hint) hint.style.color = cb.checked ? 'var(--amber)' : '';
}

/* ── Helpers ── */
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
function initials(name) {
  const parts = name.trim().split(' ');
  let i = parts[0][0].toUpperCase();
  if (parts.length > 1) i += parts[parts.length-1][0].toUpperCase();
  return i;
}

/* ── Fire toast from redirect ── */
<?php if ($toast):
  [$tmsg, $ttype] = explode('|', $toast . '|');
?>
window.addEventListener('DOMContentLoaded', () => {
  toast(<?= json_encode($tmsg) ?>, <?= json_encode(trim($ttype)) ?>);
});
<?php endif; ?>

function toast(msg, type) {
  const icon = type === 'success' ? '#i-check-c' : type === 'error' ? '#i-warn' : '#i-alert';
  const el   = document.createElement('div');
  el.className = 'toast' + (type ? ' ' + type : '');
  el.innerHTML = `<svg><use href="${icon}"/></svg>${msg}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

/* ════════════════════════════════
   COLLAB LIVE SEARCH
════════════════════════════════ */
const ALL_USERS = <?= json_encode(array_values(array_filter($all_users, fn($u) => $u['id'] != $user_id))) ?>;
let selectedCollabEmail = '';

function searchCollabs(q) {
  const drop = document.getElementById('collab-dropdown');
  q = q.trim().toLowerCase();
  if (!q) { drop.style.display = 'none'; return; }

  // Filter users not already in current collab list
  const currentIds = Array.from(document.querySelectorAll('#collab-list-container .collab-row'))
    .map(r => parseInt(r.dataset.uid || 0));

  const matches = ALL_USERS.filter(u =>
    (u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)) &&
    !currentIds.includes(u.id)
  ).slice(0, 6);

  if (!matches.length) {
    drop.innerHTML = '<div class="collab-drop-empty">No users found</div>';
    drop.style.display = 'block';
    return;
  }

  drop.innerHTML = matches.map(u => `
    <div class="collab-drop-item" onclick="selectCollab('${esc(u.email)}','${esc(u.name)}','${esc(u.role)}')">
      <div class="collab-avatar sm" style="width:28px;height:28px;font-size:10px;border:none">${esc(initials(u.name))}</div>
      <div>
        <div style="font-size:13px;font-weight:500;color:var(--ink)">${esc(u.name)}</div>
        <div style="font-size:11.5px;color:var(--ink-3)">${esc(u.email)} · ${esc(cap(u.role))}</div>
      </div>
    </div>`).join('');
  drop.style.display = 'block';
}

function selectCollab(email, name, role) {
  selectedCollabEmail = email;
  document.getElementById('collab-email-hidden').value = email;
  document.getElementById('collab-search').value = '';
  document.getElementById('collab-dropdown').style.display = 'none';
  document.getElementById('collab-add-btn').disabled = false;

  const sel = document.getElementById('collab-selected');
  sel.style.display = 'flex';
  sel.innerHTML = `
    <div class="collab-avatar sm" style="width:28px;height:28px;font-size:10px;border:none">${esc(initials(name))}</div>
    <div style="flex:1">
      <div style="font-size:13px;font-weight:500">${esc(name)}</div>
      <div style="font-size:11.5px;color:var(--ink-3)">${esc(email)}</div>
    </div>
    <button type="button" onclick="clearCollab()" class="btn-icon" style="width:24px;height:24px;color:var(--ink-3)">
      <svg style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2"><use href="#i-x"/></svg>
    </button>`;
}

function clearCollab() {
  selectedCollabEmail = '';
  document.getElementById('collab-email-hidden').value = '';
  document.getElementById('collab-search').value = '';
  document.getElementById('collab-selected').style.display = 'none';
  document.getElementById('collab-add-btn').disabled = true;
}

// Close dropdown on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('#collab-search') && !e.target.closest('#collab-dropdown')) {
    const d = document.getElementById('collab-dropdown');
    if (d) d.style.display = 'none';
  }
});

/* ════════════════════════════════
   WRITING NOTEPAD
════════════════════════════════ */
let writingProjectId = null;
let writingSaveTimer = null;

async function openWritingModal(projectId, title) {
  writingProjectId = projectId;
  document.getElementById('writing-modal-title').textContent = title;
  document.getElementById('writing-modal-sub').textContent   = 'Auto-saves as you type';
  document.getElementById('writing-save-status').textContent = '';
  document.getElementById('writing-area').innerHTML          = '';
  document.getElementById('writing-wordcount').textContent   = '0 words';
  openModal('writing-modal');
  document.execCommand('defaultParagraphSeparator', false, 'p');

  // Load existing content
  try {
    const res  = await fetch(`writing.php?action=load&project_id=${projectId}`);
    const data = await res.json();
    if (data.content) {
      document.getElementById('writing-area').innerHTML = data.content;
      updateWordCount();
    }
  } catch(e) { /* first time — no content yet */ }
}

function closeWritingModal() {
  if (writingSaveTimer) clearTimeout(writingSaveTimer);
  saveWriting();
  closeModal('writing-modal');
  writingProjectId = null;
}

function onWritingInput() {
  updateWordCount();
  updateToolbarState();
  document.getElementById('writing-save-status').textContent = 'Unsaved…';
  if (writingSaveTimer) clearTimeout(writingSaveTimer);
  writingSaveTimer = setTimeout(saveWriting, 1500);
}

async function saveWriting() {
  if (!writingProjectId) return;
  const content = document.getElementById('writing-area').innerHTML;
  try {
    const res = await fetch('writing.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=save&project_id=${writingProjectId}&content=${encodeURIComponent(content)}`
    });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('writing-save-status').textContent = 'Saved ✓';
      setTimeout(() => {
        const s = document.getElementById('writing-save-status');
        if (s && s.textContent === 'Saved ✓') s.textContent = '';
      }, 2000);
    }
  } catch(e) {}
}

function updateWordCount() {
  const text  = document.getElementById('writing-area').innerText || '';
  const words = text.trim() ? text.trim().split(/\s+/).length : 0;
  document.getElementById('writing-wordcount').textContent = words + ' word' + (words !== 1 ? 's' : '');
}

/* ── Toolbar formatting ── */
function wFmt(cmd) {
  const area = document.getElementById('writing-area');
  area.focus();
  document.execCommand(cmd, false, null);
  updateToolbarState();
}

function wBlock(tag) {
  const area = document.getElementById('writing-area');
  area.focus();

  const sel = window.getSelection();
  if (!sel || sel.rangeCount === 0) return;

  const range = sel.getRangeAt(0);

  // Find the block-level ancestor inside writing-area
  let node = range.commonAncestorContainer;
  if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;

  // Walk up until we hit a direct child of writing-area or writing-area itself
  while (node && node !== area && node.parentNode !== area) {
    node = node.parentNode;
  }

  if (!node || node === area) {
    // No block found — wrap selected/current content in the new tag
    document.execCommand('formatBlock', false, '<' + tag + '>');
  } else {
    if (node.tagName.toLowerCase() === tag) {
      // Already this block type — toggle back to paragraph if heading, or do nothing
      if (tag !== 'p') {
        const p = document.createElement('p');
        p.innerHTML = node.innerHTML;
        node.replaceWith(p);
        // Restore caret to new element
        const r = document.createRange();
        r.selectNodeContents(p);
        r.collapse(false);
        sel.removeAllRanges();
        sel.addRange(r);
      }
    } else {
      // Replace current block tag with new one
      const newEl = document.createElement(tag);
      newEl.innerHTML = node.innerHTML;
      node.replaceWith(newEl);
      // Restore caret
      const r = document.createRange();
      r.selectNodeContents(newEl);
      r.collapse(false);
      sel.removeAllRanges();
      sel.addRange(r);
    }
  }

  area.dispatchEvent(new Event('input'));
  updateToolbarState();
}

function updateToolbarState() {
  // Bold / Italic / Underline active state
  const cmds = { bold: 'B', italic: 'I', underline: 'U' };
  Object.entries(cmds).forEach(([cmd, label]) => {
    const btn = document.querySelector(`.writing-toolbar button[title="${cmd.charAt(0).toUpperCase() + cmd.slice(1)}"]`);
    if (btn) btn.classList.toggle('active', document.queryCommandState(cmd));
  });

  // Block type active state
  const sel = window.getSelection();
  let blockTag = 'p';
  if (sel && sel.rangeCount > 0) {
    let node = sel.getRangeAt(0).commonAncestorContainer;
    if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
    const area = document.getElementById('writing-area');
    while (node && node !== area && node.parentNode !== area) node = node.parentNode;
    if (node && node !== area) blockTag = node.tagName.toLowerCase();
  }
  document.querySelector('.writing-toolbar button[title="Heading"]')
    ?.classList.toggle('active', blockTag === 'h2');
  document.querySelector('.writing-toolbar button[title="Paragraph"]')
    ?.classList.toggle('active', blockTag === 'p');
}

// Update toolbar state on selection change
document.addEventListener('selectionchange', () => {
  if (document.activeElement === document.getElementById('writing-area')) {
    updateToolbarState();
  }
});
</script>

</body>
</html>
