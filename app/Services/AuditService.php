<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function log(string $action, $model = null, array $oldValues = null, array $newValues = null): AuditLog
    {
        $user = Auth::user();
        
        $logData = [
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Redact sensitive data
        if ($newValues) {
            $logData['new_values'] = $this->redactSensitiveData($newValues);
        }

        if ($oldValues) {
            $logData['old_values'] = $this->redactSensitiveData($oldValues);
        }

        $auditLog = AuditLog::create($logData);

        Log::info('Audit log created', [
            'action' => $action,
            'user_id' => $user ? $user->id : null,
            'model' => $model ? get_class($model) : null,
        ]);

        return $auditLog;
    }

    protected function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'api_key', 'secret', 'token', 'credit_card',
            'ssn', 'social_security', 'bank_account', 'routing_number'
        ];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (str_contains(strtolower($key), $sensitiveKey)) {
                        $data[$key] = '***REDACTED***';
                        break;
                    }
                }
            }
        }

        return $data;
    }

    public function getUserActivity(User $user, int $limit = 50)
    {
        return AuditLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSystemActivity(int $limit = 100)
    {
        return AuditLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}