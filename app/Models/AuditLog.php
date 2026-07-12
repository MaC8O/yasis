<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'role', 'action', 'entity_type', 'entity_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The category buckets shown in the filter, in display order. Every log is
     * mapped into exactly one of these from its entity type + action.
     */
    public const CATEGORIES = [
        'Authentication',
        'User Management',
        'Academic Records',
        'Grades & Assessment',
        'Attendance & Leave',
        'Finance',
        'System',
    ];

    /** Entity type -> category. Authentication is decided by action, not entity. */
    protected const ENTITY_CATEGORY = [
        'User' => 'User Management',
        'StaffProfile' => 'User Management',
        'Student' => 'Academic Records',
        'Guardian' => 'Academic Records',
        'Enrollment' => 'Academic Records',
        'Section' => 'Academic Records',
        'TeachingAssignment' => 'Academic Records',
        'AcademicYear' => 'Academic Records',
        'Term' => 'Academic Records',
        'PromotionBatch' => 'Academic Records',
        'DocumentRequest' => 'Academic Records',
        'Grade' => 'Grades & Assessment',
        'GradeScaleBand' => 'Grades & Assessment',
        'GradeChangeRequest' => 'Grades & Assessment',
        'Assessment' => 'Grades & Assessment',
        'AssessmentCategory' => 'Grades & Assessment',
        'ReportCardComment' => 'Grades & Assessment',
        'AttendanceRecord' => 'Attendance & Leave',
        'StaffAttendance' => 'Attendance & Leave',
        'LeaveRequest' => 'Attendance & Leave',
        'AbsenceNotice' => 'Attendance & Leave',
        'ImportBatch' => 'Finance',
        'ImportedFeeRecord' => 'Finance',
        'SystemSetting' => 'System',
        'CalendarEvent' => 'System',
        'Announcement' => 'System',
    ];

    /** Login/logout/lockout/password actions are Authentication regardless of entity. */
    public function getCategoryAttribute(): string
    {
        $action = strtolower($this->action);
        foreach (['logged in', 'logged out', 'login', 'log out', 'password', 'unlock', 'locked'] as $needle) {
            if (str_contains($action, $needle)) {
                return 'Authentication';
            }
        }

        return self::ENTITY_CATEGORY[$this->entity_type] ?? 'System';
    }

    /** SQL fragment that reproduces getCategoryAttribute() for filtering/aggregation. */
    public static function categoryScopeConstraints(string $category): array
    {
        $entities = array_keys(array_filter(self::ENTITY_CATEGORY, fn ($c) => $c === $category));
        $authNeedles = ['%logged in%', '%logged out%', '%login%', '%log out%', '%password%', '%unlock%', '%locked%'];

        return compact('entities', 'authNeedles');
    }
}
