<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;
    /**
     * Pakai guarded (bukan fillable) — semua kolom boleh diisi mass-assignment
     * kecuali id. Karena data ini yang input cuma superadmin lewat panel sendiri
     * (bukan dari input publik), risiko mass-assignment rendah.
     */
    protected $guarded = ['id'];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'view_count' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
