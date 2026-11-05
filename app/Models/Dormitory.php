<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dormitory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Pengasuh/penanggung jawab asrama (user biasa, role apapun)
    public function pengasuh()
    {
        return $this->belongsTo(User::class, 'pengasuh_id');
    }

    // Semua kamar di asrama ini
    public function rooms()
    {
        return $this->hasMany(DormitoryRoom::class);
    }

    /**
     * Penempatan aktif (penghuni sekarang) di semua kamar asrama ini.
     * Relasi asli: dormitories → dormitory_rooms → room_assignments.
     * Bisa dipakai withCount('activeAssignments') / with('activeAssignments.student').
     */
    public function activeAssignments()
    {
        return $this->hasManyThrough(
            RoomAssignment::class,
            DormitoryRoom::class,
            'dormitory_id', // FK di dormitory_rooms → dormitories
            'room_id',      // FK di room_assignments → dormitory_rooms
            'id',
            'id'
        )->whereNull('room_assignments.moved_out_at');
    }

    /**
     * Santri yang SAAT INI menghuni asrama ini.
     *
     * Sebelumnya berupa hasManyThrough yang salah (mencocokkan students.id dengan
     * dormitory_rooms.id) sehingga hasilnya murid yang keliru. Sekarang query builder biasa:
     * pakai $dormitory->students()->get(), bukan $dormitory->students.
     */
    public function students()
    {
        return Student::whereIn(
            'id',
            RoomAssignment::whereNull('moved_out_at')
                ->whereIn('room_id', DormitoryRoom::where('dormitory_id', $this->id)->select('id'))
                ->select('student_id')
        );
    }
}
