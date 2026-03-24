<?php

if (!function_exists('startSecureSession')) {
    function startSecureSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!headers_sent()) {
            $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }
}

if (!function_exists('pullSessionMessage')) {
    function pullSessionMessage($key)
    {
        if (!isset($_SESSION[$key])) {
            return '';
        }

        $message = $_SESSION[$key];
        unset($_SESSION[$key]);

        return (string) $message;
    }
}

if (!function_exists('getCsrfToken')) {
    function getCsrfToken($scope = 'default')
    {
        if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens'])) {
            $_SESSION['_csrf_tokens'] = [];
        }

        if (empty($_SESSION['_csrf_tokens'][$scope])) {
            $_SESSION['_csrf_tokens'][$scope] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_tokens'][$scope];
    }
}

if (!function_exists('csrfInput')) {
    function csrfInput($scope = 'default')
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(getCsrfToken($scope), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('isValidCsrfToken')) {
    function isValidCsrfToken($scope, $submittedToken)
    {
        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        if (empty($_SESSION['_csrf_tokens'][$scope])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_tokens'][$scope], $submittedToken);
    }
}

if (!function_exists('normalizeCoordinate')) {
    function normalizeCoordinate($value, $min, $max, $precision = 6)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $value = (float) $value;
        if ($value < $min || $value > $max) {
            return null;
        }

        return number_format($value, $precision, '.', '');
    }
}

if (!function_exists('hasValidCoordinatePair')) {
    function hasValidCoordinatePair($latitude, $longitude)
    {
        return normalizeCoordinate($latitude, -90, 90) !== null
            && normalizeCoordinate($longitude, -180, 180) !== null;
    }
}

if (!function_exists('getProjectDocumentStorageDir')) {
    function getProjectDocumentStorageDir()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private_uploads';
    }
}

if (!function_exists('getLegacyProjectDocumentStorageDir')) {
    function getLegacyProjectDocumentStorageDir()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    }
}

if (!function_exists('storeProjectDocumentUpload')) {
    function storeProjectDocumentUpload(array $file, &$error = null)
    {
        $error = null;

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'The file upload failed. Please try again.';
            return false;
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $error = 'The uploaded file could not be verified.';
            return false;
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $error = 'The file is too large. Upload a file under 5 MB.';
            return false;
        }

        $allowedMimeTypes = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        ];

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($file['tmp_name']);

        if (!isset($allowedMimeTypes[$mimeType])) {
            $error = 'Only PDF, JPG, JPEG, and PNG files are allowed.';
            return false;
        }

        if (!in_array($extension, $allowedMimeTypes[$mimeType], true)) {
            $error = 'The uploaded file type does not match its extension.';
            return false;
        }

        $storageDir = getProjectDocumentStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            $error = 'The upload directory is not available.';
            return false;
        }

        $storedExtension = $allowedMimeTypes[$mimeType][0];
        $storedFileName = bin2hex(random_bytes(16)) . '.' . $storedExtension;
        $destination = $storageDir . DIRECTORY_SEPARATOR . $storedFileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = 'The uploaded file could not be saved.';
            return false;
        }

        return $storedFileName;
    }
}

