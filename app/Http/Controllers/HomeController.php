<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    // This method will show home page
    public function index(){


        $featuredJobs = Job::where('status',1)
                        ->orderBy('created_at','DESC')
                        ->where('isFeatured',1)->take(6)->get();

        $latestJobs = Job::where('status',1)
                        ->orderBy('created_at','DESC')
                        ->take(6)->get();

        return view('front.home',[
            'featuredJobs' => $featuredJobs,
            'latestJobs' => $latestJobs,
        ]);
    }
}
