<?php

if (!function_exists('admin_audit_log')) {
    function admin_audit_log($action, $targetType, $targetId = null, $details = array())
    {
        if (function_exists('appLog')) {
            appLog('admin_action', 'Structured admin audit event', array(
                'admin_id' => $_SESSION['user_id'] ?? null,
                'admin_role' => $_SESSION['role'] ?? null,
                'admin_name' => $_SESSION['name'] ?? null,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ));
        }
    }
}