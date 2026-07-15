// app/Services/AuditService.php
<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log($userId, $action, $module, $data = [], $oldData = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'old_values' => $oldData ? json_encode($oldData) : null,
            'new_values' => $data ? json_encode($data) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    public function getLogs($userId = null, $module = null, $startDate = null, $endDate = null)
    {
        $query = AuditLog::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($module) {
            $query->where('module', $module);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query->orderBy('created_at', 'desc')->paginate(50);
    }
}
