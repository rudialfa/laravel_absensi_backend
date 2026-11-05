<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Catatan aksi penting (terutama dari panel superadmin).
 *
 * Kolom: user_id, action, subject_type, subject_id, description, meta (json), ip_address, timestamps.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    /** Jenis aksi = kata pertama dari action (create_tenant → create). */
    public const KINDS = [
        'create'   => ['Tambah', 'success'],
        'update'   => ['Ubah', 'info'],
        'delete'   => ['Hapus', 'danger'],
        'export'   => ['Export', 'secondary'],
        'view'     => ['Lihat data sensitif', 'warning'],
        'suspend'  => ['Suspend', 'danger'],
        'activate' => ['Aktifkan', 'success'],
        'login'    => ['Login', 'primary'],
        'logout'   => ['Logout', 'primary'],
    ];

    /** Model → route detail superadmin (dipakai di halaman detail log). */
    public const SUBJECT_ROUTES = [
        Company::class               => 'superadmin.tenants.show',
        SubscriptionInvoice::class   => 'superadmin.invoices.show',
        CompanySubscription::class   => 'superadmin.subscriptions.show',
        SubscriptionPlan::class      => 'superadmin.plans.show',
        SubscriptionDiscount::class  => 'superadmin.discounts.show',
        VaPayment::class             => 'superadmin.va-payments.show',
        SupportTicket::class         => 'superadmin.support-tickets.show',
        HelpArticle::class           => 'superadmin.help-articles.show',
        AppPolicy::class             => 'superadmin.app-policies.show',
        BroadcastNotification::class => 'superadmin.notifications.show',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper singkat untuk mencatat log dari controller.
     *
     * AuditLog::record('verify_invoice', $invoice, "Invoice {$invoice->invoice_number} diverifikasi manual");
     */
    public static function record(string $action, $subject = null, ?string $description = null, array $meta = []): self
    {
        $isModel = $subject instanceof Model;

        return static::create([
            'user_id'      => auth()->id(),
            'action'       => Str::limit($action, 250, ''),
            'subject_type' => $isModel ? get_class($subject) : null,
            'subject_id'   => $isModel ? $subject->getKey() : null,
            'description'  => $description,
            'meta'         => $meta ?: null,
            'ip_address'   => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    // =========================================================
    // Helper tampilan
    // =========================================================

    public function kind(): string
    {
        return Str::before($this->action, '_');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind()][0] ?? Str::headline($this->kind());
    }

    public function kindColor(): string
    {
        return self::KINDS[$this->kind()][1] ?? 'light';
    }

    /** "App\Models\SubscriptionInvoice" → "SubscriptionInvoice" */
    public function subjectName(): ?string
    {
        return $this->subject_type ? class_basename($this->subject_type) : null;
    }

    /** Data yang di-meta punya struktur before/after? */
    public function hasDiff(): bool
    {
        $m = $this->meta ?? [];

        return is_array($m['before'] ?? null) || is_array($m['after'] ?? null);
    }

    /**
     * Baris perbandingan before/after: [key, before, after, changed].
     */
    public function diffRows(): array
    {
        $before = (array) ($this->meta['before'] ?? []);
        $after = (array) ($this->meta['after'] ?? []);
        $rows = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;
            $rows[] = [$key, $b, $a, json_encode($b) !== json_encode($a)];
        }

        return $rows;
    }

    /** Meta selain before/after. */
    public function extraMeta(): array
    {
        return collect($this->meta ?? [])->except(['before', 'after'])->all();
    }

    /** ID tenant terkait (dari subject Company atau meta.company_id). */
    public function companyId(): ?int
    {
        if ($this->subject_type === Company::class) {
            return (int) $this->subject_id;
        }

        $id = $this->meta['company_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /** URL halaman detail subject bila masih ada; null bila tidak ada halaman/sudah dihapus. */
    public function subjectUrl(): ?string
    {
        if (!$this->subject_type || !$this->subject_id || !class_exists($this->subject_type)) {
            return null;
        }

        if ($this->subject_type === User::class) {
            $u = User::withoutGlobalScopes()->find($this->subject_id, ['id', 'role', 'company_id']);
            if (!$u) {
                return null;
            }
            $route = in_array($u->role, ['superadmin', 'staff'], true) && !$u->company_id ? 'superadmin.staff.show' : 'superadmin.users.show';

            return Route::has($route) ? route($route, $u->id) : null;
        }

        $route = self::SUBJECT_ROUTES[$this->subject_type] ?? null;
        if (!$route || !Route::has($route)) {
            return null;
        }

        $model = $this->subject_type;
        $exists = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)
            ? $model::withTrashed()->whereKey($this->subject_id)->exists()
            : $model::query()->withoutGlobalScopes()->whereKey($this->subject_id)->exists();

        return $exists ? route($route, $this->subject_id) : null;
    }

    /** Nilai meta → teks untuk ditampilkan. */
    public static function present($value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return (string) $value;
    }
}