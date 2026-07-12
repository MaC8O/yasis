<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;

/**
 * Publishing resolves the audience label (All/Staff/Guardians/Students or a
 * department name) to the stored audience_type/audience_id pair; delivery is
 * scope-filtered on read by the portal notice controllers.
 */
class AnnouncementService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function publish(string $audience, string $title, string $body, StaffProfile $author, User $actor): Announcement
    {
        if (in_array($audience, ['All', 'Staff', 'Guardians', 'Students'])) {
            $audienceType = $audience === 'All' ? 'School' : $audience;
            $audienceId = null;
        } else {
            $department = Department::where('name', $audience)->firstOrFail();
            $audienceType = 'Department';
            $audienceId = $department->id;
        }

        $announcement = Announcement::create([
            'author_id' => $author->id,
            'title' => $title,
            'body' => $body,
            'audience_type' => $audienceType,
            'audience_id' => $audienceId,
            'published_at' => now(),
        ]);

        $this->audit->log($actor, 'Published announcement', 'Announcement', $announcement->id);

        return $announcement;
    }
}
