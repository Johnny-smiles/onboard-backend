<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
class ClientController extends Controller
{
    public function index() { return response()->json(Client::withCount(['projects','photos'])->get()); }
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