if (!function_exists('getProjectDocumentAbsolutePath')) {
    function getProjectDocumentAbsolutePath($storedFileName)
    {
        $storedFileName = basename((string) $storedFileName);
        if ($storedFileName === '') {
            return null;
        }

        $candidatePaths = [
            getProjectDocumentStorageDir() . DIRECTORY_SEPARATOR . $storedFileName,
            getLegacyProjectDocumentStorageDir() . DIRECTORY_SEPARATOR . $storedFileName,
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }
}

if (!function_exists('getProjectDocumentMimeType')) {
    function getProjectDocumentMimeType($absolutePath)
    {
        if (!$absolutePath || !is_file($absolutePath)) {
            return 'application/octet-stream';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return (string) ($finfo->file($absolutePath) ?: 'application/octet-stream');
    }
}

if (!function_exists('canPublicCommentOnProject')) {
    function canPublicCommentOnProject($conn, $userId, $projectId)
    {
        $userId = (int) $userId;
        $projectId = (int) $projectId;

        if ($userId <= 0 || $projectId <= 0) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT p.id
            FROM projects p
            JOIN users u ON u.id = ?
            WHERE p.id = ?
              AND LOWER(p.district) = LOWER(u.location)
              AND p.status IN ('pending', 'approved')
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $userId, $projectId);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }
}

if (!function_exists('canPublicReactToComment')) {
    function canPublicReactToComment($conn, $userId, $commentId)
    {
        $userId = (int) $userId;
        $commentId = (int) $commentId;

        if ($userId <= 0 || $commentId <= 0) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT pc.id
            FROM project_comments pc
            JOIN projects p ON p.id = pc.project_id
            JOIN users u ON u.id = ?
            WHERE pc.id = ?
              AND LOWER(p.district) = LOWER(u.location)
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $userId, $commentId);
        $stmt->execute();

        return $stmt->get_result()->num_rows > 0;
    }
}

if (!function_exists('formatProjectCode')) {
    function formatProjectCode($projectId)
    {
        $projectId = (int) $projectId;

        if ($projectId <= 0) {
            return 'CDFP-0000';
        }

        return sprintf('CDFP-%04d', $projectId);
    }
}

if (!function_exists('isProjectLeadRole')) {
    function isProjectLeadRole($role)
    {
        return in_array($role, ['field_officer', 'project_manager'], true);
    }
}

if (!function_exists('formatRoleLabel')) {
    function formatRoleLabel($role)
    {
        $labels = [
            'admin' => 'Admin',
            'public' => 'Citizen',
            'field_officer' => 'Field Officer',
            'project_manager' => 'Project Manager',
        ];

        return $labels[$role] ?? ucwords(str_replace('_', ' ', (string) $role));
    }
}

if (!function_exists('formatNotificationTypeLabel')) {
    function formatNotificationTypeLabel($type)
    {
        $labels = [
            'project_submitted' => 'Project Submitted',
            'project_reviewed' => 'Project Reviewed',
            'community_request_submitted' => 'Community Request Submitted',
            'community_request_reviewed' => 'Community Request Reviewed',
            'comment_replied' => 'Comment Replied',
        ];

        return $labels[$type] ?? ucwords(str_replace('_', ' ', (string) $type));
    }
}

if (!function_exists('panelTitleForRole')) {
    function panelTitleForRole($role)
    {
        switch ($role) {
            case 'field_officer':
            case 'project_manager':
                return 'Project Panel';
            case 'public':
                return 'Citizen Panel';
            case 'admin':
                return 'Admin Panel';
            default:
                return ucfirst(str_replace('_', ' ', (string) $role)) . ' Panel';
        }
    }
}

if (!function_exists('dashboardFileForRole')) {
    function dashboardFileForRole($role)
    {
        switch ($role) {
            case 'admin':
                return 'admin.php';
            case 'field_officer':
            case 'project_manager':
                return 'field_officer.php';
            case 'public':
                return 'public.php';
            default:
                return null;
        }
    }
}

if (!function_exists('formatStatusLabel')) {
    function formatStatusLabel($status)
    {
        if ($status === null || $status === '') {
            return 'N/A';
        }

        return ucwords(str_replace('_', ' ', (string) $status));
    }
}

if (!function_exists('formatActivityLabel')) {
    function formatActivityLabel($eventType)
    {
        $labels = [
            'project_created' => 'Project Created',
            'project_status_changed' => 'Project Status Changed',
            'status_item_added' => 'Status Item Added',
            'stage_status_changed' => 'Status Item Updated',
            'contractor_assigned' => 'Contractor Assigned',
            'expense_recorded' => 'Expense Recorded',
            'team_member_added' => 'Team Member Added',
            'task_assigned' => 'Task Assigned',
            'collaboration_message_posted' => 'Collaboration Message Posted',
        ];

        return $labels[$eventType] ?? ucwords(str_replace('_', ' ', (string) $eventType));
    }
}

