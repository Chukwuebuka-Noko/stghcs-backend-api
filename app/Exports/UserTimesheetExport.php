<?php

namespace App\Exports;

use App\Models\WeekLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\ProgressReport;
use App\Models\TimeSheet;

class UserTimesheetExport implements FromCollection, WithHeadings, WithMapping
{
   protected $userId;
    protected $start_date;
    protected $end_date;

    public function __construct($userId, $start_date, $end_date)
    {
        $this->userId = $userId;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
{
    $timesheet = TimeSheet::where(['user_id' => $this->userId])
        ->with(['user', 'gigs', 'gigs.client', 'gigs.gig_type', 'assignments', 'weekLog', 'incidents_report', 'progress_report'])
        ->get();

    $enrichedActivities = [];

    foreach ($timesheet as $ts) {
        // Access the 'activities' field from each timesheet
        $activities = $ts->activities ?? null;

        // Check if activities is not null and is a valid JSON string
        if ($activities && is_string($activities)) {
            $activitiesArray = json_decode($activities, true);

            // Ensure json_decode was successful
            if (json_last_error() === JSON_ERROR_NONE && is_array($activitiesArray)) {

                // Get gig details and other information
                $gig_title = $ts->gigs->title;
                $gig_type = $ts->gigs->gig_type;
                $client = $ts->gigs->client->first_name . " " . $ts->gigs->client->last_name . " " . $ts->gigs->client->other_name;
                $support_worker = $ts->user->first_name . " " . $ts->user->last_name . " " . $ts->user->other_name;

                // Filter activities based on clock_in date
                $filteredActivities = array_filter($activitiesArray, function ($activity) {
                    $clockInDate = isset($activity['clock_in']) ? new \DateTime($activity['clock_in']) : null;
                    $startDate = new \DateTime($this->start_date);
                    $endDate = new \DateTime($this->end_date);
                    $endDate->modify('+1 day');

                    return $clockInDate && $clockInDate >= $startDate && $clockInDate <= $endDate;
                });

                // Enrich the filtered activities
                foreach ($filteredActivities as $activity) {
                    $activity['gig_title'] = $gig_title;
                    $activity['gig_type'] = $gig_type;
                    $activity['client'] = $client;
                    $activity['support_worker'] = $support_worker;

                    // Initialize the duration key with a default value
                    $activity['duration'] = null;

                    // Calculate duration if both clock_in and clock_out are present
                    if (isset($activity['clock_in']) && isset($activity['clock_out'])) {
                        $clockInDate = new \DateTime($activity['clock_in']);
                        $clockOutDate = new \DateTime($activity['clock_out']);
                        $interval = $clockInDate->diff($clockOutDate);
                        $activity['duration'] = $interval->format('%h hours %i minutes');
                    }

                    $enrichedActivities[] = $activity;
                }
            }
        }
    }

    return collect($enrichedActivities);
}


    
    /*public function collection()
{
    $timesheet = TimeSheet::where(['user_id' => $this->userId])
        ->with(['user', 'gigs', 'gigs.client', 'gigs.gig_type', 'assignments', 'weekLog', 'incidents_report', 'progress_report'])
        ->get();

    $enrichedActivities = [];

    foreach ($timesheet as $ts) {
        // Access the 'activities' field from each timesheet
        $activities = $ts->activities ?? null;

        // Check if activities is not null and is a valid JSON string
        if ($activities && is_string($activities)) {
            $activitiesArray = json_decode($activities, true);

            // Ensure json_decode was successful
            if (json_last_error() === JSON_ERROR_NONE && is_array($activitiesArray)) {

                // Get gig details and other information
                $gig_title = $ts->gigs->title ?? '';
                $gig_type = $ts->gigs->gig_type->name ?? '';
                $client = $ts->gigs->client->first_name . " " . $ts->gigs->client->last_name . " " . $ts->gigs->client->other_name;
                $support_worker = $ts->user->first_name . " " . $ts->user->last_name . " " . $ts->user->other_name;

                // Directly enrich activities without filtering
                foreach ($activitiesArray as $activity) {
                    $activity['gig_title'] = $gig_title;
                    $activity['gig_type'] = $gig_type;
                    $activity['client'] = $client;
                    $activity['support_worker'] = $support_worker;

                    // Initialize the duration key with a default value
                    $activity['duration'] = null;

                    // Calculate duration if both clock_in and clock_out are present
                    if (isset($activity['clock_in']) && isset($activity['clock_out'])) {
                        $clockInDate = new \DateTime($activity['clock_in']);
                        $clockOutDate = new \DateTime($activity['clock_out']);
                        $interval = $clockInDate->diff($clockOutDate);
                        $activity['duration'] = $interval->format('%h hours %i minutes');
                    }

                    $enrichedActivities[] = $activity;
                }
            }
        }
    }

    return collect($enrichedActivities);
}*/

    /**
     * Define the headings for the export file.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Date',
            'Activity ID',
            'Gig Title',
            'Gig Type',
            'Client Name',
            'Support Worker Name',
            'Clock In',
            'Clock Out',
            'Flags',
            'Duration'
        ];
    }

    /**
     * Map the data to the export format.
     *
     * @param array $activity
     * @return array
     */
    public function map($activity): array
{
    // Prepare flags in a readable format
    $flags = $activity['flags'] ?? [];

    // Format flags as a string or any other desired format for the export
    $formattedFlags = array_map(function($flag) {
        return implode(', ', [
            'Title: ' . $flag['title'],
            'Remark: ' . $flag['remark'],
            'Description: ' . $flag['description'],
        ]);
    }, $flags);

    // Combine the formatted flags into a single string, each flag separated by a newline for readability
    $flagsString = implode("\n", $formattedFlags);

    return [
        Carbon::parse($activity['clock_in'])->format('m-d-Y'),
        $activity['activity_id'] ?? null, // Activity ID
        $activity['gig_title'] ?? null,    // Gig Title
        $activity['gig_type'] ?? null,     // Gig Type
        $activity['client'] ?? null,       // Client Name
        $activity['support_worker'] ?? null, // Support Worker Name
        Carbon::parse($activity['clock_in'])->format('m-d-Y H:i:s'),
        $activity['clock_out'] ? Carbon::parse($activity['clock_out'])->format('m-d-Y H:i:s') : null,
        $flagsString,                      // Formatted flags as a single string
        $activity['duration']
    ];
}

    private function fetchProgressReports($timesheet_id, $activity_id)
    {
        return ProgressReport::where('timesheet_id', $timesheet_id)
            ->where('activity_id', $activity_id)
            ->get()
            ->map(function ($progress) {
                return [
                    'progress_id' => $progress->id,
                    'title' => $progress->title,
                    'description' => $progress->description,
                    'progress_time' => $progress->progress_time,
                    'created_at' => $progress->created_at->format('m-d-Y H:i:s')
                ];
            })
            ->toArray();
    }
}