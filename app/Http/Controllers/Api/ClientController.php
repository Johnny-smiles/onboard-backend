<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Client::query()
            ->addSelect([
                'latest_photo_uploaded_at' => Photo::select('created_at')
                    ->whereColumn('client_id', 'clients.id')
                    ->latest()
                    ->limit(1),
            ])
            ->withCount(['projects','photos','users'])
            ->with([
                'projects' => fn ($projects) => $projects
                    ->select('id','client_id','name','start_date','end_date')
                    ->latest('start_date')
                    ->take(3),
                'admins' => fn ($admins) => $admins
                    ->select('users.id','users.name','users.email'),
            ]);

        if ($user?->role === 'admin') {
            $query->whereHas('admins', function (Builder $builder) use ($user) {
                $builder->where('users.id', $user->id);
            });
        } elseif ($user?->role === 'client' && $user->client_id) {
            $query->where('id', $user->client_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return response()->json(
            $query->orderBy('name')->get()
        );
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'brand_color' => 'nullable|string',
            'watermark_enabled' => 'boolean',
            'notes' => 'nullable|string'
        ]);
        $client = Client::create($data);
        return response()->json($client, 201);
    }
    public function show(Client $client) { return response()->json($client->load('projects','photos')); }
    public function update(Request $request, Client $client)
    {
        $client->update($request->only(['name','contact_email','contact_phone','logo_url','brand_color','watermark_enabled','notes']));
        return response()->json($client);
    }
    public function destroy(Client $client) { $client->delete(); return response()->json(['message'=>'Client deleted']); }
}