if (!function_exists('logProjectActivity')) {
    function logProjectActivity($conn, $projectId, $eventType, $actorId = null, $actorRole = null, $oldStatus = null, $newStatus = null, $notes = null)
    {
        $projectId = (int) $projectId;

        if ($projectId <= 0) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO project_activity_log
            (project_id, event_type, actor_id, actor_role, old_status, new_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return false;
        }

        $actorId = $actorId !== null ? (int) $actorId : null;
        $stmt->bind_param(
            "isissss",
            $projectId,
            $eventType,
            $actorId,
            $actorRole,
            $oldStatus,
            $newStatus,
            $notes
        );

        return $stmt->execute();
    }
}

if (!function_exists('createUserNotification')) {
    function createUserNotification($conn, $userId, $type, $title, $message, $link = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO user_notifications (user_id, notification_type, title, message, link)
                VALUES (?, ?, ?, ?, ?)
            ");
        } catch (Throwable $e) {
            return false;
        }

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("issss", $userId, $type, $title, $message, $link);
        try {
            return $stmt->execute();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('createRoleNotifications')) {
    function createRoleNotifications($conn, $role, $type, $title, $message, $link = null, $excludeUserId = null)
    {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE role = ?");
        } catch (Throwable $e) {
            return 0;
        }

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("s", $role);
        try {
            $stmt->execute();
            $result = $stmt->get_result();
        } catch (Throwable $e) {
            return 0;
        }

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $targetUserId = (int) $row['id'];
            if ($excludeUserId !== null && $targetUserId === (int) $excludeUserId) {
                continue;
            }

            if (createUserNotification($conn, $targetUserId, $type, $title, $message, $link)) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount($conn, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return 0;
        }

        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM user_notifications
                WHERE user_id = ? AND is_read = 0
            ");
        } catch (Throwable $e) {
            return 0;
        }

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("i", $userId);
        try {
            $stmt->execute();
            return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('determineProjectStatusFromStages')) {
    function determineProjectStatusFromStages($conn, $projectId, $fallbackStatus = 'approved')
    {
        $projectId = (int) $projectId;

        if ($projectId <= 0) {
            return $fallbackStatus;
        }

        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) AS total,
                SUM(status = 'completed') AS completed,
                SUM(status = 'in_progress') AS in_progress
            FROM project_stages
            WHERE project_id = ?
        ");
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();

        if ((int) $summary['total'] === 0) {
            return $fallbackStatus;
        }

        if ((int) $summary['completed'] === (int) $summary['total']) {
            return 'completed';
        }

        if ((int) $summary['in_progress'] > 0 || (int) $summary['completed'] > 0) {
            return 'in_progress';
        }

        return 'approved';
    }
}

if (!function_exists('syncStageSpentBudget')) {
    function syncStageSpentBudget($conn, $stageId)
    {
        $stageId = (int) $stageId;

        if ($stageId <= 0) {
            return 0.0;
        }

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_spent
            FROM project_expenses
            WHERE stage_id = ?
        ");

        if (!$stmt) {
            return 0.0;
        }

        $stmt->bind_param("i", $stageId);
        $stmt->execute();
        $spent = (float) ($stmt->get_result()->fetch_assoc()['total_spent'] ?? 0);

        $update = $conn->prepare("UPDATE project_stages SET spent_budget = ? WHERE id = ?");
        if ($update) {
            $update->bind_param("di", $spent, $stageId);
            $update->execute();
        }

        return $spent;
    }
}

