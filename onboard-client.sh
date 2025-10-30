#!/bin/bash
# Client Onboarding Script
# Usage: ./onboard-client.sh "Client Name" "client@email.com"

set -e

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: ./onboard-client.sh \"Client Name\" \"client@email.com\""
    echo "Example: ./onboard-client.sh \"Acme Corp\" \"contact@acme.com\""
    exit 1
fi

CLIENT_NAME="$1"
CLIENT_EMAIL="$2"
CLIENT_PASSWORD="${3:-password}"

echo "🚀 Onboarding client: $CLIENT_NAME ($CLIENT_EMAIL)"
echo ""

# Get admin token
echo "1️⃣  Getting admin authentication..."
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d "{\"email\": \"admin@example.com\", \"password\": \"password\"}" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ Failed to authenticate admin. Make sure the server is running."
    exit 1
fi

# Create client
echo "2️⃣  Creating client organization..."
CLIENT_RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/clients \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"name\": \"$CLIENT_NAME\",
    \"contact_email\": \"$CLIENT_EMAIL\",
    \"watermark_enabled\": false
  }")

CLIENT_ID=$(echo $CLIENT_RESPONSE | grep -o '"id":[0-9]*' | grep -o '[0-9]*')

if [ -z "$CLIENT_ID" ]; then
    echo "❌ Failed to create client"
    echo "$CLIENT_RESPONSE"
    exit 1
fi

echo "   ✅ Client created with ID: $CLIENT_ID"

# Create client user and assign admin via PHP
echo "3️⃣  Creating client user and assigning admin..."
php artisan tinker --execute="
\$client = \App\Models\Client::find($CLIENT_ID);
\$user = \App\Models\User::create([
    'name' => '$CLIENT_NAME User',
    'email' => '$CLIENT_EMAIL',
    'password' => bcrypt('$CLIENT_PASSWORD'),
    'role' => 'client',
    'client_id' => $CLIENT_ID
]);
\$admin = \App\Models\User::where('email', 'admin@example.com')->first();
\$admin->managedClients()->attach($CLIENT_ID);
echo '   ✅ User created' . PHP_EOL;
" 2>/dev/null

echo ""
echo "✅ Client onboarding complete!"
echo ""
echo "📧 Client Login:"
echo "   Email: $CLIENT_EMAIL"
echo "   Password: $CLIENT_PASSWORD"
echo "   URL: http://localhost:5173"
echo ""
echo "🎯 Next Steps:"
echo "   1. Share credentials with client"
echo "   2. Client can upload photos at /client/upload"
echo "   3. Admin can review at /admin/review"
echo ""

