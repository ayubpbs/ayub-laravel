<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the audit trail. Failure-safe like Notify: a problem while writing the log
 * must never stop the real action.
 */
class Audit
{
    public static function log(
        string $action,
        string $subjectType,
        ?int $subjectId,
        string $summary,
        ?array $old = null,
        ?array $new = null,
        ?string $customerPhone = null
    ): void {
        try {
            if (!AuditLog::tableReady()) {
                return;
            }

            $staff = AdminAccess::staff();

            AuditLog::create([
                'actor_id'       => $staff ? $staff->id : null,
                'actor_name'     => AdminAccess::name(),
                'action'         => $action,
                'subject_type'   => $subjectType,
                'subject_id'     => $subjectId,
                'customer_phone' => $customerPhone,
                'summary'        => Str::limit($summary, 250, '…'),
                'old_values'     => $old,
                'new_values'     => $new,
                'ip'             => request()->ip(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit log not saved: ' . $e->getMessage());
        }
    }
}
