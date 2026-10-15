<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell in the staff area. Each user only ever sees and changes their own notifications.
 */
class NotificationController extends Controller
{
    public const SHOWN = 20;

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json($this->payload($request));
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json($this->payload($request));
    }

    /**
     * @return array{unread: int, items: list<array<string, mixed>>}
     */
    private function payload(Request $request): array
    {
        $user = $request->user();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(self::SHOWN)
                ->get()
                ->map(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
