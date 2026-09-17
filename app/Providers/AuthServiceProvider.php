<?php

namespace App\Providers;

use App\Models\AttendanceDevice;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\Dormitory;
use App\Models\DormitoryRoom;
use App\Models\TahfidzSetoran;
use App\Models\MutabaahRecord;
use App\Models\Subject;
use App\Models\BoardingPermission;
use App\Models\Curriculum;
use App\Models\LessonSchedule;
use App\Models\AcademicEvent;
use App\Models\Grade;
use App\Models\ClassPromotion;
use App\Models\LearningMaterial;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\BillType;
use App\Models\StudentBill;
use App\Models\StudentMutation;
use App\Models\PpdbPeriod;
use App\Models\PpdbApplicant;
use App\Models\HealthRecord;
use App\Models\UksVisit;
use App\Models\Extracurricular;
use App\Models\User;

use App\Policies\AttendanceDevicePolicy;
use App\Policies\ClassRoomPolicy;
use App\Policies\DormitoryPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\DormitoryRoomPolicy;
use App\Policies\TahfidzSetoranPolicy;
use App\Policies\MutabaahRecordPolicy;
use App\Policies\StudentPermissionPolicy;
use App\Policies\StudentPolicy;
use App\Policies\BoardingPermissionPolicy;
use App\Policies\CurriculumPolicy;
use App\Policies\LessonSchedulePolicy;
use App\Policies\AcademicEventPolicy;
use App\Policies\GradePolicy;
use App\Policies\ClassPromotionPolicy;
use App\Policies\LearningMaterialPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\AssignmentSubmissionPolicy;
use App\Policies\BillTypePolicy;
use App\Policies\StudentBillPolicy;
use App\Policies\StudentMutationPolicy;
use App\Policies\PpdbPeriodPolicy;
use App\Policies\PpdbApplicantPolicy;
use App\Policies\HealthRecordPolicy;
use App\Policies\UksVisitPolicy;
use App\Policies\ExtracurricularPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Student::class           => StudentPolicy::class,
        ClassRoom::class         => ClassRoomPolicy::class,
        AttendanceDevice::class  => AttendanceDevicePolicy::class,
        StudentPermission::class => StudentPermissionPolicy::class,

        Dormitory::class     => DormitoryPolicy::class,
        DormitoryRoom::class => DormitoryRoomPolicy::class,

        TahfidzSetoran::class  => TahfidzSetoranPolicy::class,
        MutabaahRecord::class  => MutabaahRecordPolicy::class,
        BoardingPermission::class => BoardingPermissionPolicy::class,
        Subject::class => SubjectPolicy::class,
        Curriculum::class => CurriculumPolicy::class,
        LessonSchedule::class => LessonSchedulePolicy::class,
        AcademicEvent::class => AcademicEventPolicy::class,
        Grade::class => GradePolicy::class,
        ClassPromotion::class => ClassPromotionPolicy::class,
        // di dalam $policies:
        LearningMaterial::class    => LearningMaterialPolicy::class,
        Assignment::class          => AssignmentPolicy::class,
        AssignmentSubmission::class => AssignmentSubmissionPolicy::class,

        BillType::class    => BillTypePolicy::class,
        StudentBill::class => StudentBillPolicy::class,

        StudentMutation::class => StudentMutationPolicy::class,
        PpdbPeriod::class    => PpdbPeriodPolicy::class,
        PpdbApplicant::class => PpdbApplicantPolicy::class,
        HealthRecord::class     => HealthRecordPolicy::class,
        UksVisit::class         => UksVisitPolicy::class,
        Extracurricular::class  => ExtracurricularPolicy::class,
        // ... policy company/pesantren yang sudah ada (kalau ada) tetap di sini
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        /**
         * Admin sekolah boleh kelola (lihat/edit/reset password/nonaktifkan) staff
         * (guru/wali) HANYA di sekolahnya sendiri. Guru/wali tidak pernah bisa
         * kelola staff lain, bahkan sesama guru/wali.
         */
        Gate::define('manage-staff', function (User $user, User $staff) {
            return $user->role === 'admin' && $user->company_id === $staff->company_id;
        });
    }
}
