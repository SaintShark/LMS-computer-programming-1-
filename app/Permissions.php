<?php

class Permission
{
    /**
     * Get current user role from session
     */
    private static function getUserRole(): string
    {
        return $_SESSION['user']['role'] ?? '';
    }

    /**
     * Check if user has exact role
     */
    private static function hasRole(string $role): bool
    {
        return self::getUserRole() === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    private static function hasAnyRole(array $roles): bool
    {
        return in_array(self::getUserRole(), $roles, true);
    }

    // ========================
    // USER MANAGEMENT
    // ========================

    public static function canManageUsers(): bool
    {
        return self::hasRole('admin');
    }
    public static function canAddUsers(): bool
    {
        return self::hasRole('admin');
    }
    public static function canEditUsers(): bool
    {
        return self::hasRole('admin');
    }
    public static function canDeleteUsers(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // STUDENTS
    // ========================

    public static function canManageStudents(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddStudents(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditStudents(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteStudents(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // ACTIVITIES
    // ========================

    public static function canManageActivities(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddActivities(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditActivities(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteActivities(): bool
    {
        return self::hasRole('admin');
    }
    public static function canViewOwnActivities(): bool
    {
        return self::hasRole('student');
    }
    public static function canSubmitActivities(): bool
    {
        return self::hasRole('student');
    }

    // ========================
    // LESSONS
    // ========================

    public static function canManageLessons(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canViewLessons(): bool
    {
        return self::hasAnyRole(['admin', 'teacher', 'student']);
    }
    public static function canAddLessons(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditLessons(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteLessons(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // QUIZZES
    // ========================

    public static function canManageQuizzes(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddQuizzes(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditQuizzes(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteQuizzes(): bool
    {
        return self::hasRole('admin');
    }
    public static function canViewOwnQuizzes(): bool
    {
        return self::hasRole('student');
    }
    public static function canTakeQuizzes(): bool
    {
        return self::hasRole('student');
    }

    // ========================
    // GRADES
    // ========================

    public static function canManageGrades(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canViewOwnGrades(): bool
    {
        return self::hasRole('student');
    }

    // ========================
    // INTERVENTIONS
    // ========================

    public static function canManageInterventions(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddInterventions(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditInterventions(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteInterventions(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // ANNOUNCEMENTS
    // ========================

    public static function canManageAnnouncements(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddAnnouncements(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditAnnouncements(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteAnnouncements(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // SEMESTERS
    // ========================

    public static function canManageSemesters(): bool
    {
        return self::hasRole('admin');
    }
    public static function canAddSemesters(): bool
    {
        return self::hasRole('admin');
    }
    public static function canEditSemesters(): bool
    {
        return self::hasRole('admin');
    }
    public static function canDeleteSemesters(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // GRADING PERIODS
    // ========================

    public static function canManageGradingPeriods(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canAddGradingPeriods(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canEditGradingPeriods(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
    public static function canDeleteGradingPeriods(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // SETTINGS
    // ========================

    public static function canManageSettings(): bool
    {
        return self::hasRole('admin');
    }

    // ========================
    // UTILITY
    // ========================

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }
    public static function isTeacher(): bool
    {
        return self::hasRole('teacher');
    }
    public static function isStudent(): bool
    {
        return self::hasRole('student');
    }
    public static function isAdminOrTeacher(): bool
    {
        return self::hasAnyRole(['admin', 'teacher']);
    }
}