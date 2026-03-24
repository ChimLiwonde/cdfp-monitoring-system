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
