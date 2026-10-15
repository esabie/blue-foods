<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'entity' => ['nullable', 'string', 'max:50'],
            'entity_id' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::with('user:id,name')
            ->when($filters['entity'] ?? null, fn ($q, $entity) => $q->where('entity', $entity))
            ->when($filters['entity_id'] ?? null, fn ($q, $id) => $q->where('entity_id', $id))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'System',
                'entity' => $log->entity,
                'entity_id' => $log->entity_id,
                'action' => $log->action,
                'before' => $log->before_json,
                'after' => $log->after_json,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'entities' => AuditLog::query()->distinct()->orderBy('entity')->pluck('entity'),
            'filters' => $filters,
        ]);
    }
}
