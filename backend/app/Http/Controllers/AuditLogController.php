<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'method' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status_code' => ['nullable', 'integer', 'between:100,599'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email,role')
            ->latest('created_at');

        foreach (['user_id', 'method', 'status_code'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $query->where($field, $validated[$field]);
            }
        }

        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $logs = $query->paginate(50);

        $logs->getCollection()->each(function (AuditLog $log) {
            $log->setAttribute('integrity_valid', $log->integriteValide());
        });

        return response()->json([
            'audit_logs' => $logs,
        ]);
    }
}
