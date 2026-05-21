<?php
// ============================================================
//  Ame Writer — project.php
//  Project detail page. Only collaborators can access.
//  Shows project info, collaborators, status updates.
//  Owner can change status, add/remove collaborators.
//  All collaborators can update status (draft→in_progress→review).
//  Only owner can mark complete or delete.
// ============================================================
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'database.php';

$db      = getDB();
$user_id = (int) $_SESSION['user_id'];
$proj_id = (int) ($_GET['id'] ?? 0);
$toast   = '';

if (!$proj_id) { header('Location: dashboard.php'); exit; }

// ── Security: must be a collaborator ─────────────────────
$stmt = $db->prepare(
    'SELECT p.*, u.name AS owner_name, u.email AS owner_email
     FROM projects p
     JOIN project_collaborators pc ON pc.project_id = p.id
     JOIN users u ON u.id = p.owner_id
     WHERE p.id=? AND pc.user_id=?'
);
$stmt->execute([$proj_id, $user_id]);
$project = $stmt->fetch();

if (!$project) {
    // Not a collaborator — block access
    header('Location: dashboard.php?toast=' . urlencode('You do not have access to that project.|error'));
    exit;
}

$is_owner = ($project['owner_id'] == $user_id);

// ── POST handler ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update status (collaborators: draft/in_progress/review; owner: all incl. complete) ──
    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $allowed = $is_owner
            ? ['draft','in_progress','review','complete']
            : ['draft','in_progress','review'];

        if (in_array($new_status, $allowed)) {
            $db->prepare('UPDATE projects SET status=? WHERE id=?')
               ->execute([$new_status, $proj_id]);

            // Notify all collaborators of status change
            $collabs_stmt = $db->prepare(
                'SELECT user_id FROM project_collaborators WHERE project_id=? AND user_id != ?'
            );
            $collabs_stmt->execute([$proj_id, $user_id]);
            $msg = $_SESSION['user_name'] . ' changed status to ' . ucfirst(str_replace('_',' ',$new_status)) . ' on "' . $project['title'] . '"';
            foreach ($collabs_stmt->fetchAll() as $c) {
                sendNotification($c['user_id'], $user_id, $proj_id, 'status_changed', $msg);
            }
            $toast = 'Status updated.|success';
        }

    // ── Add collaborator (owner only) ─────────────────────
    } elseif ($action === 'add_collab' && $is_owner) {
        $collab_email = trim($_POST['collab_email'] ?? '');
        $stmt = $db->prepare('SELECT id, name FROM users WHERE email=?');
        $stmt->execute([$collab_email]);
        $collab_user = $stmt->fetch();

        if (!$collab_user) {
            $toast = 'No active user found with that email.|error';
        } elseif ((int)$collab_user['id'] === $user_id) {
            $toast = 'You are already the project owner.|error';
        } else {
            $stmt = $db->prepare('SELECT id FROM project_collaborators WHERE project_id=? AND user_id=?');
            $stmt->execute([$proj_id, $collab_user['id']]);
            if ($stmt->fetch()) {
                $toast = htmlspecialchars($collab_user['name']) . ' is already a collaborator.|error';
            } else {
                $db->prepare('INSERT INTO project_collaborators (project_id, user_id) VALUES (?,?)')->execute([$proj_id, $collab_user['id']]);
                // Notify new collaborator
                $msg = $_SESSION['user_name'] . ' added you to "' . $project['title'] . '"';
                sendNotification($collab_user['id'], $user_id, $proj_id, 'added_to_project', $msg);
                $safe = str_replace('|','', htmlspecialchars($collab_user['name']));
                $toast = $safe . ' added as collaborator!|success';
            }
        }

    // ── Remove collaborator (owner only) ──────────────────
    } elseif ($action === 'remove_collab' && $is_owner) {
        $collab_id = (int) ($_POST['collab_id'] ?? 0);
        if ($collab_id !== $user_id) {
            // Notify removed user
            $stmt = $db->prepare('SELECT name FROM users WHERE id=?');
            $stmt->execute([$collab_id]);
            $removed = $stmt->fetch();
            $db->prepare('DELETE FROM project_collaborators WHERE project_id=? AND user_id=?')->execute([$proj_id, $collab_id]);
            $msg = 'You were removed from "' . $project['title'] . '"';
            sendNotification($collab_id, $user_id, $proj_id, 'removed_from_project', $msg);
            $toast = 'Collaborator removed.|success';
        }

    // ── Delete project (owner only) ───────────────────────
    } elseif ($action === 'delete_project' && $is_owner) {
        // Notify all collaborators before delete
        $collabs_stmt = $db->prepare('SELECT user_id FROM project_collaborators WHERE project_id=? AND user_id != ?');
        $collabs_stmt->execute([$proj_id, $user_id]);
        $msg = '"' . $project['title'] . '" was deleted by ' . $_SESSION['user_name'];
        foreach ($collabs_stmt->fetchAll() as $c) {
            sendNotification($c['user_id'], $user_id, $proj_id, 'project_deleted', $msg);
        }
        $db->prepare('DELETE FROM projects WHERE id=?')->execute([$proj_id]);
        header('Location: dashboard.php?toast=' . urlencode('Project deleted.|error'));
        exit;
    }

    header('Location: project.php?id=' . $proj_id . ($toast ? '&toast=' . urlencode($toast) : ''));
    exit;
}

