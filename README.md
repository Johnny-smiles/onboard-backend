# On Brand Backend

Laravel REST API for social media asset management and publishing.

## Requirements

- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Redis (for queues)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Running

```bash
php artisan serve
php artisan queue:work  # For background jobs
```

## Testing

```bash
php artisan test
```

## Future Enhancements

### Email Verification (Optional)

Email verification is not currently implemented. To add it:

1. Create migration to add `email_verified_at` column:
   ```php
   $table->timestamp('email_verified_at')->nullable();
   ```

2. Update `User` model to implement `MustVerifyEmail`:
   ```php
   use Illuminate\Contracts\Auth\MustVerifyEmail;

   class User extends Authenticatable implements MustVerifyEmail
   ```

3. Add `unverified()` method to `UserFactory`:
   ```php
   public function unverified(): static
   {
       return $this->state(fn () => [
           'email_verified_at' => null,
       ]);
   }
   ```

4. Configure verification routes in `routes/auth.php`

### Other Planned Features

- Instagram Publishing
- Scheduled Publishing Calendar
- Caption Templates
- Publishing Analytics