if (!function_exists('getProjectBudgetSummary')) {
    function getProjectBudgetSummary($conn, $projectId)
    {
        $summary = [
            'project_id' => (int) $projectId,
            'estimated_budget' => 0.0,
            'contractor_fee' => 0.0,
            'total_budget' => 0.0,
            'allocated_total' => 0.0,
            'spent_total' => 0.0,
            'remaining_budget' => 0.0,
            'remaining_allocatable_budget' => 0.0,
            'is_over_budget' => false,
            'is_over_allocated' => false,
        ];

        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return $summary;
        }

        $stmt = $conn->prepare("
            SELECT
                p.estimated_budget,
                p.contractor_fee,
                COALESCE(
                    (SELECT SUM(ps.allocated_budget) FROM project_stages ps WHERE ps.project_id = p.id),
                    0
                ) AS allocated_total,
                COALESCE(
                    (SELECT SUM(pe.amount) FROM project_expenses pe WHERE pe.project_id = p.id),
                    0
                ) AS spent_total
            FROM projects p
            WHERE p.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return $summary;
        }

        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return $summary;
        }

        $summary['estimated_budget'] = (float) $row['estimated_budget'];
        $summary['contractor_fee'] = (float) $row['contractor_fee'];
        $summary['total_budget'] = $summary['estimated_budget'] + $summary['contractor_fee'];
        $summary['allocated_total'] = (float) $row['allocated_total'];
        $summary['spent_total'] = (float) $row['spent_total'];
        $summary['remaining_budget'] = $summary['total_budget'] - $summary['spent_total'];
        $summary['remaining_allocatable_budget'] = $summary['total_budget'] - $summary['allocated_total'];
        $summary['is_over_budget'] = $summary['remaining_budget'] < 0;
        $summary['is_over_allocated'] = $summary['remaining_allocatable_budget'] < 0;

        return $summary;
    }
}

if (!function_exists('getStageBudgetSummary')) {
    function getStageBudgetSummary($conn, $stageId)
    {
        $summary = [
            'stage_id' => (int) $stageId,
            'project_id' => 0,
            'stage_name' => '',
            'status' => '',
            'allocated_budget' => 0.0,
            'spent_total' => 0.0,
            'remaining_budget' => 0.0,
            'is_over_budget' => false,
        ];

        $stageId = (int) $stageId;
        if ($stageId <= 0) {
            return $summary;
        }

        $stmt = $conn->prepare("
            SELECT
                ps.project_id,
                ps.stage_name,
                ps.status,
                ps.allocated_budget,
                COALESCE(
                    (SELECT SUM(pe.amount) FROM project_expenses pe WHERE pe.stage_id = ps.id),
                    0
                ) AS spent_total
            FROM project_stages ps
            WHERE ps.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return $summary;
        }

        $stmt->bind_param("i", $stageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return $summary;
        }

        $summary['project_id'] = (int) $row['project_id'];
        $summary['stage_name'] = (string) $row['stage_name'];
        $summary['status'] = (string) $row['status'];
        $summary['allocated_budget'] = (float) $row['allocated_budget'];
        $summary['spent_total'] = (float) $row['spent_total'];
        $summary['remaining_budget'] = $summary['allocated_budget'] - $summary['spent_total'];
        $summary['is_over_budget'] = $summary['remaining_budget'] < 0;

        return $summary;
    }
}

if (!function_exists('normalizeReportType')) {
    function normalizeReportType($type, $default = 'progress')
    {
        $allowed = ['progress', 'financial'];
        return in_array($type, $allowed, true) ? $type : $default;
    }
}

if (!function_exists('formatReportTypeLabel')) {
    function formatReportTypeLabel($type)
    {
        return normalizeReportType($type) === 'financial' ? 'Financial Report' : 'Progress Report';
    }
}

if (!function_exists('calculateProgressPercent')) {
    function calculateProgressPercent($totalItems, $completedItems, $activeItems)
    {
        $totalItems = (int) $totalItems;
        $completedItems = (int) $completedItems;
        $activeItems = (int) $activeItems;

        if ($totalItems <= 0) {
            return 0;
        }

        $progressUnits = $completedItems + ($activeItems * 0.5);
        return (int) round(($progressUnits / $totalItems) * 100);
    }
}
