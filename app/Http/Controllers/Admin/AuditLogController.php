<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** GET /api/admin/audit-logs?table=&action=&record_id= – the change log, newest first. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AdminAuditLog::with('user:id,name,email')
                ->when($request->query('table'), fn ($q, $t) => $q->where('table_name', $t))
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
                ->when($request->query('record_id'), fn ($q, $id) => $q->where('record_id', $id))
                ->orderByDesc('id')
                ->paginate(50)
        );
    }
}
