<?php
require_once __DIR__ . '/../config/helpers.php';
startSecureSession();
require "../config/db.php";

$role = $_SESSION['role'] ?? null;
if ($role !== 'admin' && !isProjectLeadRole($role)) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$selected_project_id = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$message = '';

if (isset($_POST['send_message'])) {
    if (!isValidCsrfToken('project_collaboration_form', $_POST['_csrf_token'] ?? '')) {
        $message = "Your session expired. Please try posting the message again.";
    } else {
        $selected_project_id = (int) ($_POST['project_id'] ?? 0);
        $chat_message = trim($_POST['message'] ?? '');

        if ($selected_project_id <= 0 || $chat_message === '') {
            $message = "Select a project and enter a message.";
        } else {
            if ($role === 'admin') {
                $project_stmt = $conn->prepare("
                    SELECT id, title, created_by, status
                    FROM projects
                    WHERE id = ?
                ");
                $project_stmt->bind_param("i", $selected_project_id);
            } else {
                $project_stmt = $conn->prepare("
                    SELECT id, title, created_by, status
                    FROM projects
                    WHERE id = ? AND created_by = ?
                ");
                $project_stmt->bind_param("ii", $selected_project_id, $user_id);
            }

            $project_stmt->execute();
            $project = $project_stmt->get_result()->fetch_assoc();

            if (!$project) {
                $message = "The selected project was not found.";
            } else {
                $insert = $conn->prepare("
                    INSERT INTO project_collaboration_messages (project_id, sender_id, sender_role, message)
                    VALUES (?, ?, ?, ?)
                ");
                $insert->bind_param("iiss", $selected_project_id, $user_id, $role, $chat_message);

                if ($insert->execute()) {
                    logProjectActivity(
                        $conn,
                        $selected_project_id,
                        'collaboration_message_posted',
                        $user_id,
                        $role,
                        null,
                        null,
                        'Internal collaboration message posted.'
                    );

                    $_SESSION['success_message'] = "Internal collaboration message posted for " . formatProjectCode($selected_project_id) . ".";
                    header("Location: project_collaboration.php?project_id={$selected_project_id}");
                    exit();
                }

                $message = "Failed to send the collaboration message.";
            }
        }
    }
}

if ($role === 'admin') {
    $projects_query = "
        SELECT p.id, p.title, p.status, u.username AS field_officer
        FROM projects p
        JOIN users u ON u.id = p.created_by
        ORDER BY p.created_at DESC
    ";
    $projects = $conn->query($projects_query);
} else {
    $projects_stmt = $conn->prepare("
        SELECT id, title, status
        FROM projects
        WHERE created_by = ?
        ORDER BY created_at DESC
    ");
    $projects_stmt->bind_param("i", $user_id);
    $projects_stmt->execute();
    $projects = $projects_stmt->get_result();
}

$available_project_count = $projects ? $projects->num_rows : 0;
$selected_project = null;

if ($selected_project_id > 0) {
    if ($role === 'admin') {
        $selected_stmt = $conn->prepare("
            SELECT p.id, p.title, p.status, p.district, p.location, u.username AS field_officer
            FROM projects p
            JOIN users u ON u.id = p.created_by
            WHERE p.id = ?
        ");
        $selected_stmt->bind_param("i", $selected_project_id);
    } else {
        $selected_stmt = $conn->prepare("
            SELECT p.id, p.title, p.status, p.district, p.location, u.username AS field_officer
            FROM projects p
            JOIN users u ON u.id = p.created_by
            WHERE p.id = ? AND p.created_by = ?
        ");
        $selected_stmt->bind_param("ii", $selected_project_id, $user_id);
    }
    $selected_stmt->execute();
    $selected_project = $selected_stmt->get_result()->fetch_assoc();
}

$messages = [];
$admin_message_count = 0;
$lead_message_count = 0;

if ($selected_project) {
    $messages_stmt = $conn->prepare("
        SELECT pcm.message, pcm.sender_role, pcm.created_at, u.username
        FROM project_collaboration_messages pcm
        JOIN users u ON u.id = pcm.sender_id
        WHERE pcm.project_id = ?
        ORDER BY pcm.created_at ASC, pcm.id ASC
    ");
    $messages_stmt->bind_param("i", $selected_project_id);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();

    while ($row = $messages_result->fetch_assoc()) {
        if ($row['sender_role'] === 'admin') {
            $admin_message_count++;
        } else {
            $lead_message_count++;
        }
        $messages[] = $row;
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$menu_file = $role === 'admin' ? 'adminmenu.php' : 'menu.php';
$project_details_link = $selected_project
    ? ($role === 'admin'
        ? "admin_project_details.php?id=" . (int) $selected_project['id']
        : "view_project_details.php?id=" . (int) $selected_project['id'])
    : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Collaboration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/flexible.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include $menu_file; ?></div>
    <div class="col-9 dashboard-main">
        <div class="form-card page-hero">
            <div class="page-hero__grid">
                <div class="page-hero__copy">
                    <span class="eyebrow">Internal Collaboration</span>
                    <h3>Coordinate project work in one private channel</h3>
                    <p>This space is reserved for admins and project leads so budget concerns, coordination notes, and delivery decisions stay separate from public feedback.</p>
                    <?php if ($selected_project): ?>
                        <div class="hero-actions">
                            <a href="<?= htmlspecialchars($project_details_link) ?>" class="back-btn">Open Project Details</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero-pills">
                    <div class="hero-pill"><strong><?= $available_project_count ?></strong>&nbsp; Projects</div>
                    <div class="hero-pill"><strong><?= count($messages) ?></strong>&nbsp; Messages</div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <?php if ($success_message): ?>
                <div class="msg success"><?= htmlspecialchars($success_message) ?></div>
            <?php elseif ($message !== ''): ?>
                <div class="msg error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="section-header">
                <div>
                    <span class="section-kicker">Workspace Selection</span>
                    <h3>Choose a Project Thread</h3>
                </div>
                <p>Select a project to open its private conversation stream and keep coordination tied to the right record.</p>
            </div>

            <form method="GET">
                <div class="form-grid">
                    <div class="full-span">
                        <label for="project_id">Select Project</label>
                        <select id="project_id" name="project_id" onchange="this.form.submit()" required>
                            <option value="">-- Select Project --</option>
                            <?php while ($project = $projects->fetch_assoc()): ?>
                                <option value="<?= (int) $project['id'] ?>" <?= (int) $project['id'] === $selected_project_id ? 'selected' : '' ?>>
                                    <?php
                                    $label = formatProjectCode($project['id']) . ' - ' . $project['title'] . ' (' . formatStatusLabel($project['status']) . ')';
                                    if ($role === 'admin' && isset($project['field_officer'])) {
                                        $label .= ' | Project Lead: ' . $project['field_officer'];
                                    }
                                    ?>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($selected_project): ?>
            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Selected Project</span>
                        <h3><?= formatProjectCode($selected_project['id']) ?> - <?= htmlspecialchars($selected_project['title']) ?></h3>
                    </div>
                    <p>Use the thread below for internal coordination linked directly to this project.</p>
                </div>

                <div class="detail-grid">
                    <div class="detail-card">
                        <strong>Status</strong>
                        <span class="status-badge <?= htmlspecialchars($selected_project['status']) ?>"><?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>District</strong>
                        <span><?= htmlspecialchars($selected_project['district']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Location</strong>
                        <span><?= htmlspecialchars($selected_project['location']) ?></span>
                    </div>
                    <div class="detail-card">
                        <strong>Project Lead</strong>
                        <span><?= htmlspecialchars($selected_project['field_officer']) ?></span>
                    </div>
                </div>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Post Update</span>
                        <h3>Send Internal Message</h3>
                    </div>
                    <p>Share project progress, assignment follow-ups, or risks that should stay within the delivery team.</p>
                </div>

                <form method="POST">
                    <?= csrfInput('project_collaboration_form') ?>
                    <input type="hidden" name="project_id" value="<?= (int) $selected_project['id'] ?>">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" placeholder="Share status updates, coordination notes, or budget concerns here." required></textarea>
                    <div class="hero-actions">
                        <input type="submit" name="send_message" value="Send Message">
                    </div>
                </form>
            </div>

            <div class="data-card">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">Conversation</span>
                        <h3>Project Thread</h3>
                    </div>
                    <p><?= $admin_message_count ?> admin messages and <?= $lead_message_count ?> project-lead messages are currently recorded for this workspace.</p>
                </div>

                <?php if (count($messages) === 0): ?>
                    <div class="empty-state">No internal messages yet. Start the thread with your first project update.</div>
                <?php else: ?>
                    <div class="chat-thread">
                        <?php foreach ($messages as $chat): ?>
                            <?php $chatClass = $chat['sender_role'] === 'admin' ? 'chat-role-admin' : 'chat-role-field'; ?>
                            <div class="chat-item <?= $chatClass ?>">
                                <div class="chat-meta">
                                    <?= htmlspecialchars($chat['username']) ?> (<?= htmlspecialchars(formatRoleLabel($chat['sender_role'])) ?>)
                                    | <?= date("d M Y, H:i", strtotime($chat['created_at'])) ?>
                                </div>
                                <div><?= nl2br(htmlspecialchars($chat['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="data-card">
                <div class="empty-state">Select a project to open its private collaboration thread.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