// Carry toast
if (!empty($_GET['toast'])) $toast = $_GET['toast'];

// Refresh project after possible status update
$stmt = $db->prepare(
    'SELECT p.*, u.name AS owner_name FROM projects p JOIN users u ON u.id=p.owner_id WHERE p.id=?'
);
$stmt->execute([$proj_id]);
$project = $stmt->fetch();
$is_owner = ($project['owner_id'] == $user_id);

// Collaborators
$stmt = $db->prepare(
    'SELECT u.id, u.name, u.email, u.role FROM project_collaborators pc
     JOIN users u ON u.id=pc.user_id WHERE pc.project_id=? ORDER BY u.name ASC'
);
$stmt->execute([$proj_id]);
$collaborators = $stmt->fetchAll();

// All users for add collaborator datalist
$all_users = $db->query('SELECT id, name, email, role FROM users ORDER BY name ASC')->fetchAll();

$role_labels = ['speechwriter'=>'Speech Writer','ghostwriter'=>'Ghostwriter','copywriter'=>'Copywriter','journalist'=>'Journalist'];

function p_initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0],0,1));
    if (count($parts)>1) $i .= strtoupper(substr($parts[count($parts)-1],0,1));
    return $i;
}
function p_status_label(string $s): string {
    return match($s) { 'draft'=>'Draft','in_progress'=>'In Progress','review'=>'Review','complete'=>'Complete', default=>ucfirst($s) };
}
function p_type_label(string $t): string {
    return match($t) { 'essay'=>'Essay','journal'=>'Journal','speech'=>'Speech','article'=>'Article','blog'=>'Blog Post','report'=>'Report', default=>'Other' };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ame Writer — <?= htmlspecialchars($project['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-body">

<?php include 'nav.php'; ?>

<div class="app-body app-body-centered">
  <main class="main-content" style="max-width:800px;margin-left:auto;margin-right:auto">

    <!--Back + header-->
    <div class="page-header">
      <div>
        <a href="dashboard.php" class="btn-back-prominent" style="margin-bottom:.75rem;display:inline-flex">
          <svg><use href="#i-arrow-l"/></svg> Back to Dashboard
        </a>
        <h2><?= htmlspecialchars($project['title']) ?></h2>
        <p>
          <span class="badge type-<?= $project['type'] ?>"><?= p_type_label($project['type']) ?></span>
          &nbsp;
          <span class="badge status-<?= $project['status'] ?>"><?= p_status_label($project['status']) ?></span>
          &nbsp;
          <?php if ($project['is_solo']): ?>
          <span class="badge solo">Solo</span>
          <?php else: ?>
          <span class="badge collab-badge">Collaborative</span>
          <?php endif; ?>
        </p>
      </div>
      <?php if ($is_owner): ?>
      <button class="btn-icon" onclick="openDeleteModal()" title="Delete project" type="button"
              style="color:var(--red);border-color:rgba(220,38,38,.2)">
        <svg><use href="#i-trash"/></svg>
      </button>
      <?php endif; ?>
    </div>

    <?php if ($toast): [$tmsg,$ttype] = explode('|',$toast.'|'); ?>
    <div class="php-<?= trim($ttype)==='success'?'success':'error' ?>">
      <svg><use href="#i-<?= trim($ttype)==='success'?'check-c':'alert' ?>"/></svg>
      <?= htmlspecialchars($tmsg) ?>
    </div>
    <?php endif; ?>

    <!--Description-->
    <?php if ($project['description']): ?>
    <div class="project-detail-card">
      <div class="pdc-label">Description</div>
      <p style="font-size:14px;color:var(--ink-2);line-height:1.7"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
    </div>
    <?php endif; ?>

    <!--Meta info-->
    <div class="project-detail-card">
      <div class="pdc-label">Details</div>
      <div class="pdc-grid">
        <div class="pdc-item"><span class="pdc-key">Owner</span><span class="pdc-val"><?= htmlspecialchars($project['owner_name']) ?></span></div>
        <div class="pdc-item"><span class="pdc-key">Type</span><span class="pdc-val"><?= p_type_label($project['type']) ?></span></div>
        <div class="pdc-item"><span class="pdc-key">Status</span><span class="pdc-val"><?= p_status_label($project['status']) ?></span></div>
        <div class="pdc-item"><span class="pdc-key">Due Date</span><span class="pdc-val"><?= $project['due_date'] ? (new DateTime($project['due_date']))->format('M j, Y') : '—' ?></span></div>
        <div class="pdc-item"><span class="pdc-key">Created</span><span class="pdc-val"><?= (new DateTime($project['created_at']))->format('M j, Y') ?></span></div>
        <div class="pdc-item"><span class="pdc-key">Last Updated</span><span class="pdc-val"><?= !empty($project['updated_at']) ? (new DateTime($project['updated_at']))->format('M j, Y') : '—' ?></span></div>
      </div>
    </div>

    <!--Status update-->
    <?php if ($project['status'] !== 'complete' || $is_owner): ?>
    <div class="project-detail-card">
      <div class="pdc-label">Update Status</div>
      <form method="POST" action="project.php?id=<?= $proj_id ?>" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="action" value="update_status">
        <select name="status" style="flex:1;min-width:160px">
          <option value="draft"       <?= $project['status']==='draft'       ?'selected':'' ?>>Draft</option>
          <option value="in_progress" <?= $project['status']==='in_progress' ?'selected':'' ?>>In Progress</option>
          <option value="review"      <?= $project['status']==='review'      ?'selected':'' ?>>Review</option>
          <?php if ($is_owner): ?>
          <option value="complete"    <?= $project['status']==='complete'    ?'selected':'' ?>>Complete</option>
          <?php endif; ?>
        </select>
        <button class="btn-accent" type="submit" style="white-space:nowrap">Update Status</button>
      </form>
      <?php if (!$is_owner): ?>
      <p style="font-size:12px;color:var(--ink-3);margin-top:.5rem">Only the project owner can mark this as Complete.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!--Collaborators-->
    <?php if (!$project['is_solo']): ?>
    <div class="project-detail-card">
      <div class="pdc-label">Collaborators (<?= count($collaborators) ?>)</div>

      <!--Add collaborator (owner only)-->
      <?php if ($is_owner): ?>
      <form method="POST" action="project.php?id=<?= $proj_id ?>" style="display:flex;gap:.5rem;margin-bottom:1rem">
        <input type="hidden" name="action" value="add_collab">
        <input type="email" name="collab_email" placeholder="Add by email…"
               list="users-datalist" style="flex:1" />
        <button class="btn-accent" type="submit" style="white-space:nowrap;flex-shrink:0">
          <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2.5"><use href="#i-plus"/></svg> Add
        </button>
      </form>
      <datalist id="users-datalist">
        <?php foreach ($all_users as $u): if($u['id']==$user_id) continue; ?>
        <option value="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['name']) ?></option>
        <?php endforeach; ?>
      </datalist>
      <?php endif; ?>

      <!--Collaborator list-->
      <div class="collab-full-list">
        <?php foreach ($collaborators as $c): ?>
        <div class="collab-row">
          <div class="collab-avatar sm"><?= htmlspecialchars(p_initials($c['name'])) ?></div>
          <div class="collab-info">
            <span class="collab-name">
              <?= htmlspecialchars($c['name']) ?>
              <?php if ($c['id'] == $project['owner_id']): ?>
              <span style="font-size:11px;color:var(--accent);font-weight:600;margin-left:4px">Owner</span>
              <?php endif; ?>
            </span>
            <span class="collab-role"><?= htmlspecialchars($role_labels[$c['role']] ?? ucfirst($c['role'])) ?> · <?= htmlspecialchars($c['email']) ?></span>
          </div>
          <?php if ($is_owner && $c['id'] != $user_id): ?>
          <form method="POST" action="project.php?id=<?= $proj_id ?>" style="margin-left:auto">
            <input type="hidden" name="action"    value="remove_collab">
            <input type="hidden" name="collab_id" value="<?= $c['id'] ?>">
            <button class="btn-icon" type="submit" title="Remove"
                    style="color:var(--red);border-color:rgba(220,38,38,.2)">
              <svg><use href="#i-x"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<!--Delete modal-->
<div class="modal-backdrop" id="delete-modal">
  <div class="modal" style="max-width:380px" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h3>Delete Project</h3>
      <button class="modal-close" onclick="closeModal('delete-modal')" type="button"><svg><use href="#i-x"/></svg></button>
    </div>
    <div class="confirm-body">
      <div class="confirm-icon"><svg><use href="#i-warn"/></svg></div>
      <p>Are you sure you want to delete <strong>"<?= htmlspecialchars($project['title']) ?>"</strong>? This cannot be undone.</p>
    </div>
    <form method="POST" action="project.php?id=<?= $proj_id ?>">
      <input type="hidden" name="action" value="delete_project">
      <div class="modal-footer">
        <button class="btn-ghost" onclick="closeModal('delete-modal')" type="button">Cancel</button>
        <button class="btn-danger" type="submit">
          <svg style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2"><use href="#i-trash"/></svg> Delete
        </button>
      </div>
    </form>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openDeleteModal() { openModal('delete-modal'); }
document.querySelectorAll('.modal-backdrop').forEach(el => {
  el.addEventListener('click', e => { if (e.target===el) el.classList.remove('open'); });
});
</script>
</body>
</html>
