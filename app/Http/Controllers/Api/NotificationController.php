<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = Notification::where('user_id', $request->user()->id)->latest()->limit(50)->get();
        return response()->json($items);
    }
    public function markRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read']);
    }
}
