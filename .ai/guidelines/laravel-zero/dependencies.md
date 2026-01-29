# Laravel Zero Dependencies

## Common Services

### File System
```php
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\Filesystem;

// Via Facade
Storage::disk('local')->put('file.txt', 'content');

// Via Dependency Injection
public function __construct(
    private readonly Filesystem $files,
) {}


HTTP Client
```php
use Illuminate\Support\Facades\Http;

$response = Http::get('https://api.example.com');
$data = $response->json();


Process
```php
use Illuminate\Support\Facades\Process;

$process = Process::run('docker-compose up -d');
if (!$process->successful()) {
    throw new RuntimeException($process->errorOutput());
}


Collection
```php
use Illuminate\Support\Collection;

$collection = collect($items);
$filtered = $collection->filter(fn ($item) => $item->active);

Database (if used)

```php
use Illuminate\Support\Facades\DB;

DB::table('users')->insert(['name' => 'John']);
$users = DB::table('users')->get();
