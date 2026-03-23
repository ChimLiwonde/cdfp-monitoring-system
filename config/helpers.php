<?php

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

if (!function_exists('panelTitleForRole')) {
    function panelTitleForRole($role)
    {
        switch ($role) {
            case 'field_officer':
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
