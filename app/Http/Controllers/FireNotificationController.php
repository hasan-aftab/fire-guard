<?php

namespace App\Http\Controllers;

use App\Actions\SyncFireNotifications;
use App\Models\FireNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FireNotificationController extends Controller
{
    public function store(Request $request, SyncFireNotifications $notifications): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $notifications->handle($user);

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($user));
        }

        return back();
    }

    public function update(Request $request, FireNotification $notification): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $notification->user_id === $user->id, 404);
        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($user));
        }

        return to_route(match ($notification->entity_type) {
            'asset' => 'assets.index',
            'certificate' => 'certificates.index',
            default => 'inspections.index',
        });
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        FireNotification::where('user_id', $user->id)->where('is_read', false)->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($user));
        }

        return back();
    }

    /** @return array{items: Collection<int, FireNotification>, unreadCount: int} */
    private function snapshot(User $user): array
    {
        return [
            'items' => FireNotification::where('user_id', $user->id)->latest()->orderByDesc('id')->limit(50)->get(),
            'unreadCount' => FireNotification::where('user_id', $user->id)->where('is_read', false)->count(),
        ];
    }
}
