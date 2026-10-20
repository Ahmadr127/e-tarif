<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // 'subject' sengaja tidak di-eager load: baris audit lama bisa menunjuk
        // model yang sudah dihapus (mis. Room) dan morph eager load akan
        // melempar error. View memakai accessor ActivityLog::subject_label.
        $query = ActivityLog::latest('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('log_name', 'ilike', "%{$search}%")
                    ->orWhere('event', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // 'causer' hanya di-eager load bila semua tipe causer pada hasil filter
        // masih bisa di-resolve. MorphTo mengiterasi semua tipe yang ada, jadi
        // satu tipe yang modelnya sudah dihapus cukup untuk menggagalkan eager
        // load. Bila ada yang tidak resolvable, view mengakses causer secara
        // guarded (lihat causerIsResolvable()).
        if ($this->causerTypesResolvable($query)) {
            $query->with('causer');
        }

        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100]) ? (int) $request->input('per_page', 10) : 10;

        $logs = $query->paginate($perPage)->withQueryString();

        $users = User::select('id', 'name')->orderBy('name')->get();
        $logNames = ActivityLog::select('log_name')->distinct()->pluck('log_name');
        $events = ActivityLog::select('event')->distinct()->pluck('event');

        return view('activity-logs.index', compact('logs', 'users', 'logNames', 'events'));
    }

    public function show(ActivityLog $activityLog)
    {
        // Sama seperti index: subject diakses lewat accessor yang aman,
        // bukan eager load (bisa error untuk model yang sudah dihapus).
        if (ActivityLog::typeIsResolvable($activityLog->causer_type)) {
            $activityLog->load('causer');
        }

        return view('activity-logs.show', compact('activityLog'));
    }

    /**
     * Semua causer_type pada hasil query harus bisa di-resolve sebelum morph
     * causer di-eager load. Tipe kosong (causer null) diabaikan karena tidak
     * perlu di-resolve.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ActivityLog>  $query
     */
    protected function causerTypesResolvable($query): bool
    {
        return (clone $query)
            ->reorder()
            ->select('causer_type')
            ->distinct()
            ->pluck('causer_type')
            ->filter()
            ->every(fn ($type) => ActivityLog::typeIsResolvable($type));
    }

    public function forSubject(Request $request, string $type, int $id)
    {
        // contoh: /activity-logs/subject/user/2  -> tampilkan log untuk user id 2
        $modelClass = match ($type) {
            'user' => \App\Models\User::class,
            'role' => \App\Models\Role::class,
            'organization-unit' => \App\Models\OrganizationUnit::class,
            'organization-type' => \App\Models\OrganizationType::class,
            default => abort(404, 'Unknown subject type')
        };

        $query = ActivityLog::where('subject_type', $modelClass)->where('subject_id', $id)->latest('id');
        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100]) ? (int) $request->input('per_page', 10) : 10;
        $logs = $query->paginate($perPage)->withQueryString();
        $subject = $modelClass::findOrFail($id);

        return view('activity-logs.index', compact('logs', 'subject'));
    }
}
