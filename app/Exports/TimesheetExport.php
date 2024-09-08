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

class TimesheetExport implements FromCollection, WithHeadings, WithMapping
{
   protected $timesheetId;
    protected $start_date;
    protected $end_date;

    public function __construct($timesheetId, $start_date, $end_date)
    {
        $this->timesheetId = $timesheetId;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $timesheet = TimeSheet::where(['unique_id' => $this->timesheetId])
            ->with(['user', 'gigs', 'gigs.client', 'gigs.gig_type', 'assignments', 'weekLog', 'incidents_report', 'progress_report'])
            ->first();

        $activities = $timesheet->activities;
        
        $gig_title = $timesheet->gigs->title;
        $gig_type = $timesheet->gigs->gig_type;
        $client = $timesheet->gigs->client->first_name." ".$timesheet->gigs->client->last_name." ".$timesheet->gigs->client->other_name;
        $support_worker = $timesheet->user->first_name." ".$timesheet->user->last_name." ".$timesheet->user->other_name;

        // Assuming activities is stored as a JSON string, decode it to an array
    $activitiesArray = json_decode($activities, true);

    // Filter activities where clock_in is between start_date and end_date
    $filteredActivities = array_filter($activitiesArray, function ($activity) {
        // Convert clock_in to a DateTime object if it's in string format
        $clockInDate = isset($activity['clock_in']) ? new \DateTime($activity['clock_in']) : null;

        // Ensure start_date and end_date are DateTime objects
        $startDate = new \DateTime($this->start_date);
        $endDate = new \DateTime($this->end_date);
        $endDate->modify('+1 day');

        // Check if clock_in exists and is within the date range
        return $clockInDate && $clockInDate >= $startDate && $clockInDate <= $endDate;
    });

    // Add gig details, client, support worker information, and flags to each filtered activity
    $enrichedActivities = array_map(function ($activity) use ($gig_title, $gig_type, $client, $support_worker) {
        // Check if flags is already an array; if not, decode it
        $flags = isset($activity['flags']) && is_string($activity['flags']) ? json_decode($activity['flags'], true) : $activity['flags'];

        // Convert flags to an associative array for export
        $formattedFlags = [];
        if (is_array($flags)) {
            foreach ($flags as $flag) {
                $formattedFlags[] = [
                    'title' => $flag['title'] ?? '',
                    'remark' => $flag['remark'] ?? '',
                    'description' => $flag['description'] ?? '',
                ];
            }
        }
        
        // Calculate the duration between clock_in and clock_out
        $duration = null;
        if (isset($activity['clock_in']) && isset($activity['clock_out'])) {
            $clockInDate = new \DateTime($activity['clock_in']);
            $clockOutDate = new \DateTime($activity['clock_out']);
            $interval = $clockInDate->diff($clockOutDate);
            $duration = $interval->format('%h hours %i minutes'); // Format duration as hours and minutes
        }

        // Return the enriched activity with the formatted flags
        return array_merge($activity, [
            'gig_title' => $gig_title,
            'gig_type' => $gig_type,
            'client' => $client,
            'support_worker' => $support_worker,
            'flags' => $formattedFlags, // Use the formatted associative array
            'duration' => $duration // Include calculated duration
        ]);
    }, $filteredActivities);

    // Convert the enriched activities array to a Laravel Collection
    return collect($enrichedActivities);
    }

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