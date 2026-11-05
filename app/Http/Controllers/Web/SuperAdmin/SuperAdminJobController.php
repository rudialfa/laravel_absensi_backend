<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Antrian Job & Scheduler.
 *
 *  - Ringkasan  : driver queue, detak scheduler (cron hidup/tidak), uji worker, daftar tugas terjadwal
 *                 (jadwal berikut, log terakhir, kunci withoutOverlapping) + jalankan manual / lepas kunci.
 *  - Menunggu   : isi tabel jobs (hanya berarti kalau QUEUE_CONNECTION=database).
 *  - Gagal      : isi tabel failed_jobs → detail, retry, retry semua, hapus, kosongkan.
 *  - Log        : 200 baris terakhir file log scheduler (hanya file output milik tugas terjadwal).
 */
class SuperAdminJobController extends Controller
{
    private const TZ = 'Asia/Jakarta';
    private const HEARTBEAT_KEY = 'scheduler:heartbeat';
    private const TEST_SENT_KEY = 'queue:test_sent';
    private const TEST_DONE_KEY = 'queue:test_done';

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['overview', 'pending', 'failed', 'log'], true) ? $request->tab : 'overview';

        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver", $connection);
        $jobsTable = config('queue.connections.database.table', 'jobs');
        $failedTable = config('queue.failed.table', 'failed_jobs');
        $hasJobs = Schema::hasTable($jobsTable);
        $hasFailed = Schema::hasTable($failedTable);

        // ----- Status -----
        $heartbeat = Cache::get(self::HEARTBEAT_KEY);
        $testSent = Cache::get(self::TEST_SENT_KEY);
        $testDone = Cache::get(self::TEST_DONE_KEY);

        $pendingCount = $hasJobs ? DB::table($jobsTable)->count() : 0;
        $reservedCount = $hasJobs ? DB::table($jobsTable)->whereNotNull('reserved_at')->count() : 0;
        $oldestPending = $hasJobs ? DB::table($jobsTable)->whereNull('reserved_at')->min('available_at') : null;
        $failedCount = $hasFailed ? DB::table($failedTable)->count() : 0;
        $failed24h = $hasFailed ? DB::table($failedTable)->where('failed_at', '>=', now()->subDay())->count() : 0;

        $status = [
            'connection'     => $connection,
            'driver'         => $driver,
            'heartbeat'      => $heartbeat ? Carbon::createFromTimestamp($heartbeat) : null,
            'scheduler_ok'   => $heartbeat && (time() - (int) $heartbeat) <= 180,
            'test_sent'      => $testSent ? Carbon::createFromTimestamp($testSent) : null,
            'test_done'      => $testDone ? Carbon::createFromTimestamp($testDone) : null,
            'worker_ok'      => $driver === 'sync' ? null : ($testSent ? ($testDone && $testDone >= $testSent) : null),
            'pending'        => $pendingCount,
            'reserved'       => $reservedCount,
            'oldest_pending' => $oldestPending ? Carbon::createFromTimestamp($oldestPending) : null,
            'failed'         => $failedCount,
            'failed_24h'     => $failed24h,
            'has_jobs'       => $hasJobs,
            'has_failed'     => $hasFailed,
        ];

        $data = ['tab' => $tab, 'status' => $status];

        if ($tab === 'overview') {
            $data['events'] = $this->events();
        }

        if ($tab === 'pending') {
            $data['jobs'] = $hasJobs
                ? DB::table($jobsTable)
                ->when($request->filled('queue'), fn($q) => $q->where('queue', $request->queue))
                ->orderBy('id')->paginate(25)->withQueryString()
                ->through(fn($j) => $this->decorate($j))
                : null;
            $data['queues'] = $hasJobs ? DB::table($jobsTable)->distinct()->pluck('queue') : collect();
        }

        if ($tab === 'failed') {
            $data['jobs'] = $hasFailed
                ? DB::table($failedTable)
                ->when($request->filled('queue'), fn($q) => $q->where('queue', $request->queue))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $s = '%' . addcslashes(trim($request->search), '%_\\') . '%';
                    $q->where(fn($w) => $w->where('payload', 'like', $s)->orWhere('exception', 'like', $s));
                })
                ->orderByDesc('id')->paginate(25)->withQueryString()
                ->through(fn($j) => $this->decorate($j))
                : null;
            $data['queues'] = $hasFailed ? DB::table($failedTable)->distinct()->pluck('queue') : collect();
        }

        if ($tab === 'log') {
            $files = $this->logFiles();
            $selected = in_array($request->file, $files, true) ? $request->file : ($files[0] ?? null);
            $data['files'] = $files;
            $data['selected'] = $selected;
            $data['lines'] = $selected ? $this->tail(storage_path('logs/' . $selected), 200) : [];
            $data['fileInfo'] = $selected && is_file(storage_path('logs/' . $selected))
                ? ['size' => filesize(storage_path('logs/' . $selected)), 'mtime' => Carbon::createFromTimestamp(filemtime(storage_path('logs/' . $selected)))]
                : null;
        }

        return view('pages.superadmin.jobs.index', $data);
    }

    // =========================================================
    // DETAIL JOB (menunggu / gagal)
    // =========================================================
    public function show(string $kind, $id)
    {
        $table = $this->tableFor($kind);
        $row = DB::table($table)->where('id', $id)->first();
        abort_unless($row, 404);

        $job = $this->decorate($row);
        $payload = json_decode($row->payload, true) ?: [];

        return view('pages.superadmin.jobs.show', [
            'kind'        => $kind,
            'job'         => $job,
            'payloadJson' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    // =========================================================
    // AKSI JOB GAGAL
    // =========================================================
    public function retry($id)
    {
        $row = DB::table(config('queue.failed.table', 'failed_jobs'))->where('id', $id)->first();
        abort_unless($row, 404);

        $exit = Artisan::call('queue:retry', ['id' => [$row->uuid ?? $row->id]]);
        $name = $this->decorate($row)->name;

        AuditLog::record('retry_failed_job', null, "Retry job gagal #{$row->id} ({$name})", ['uuid' => $row->uuid ?? null, 'exit' => $exit]);

        return redirect()->route('superadmin.jobs.index', ['tab' => 'failed'])
            ->with($exit === 0 ? 'success' : 'error', $exit === 0 ? "Job {$name} dikembalikan ke antrian." : 'Retry gagal: ' . trim(Artisan::output()));
    }

    public function retryAll()
    {
        $table = config('queue.failed.table', 'failed_jobs');
        $count = DB::table($table)->count();
        abort_unless($count, 404);

        @set_time_limit(120);
        $exit = Artisan::call('queue:retry', ['id' => ['all']]);

        AuditLog::record('retry_all_failed_jobs', null, "Retry semua job gagal ({$count} job)", ['exit' => $exit]);

        return redirect()->route('superadmin.jobs.index', ['tab' => 'failed'])
            ->with($exit === 0 ? 'success' : 'error', $exit === 0 ? "{$count} job dikembalikan ke antrian." : 'Retry gagal: ' . trim(Artisan::output()));
    }

    public function destroy(Request $request, string $kind, $id)
    {
        $table = $this->tableFor($kind);
        $row = DB::table($table)->where('id', $id)->first();
        abort_unless($row, 404);

        if ($kind === 'pending' && $row->reserved_at) {
            return back()->with('error', 'Job sedang diproses worker, tidak bisa dihapus sekarang.');
        }

        $name = $this->decorate($row)->name;
        DB::table($table)->where('id', $id)->delete();

        AuditLog::record($kind === 'pending' ? 'delete_pending_job' : 'delete_failed_job', null, "Hapus job " . ($kind === 'pending' ? 'menunggu' : 'gagal') . " #{$id} ({$name})", [
            'queue' => $row->queue,
            'uuid' => $row->uuid ?? null,
        ]);

        return redirect()->route('superadmin.jobs.index', ['tab' => $kind])->with('success', "Job #{$id} ({$name}) dihapus.");
    }

    public function flushFailed(Request $request)
    {
        $data = $request->validate([
            'older_than' => 'required|integer|in:0,1,7,30',
            'reason'     => 'required|string|max:500',
        ], [], ['older_than' => 'umur', 'reason' => 'alasan']);

        $q = DB::table(config('queue.failed.table', 'failed_jobs'));
        if ((int) $data['older_than'] > 0) {
            $q->where('failed_at', '<', now()->subDays((int) $data['older_than']));
        }
        $deleted = $q->delete();

        AuditLog::record('flush_failed_jobs', null, "Hapus {$deleted} job gagal" . ($data['older_than'] ? " (lebih dari {$data['older_than']} hari)" : ' (semua)') . ". Alasan: {$data['reason']}", $data + ['deleted' => $deleted]);

        return redirect()->route('superadmin.jobs.index', ['tab' => 'failed'])->with('success', "{$deleted} job gagal dihapus.");
    }

    // =========================================================
    // UJI WORKER
    // =========================================================
    public function test()
    {
        $now = now()->timestamp;
        Cache::put(self::TEST_SENT_KEY, $now, 86400);

        dispatch(function () {
            Cache::put('queue:test_done', now()->timestamp, 86400);
        });

        $driver = config('queue.connections.' . config('queue.default') . '.driver');

        AuditLog::record('test_queue_worker', null, "Kirim job uji ke antrian ({$driver})");

        $msg = $driver === 'sync'
            ? 'Driver sync: job uji langsung dijalankan saat itu juga (tanpa worker).'
            : 'Job uji dikirim. Muat ulang halaman dalam ±10 detik — kalau statusnya masih "menunggu", worker (queue:work) tidak berjalan.';

        return redirect()->route('superadmin.jobs.index')->with('success', $msg);
    }

    // =========================================================
    // TUGAS TERJADWAL: jalankan manual / lepas kunci
    // =========================================================
    public function run(Request $request)
    {
        $event = $this->findEvent($request->input('key'));

        if (!$event['artisan']) {
            return back()->with('error', 'Tugas ini berupa fungsi (closure) dan hanya bisa dijalankan oleh scheduler.');
        }

        @set_time_limit(300);
        $started = microtime(true);

        try {
            $exit = Artisan::call($event['artisan']);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $exit = 1;
            $output = $e->getMessage();
        }

        $seconds = round(microtime(true) - $started, 1);

        AuditLog::record('run_scheduled_command', null, "Jalankan manual: php artisan {$event['artisan']} (exit {$exit}, {$seconds} dtk)", [
            'command' => $event['artisan'],
            'exit' => $exit,
            'seconds' => $seconds,
            'output' => Str::limit($output, 2000),
        ]);

        return back()->with($exit === 0 ? 'success' : 'error', "php artisan {$event['artisan']} selesai (exit {$exit}, {$seconds} dtk).")
            ->with('command_output', Str::limit($output ?: '(tidak ada output)', 5000));
    }

    public function unlock(Request $request)
    {
        $event = $this->findEvent($request->input('key'));

        /** @var Event $e */
        $e = $event['event'];
        if (!$e->withoutOverlapping) {
            return back()->with('error', 'Tugas ini tidak memakai kunci.');
        }

        $e->mutex->forget($e);

        AuditLog::record('unlock_scheduled_task', null, "Lepas kunci tugas terjadwal: {$event['label']}");

        return back()->with('success', "Kunci {$event['label']} dilepas. Tugas akan jalan lagi di jadwal berikutnya.");
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function tableFor(string $kind): string
    {
        $table = $kind === 'failed'
            ? config('queue.failed.table', 'failed_jobs')
            : config('queue.connections.database.table', 'jobs');

        abort_unless(Schema::hasTable($table), 404);

        return $table;
    }

    /** Tambah info yang mudah dibaca dari payload job. */
    private function decorate(object $row): object
    {
        $p = json_decode($row->payload ?? '', true) ?: [];

        $row->name = $p['displayName'] ?? ($p['job'] ?? 'Job');
        $row->short_name = class_basename(str_replace('\\', '/', $row->name)) ?: $row->name;
        $row->max_tries = $p['maxTries'] ?? null;
        $row->timeout = $p['timeout'] ?? null;

        if (isset($row->available_at)) {
            $row->available = Carbon::createFromTimestamp($row->available_at);
            $row->created = Carbon::createFromTimestamp($row->created_at);
            $row->reserved = $row->reserved_at ? Carbon::createFromTimestamp($row->reserved_at) : null;
        }

        if (isset($row->exception)) {
            $row->failed = Carbon::parse($row->failed_at);
            $row->error = Str::limit(strtok($row->exception, "\n"), 200);
        }

        return $row;
    }

    /** Semua tugas di App\Console\Kernel::schedule(). */
    private function events(): array
    {
        app(ConsoleKernel::class); // memastikan jadwal di Kernel ter-daftar ke container
        $schedule = app(Schedule::class);

        return collect($schedule->events())->map(function (Event $e) {
            $artisan = null;
            if ($e->command && preg_match('/artisan[\'"]?\s+(.+)$/', $e->command, $m)) {
                $artisan = trim($m[1]);
            }

            $output = ($e->output && $e->output !== $e->getDefaultOutput()) ? $e->output : null;

            try {
                $next = Carbon::instance($e->nextRunDate())->timezone(self::TZ);
            } catch (\Throwable) {
                $next = null;
            }

            try {
                $locked = $e->withoutOverlapping && $e->mutex->exists($e);
            } catch (\Throwable) {
                $locked = false;
            }

            $label = $artisan ?: ($e->description ?: 'closure');

            return [
                'key'         => substr(sha1($e->expression . '|' . $label), 0, 12),
                'event'       => $e,
                'label'       => $label,
                'artisan'     => $artisan,
                'closure'     => $e instanceof CallbackEvent,
                'description' => $e->description,
                'expression'  => $e->expression,
                'timezone'    => is_string($e->timezone) ? $e->timezone : ($e->timezone ? $e->timezone->getName() : config('app.timezone')),
                'next'        => $next,
                'overlap'     => $e->withoutOverlapping ? $e->expiresAt : null,
                'locked'      => $locked,
                'output'      => $output ? basename($output) : null,
                'output_at'   => $output && is_file($output) ? Carbon::createFromTimestamp(filemtime($output))->timezone(self::TZ) : null,
            ];
        })->values()->all();
    }

    private function findEvent(?string $key): array
    {
        $event = collect($this->events())->firstWhere('key', (string) $key);

        if (!$event) {
            throw new HttpResponseException(back()->with('error', 'Tugas terjadwal tidak ditemukan (Kernel mungkin sudah berubah). Muat ulang halaman.'));
        }

        return $event;
    }

    /** Nama file log yang boleh dibaca: output tugas terjadwal yang ada di storage/logs. */
    private function logFiles(): array
    {
        $logs = realpath(storage_path('logs'));

        return collect($this->events())->pluck('event')
            ->map(fn(Event $e) => $e->output && $e->output !== $e->getDefaultOutput() ? realpath($e->output) : false)
            ->filter(fn($p) => $p && $logs && dirname($p) === $logs)
            ->map(fn($p) => basename($p))
            ->unique()->sort()->values()->all();
    }

    /** N baris terakhir sebuah file tanpa membaca seluruh file. */
    private function tail(string $path, int $lines): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $fh = fopen($path, 'rb');
        $size = filesize($path);
        $chunk = 8192;
        $buffer = '';
        $pos = $size;

        while ($pos > 0 && substr_count($buffer, "\n") <= $lines) {
            $read = min($chunk, $pos);
            $pos -= $read;
            fseek($fh, $pos);
            $buffer = fread($fh, $read) . $buffer;
        }
        fclose($fh);

        $all = preg_split('/\r?\n/', rtrim($buffer));

        return array_slice($all, -$lines);
    }
}
