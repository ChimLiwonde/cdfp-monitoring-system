<?php
session_start();
require "../config/db.php";
require_once __DIR__ . '/../config/helpers.php';

$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'field_officer'], true)) {
    header("Location: ../Pages/login.php");
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$selected_project_id = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$message = '';

if (isset($_POST['send_message'])) {
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
        $messages[] = $row;
    }
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$menu_file = $role === 'admin' ? 'adminmenu.php' : 'menu.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project Collaboration</title>
    <link rel="stylesheet" href="../assets/css/flexible.css">
    <style>
        .chat-thread {
            background: #f8fbfe;
            border: 1px solid #d6eafc;
            border-radius: 12px;
            padding: 18px;
            margin-top: 20px;
        }
        .chat-item {
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
            background: #ffffff;
            border: 1px solid #dfeaf5;
        }
        .chat-role-admin {
            border-left: 4px solid #1565c0;
        }
        .chat-role-field {
            border-left: 4px solid #2e7d32;
        }
        .chat-meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

<?php include "header.php"; ?>

<div class="row">
    <div class="col-3"><?php include $menu_file; ?></div>
    <div class="col-9">
        <div class="form-card">
            <h3>Internal Project Collaboration</h3>
            <p>This channel is private to admins and field officers. Public comments stay in the citizen feedback pages.</p>

            <?php if ($success_message): ?>
                <div class="msg"><?= htmlspecialchars($success_message) ?></div>
            <?php elseif ($message !== ''): ?>
                <div class="msg"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="GET" style="margin-bottom:20px;">
                <label>Select Project</label>
                <select name="project_id" onchange="this.form.submit()" required>
                    <option value="">-- Select Project --</option>
                    <?php while ($project = $projects->fetch_assoc()): ?>
                        <option value="<?= $project['id'] ?>" <?= $project['id'] === $selected_project_id ? 'selected' : '' ?>>
                            <?php
                            $label = formatProjectCode($project['id']) . ' - ' . $project['title'] . ' (' . formatStatusLabel($project['status']) . ')';
                            if ($role === 'admin' && isset($project['field_officer'])) {
                                $label .= ' | Officer: ' . $project['field_officer'];
                            }
                            ?>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>

            <?php if ($selected_project): ?>
                <div class="form-card" style="margin:0 0 20px 0;">
                    <h4><?= formatProjectCode($selected_project['id']) ?> - <?= htmlspecialchars($selected_project['title']) ?></h4>
                    <p><strong>Status:</strong> <?= htmlspecialchars(formatStatusLabel($selected_project['status'])) ?></p>
                    <p><strong>District:</strong> <?= htmlspecialchars($selected_project['district']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($selected_project['location']) ?></p>
                    <p><strong>Field Officer:</strong> <?= htmlspecialchars($selected_project['field_officer']) ?></p>
                </div>

                <div class="form-card" style="margin-top:0;">
                    <h4>Post Internal Message</h4>
                    <form method="POST">
                        <input type="hidden" name="project_id" value="<?= $selected_project['id'] ?>">
                        <textarea name="message" style="width:100%;min-height:120px;padding:12px;border:1px solid #90caf9;border-radius:8px;" placeholder="Share status updates, coordination notes, or budget concerns here." required></textarea>
                        <input type="submit" name="send_message" value="Send Message">
                    </form>
                </div>

                <div class="chat-thread">
                    <h4>Conversation</h4>
                    <?php if (count($messages) === 0): ?>
                        <p>No internal messages yet.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $chat): ?>
                            <?php $chatClass = $chat['sender_role'] === 'admin' ? 'chat-role-admin' : 'chat-role-field'; ?>
                            <div class="chat-item <?= $chatClass ?>">
                                <div class="chat-meta">
                                    <?= htmlspecialchars($chat['username']) ?> (<?= htmlspecialchars(formatStatusLabel($chat['sender_role'])) ?>)
                                    | <?= date("d M Y, H:i", strtotime($chat['created_at'])) ?>
                                </div>
                                <div><?= nl2br(htmlspecialchars($chat['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p>Select a project to view or post private collaboration messages.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>

</body>
</html>
