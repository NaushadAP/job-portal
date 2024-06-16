<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\User;
use App\Models\SavedJob;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use App\Mail\JobNotificationEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Session\Session;

class JobsController extends Controller
{
    //This method will show jobs page
    public function index(Request $request)
    {

        $jobs = Job::where('status', 1);

        // Search using keyword
        if (!empty($request->keyword)) {
            $jobs = $jobs->where(function ($query) use ($request) {
                $query->orWhere('title', 'like', '%' . $request->keyword . '%');
                $query->orWhere('keywords', 'like', '%' . $request->keyword . '%');
            });
        }

        // Search using location
        if (!empty($request->location)) {
            $jobs = $jobs->where('location', $request->location);
        }

        // Search using Job Experience
        if (!empty($request->experience)) {
            $jobs = $jobs->where('experience', $request->experience);
        }

        if ($request->sort == '0') {

            $jobs = $jobs->orderBy('created_at', 'ASC');
        } else {

            $jobs = $jobs->orderBy('created_at', 'DESC');
        }

        $jobs = $jobs->paginate(9);


        return view('front.jobs', [
            'jobs' => $jobs,
        ]);
    }
    public function indexApi(Request $request): JsonResponse
    {

        $jobs = Job::where('status', 1);

        // Search using keyword
        if (!empty($request->keyword)) {
            $jobs = $jobs->where(function ($query) use ($request) {
                $query->orWhere('title', 'like', '%' . $request->keyword . '%');
                $query->orWhere('keywords', 'like', '%' . $request->keyword . '%');
            });
        }

        // Search using location
        if (!empty($request->location)) {
            $jobs = $jobs->where('location', $request->location);
        }

        // Search using Job Experience
        if (!empty($request->experience)) {
            $jobs = $jobs->where('experience', $request->experience);
        }

        if ($request->sort == '0') {

            $jobs = $jobs->orderBy('created_at', 'ASC');
        } else {

            $jobs = $jobs->orderBy('created_at', 'DESC');
        }

        $jobs = $jobs->paginate(9);


        return response()->json($jobs, 201);

    }

    // This method will show job detail page
    public function jobDetail($id)
    {

        $job = Job::where([
            'id' => $id,
            'status' => 1
        ])->first();

        if ($job == null) {
            abort(404);
        }

        $count = 0;
        if (Auth::user()) {
            $count = SavedJob::where([
                'user_id' => Auth::user()->id,
                'job_id' => $id,
            ])->count();
        }

        // fetch applicants
        $applications = JobApplication::where('job_id', $id)->with('user')->get();
        return view('front.jobDetail', [
            'job' => $job,
            'count' => $count,
            'applications' => $applications,
        ]);
    }


    // Apply job method
    public function applyJob(Request $request)
    {
        $id = $request->id;

        $job = Job::where('id', $id)->first();

        // If job not found
        if ($job == null) {
            $message = "Job does not exist";
            Session()->flash('error', $message);
            return response()->json([
                'status' => false,
                'message' => $message,
            ]);
        }

        // You can't apply on your own job
        $employer_id = $job->user_id;
        if ($employer_id == Auth::user()->id) {
            $message = "You can not apply on your own job";
            Session()->flash('error', $message);
            return response()->json([
                'status' => false,
                'message' => $message,
            ]);
        }

        // User can't apply on a job twise
        $jobApplicationCount = JobApplication::where([
            'user_id' => Auth::user()->id,
            'job_id' => $id,
        ])->count();

        if ($jobApplicationCount > 0) {
            $message = "You already applied on this job.";
            Session()->flash('error', $message);
            return response()->json([
                'status' => false,
                'message' => $message,
            ]);
        }

        $application = new JobApplication();
        $application->job_id = $id;
        $application->user_id = Auth::user()->id;
        $application->employer_id = $employer_id;
        $application->applied_date = now();
        $application->save();

        // Send notification email to employer
        $employer = User::where('id', $employer_id)->first();
        $mailData = [
            'employer' => $employer,
            'user' => Auth::user(),
            'job' => $job,
        ];
        Mail::to($employer->email)->send(new JobNotificationEmail($mailData));

        $message = "You have successfully applied.";
        Session()->flash('success', $message);
        return response()->json([
            'status' => true,
            'message' => $message,
        ]);

    }

}
