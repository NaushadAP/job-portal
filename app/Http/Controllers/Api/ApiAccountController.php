<?php
namespace App\Http\Controllers\Api;

use DB;
use App\Models\Job;
use App\Models\User;
use App\Models\SavedJob;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Mail\ResetPasswordEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Intervention\Image\ImageManager;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Http\Controllers\Controller;

class ApiAccountController extends Controller
{

    // This method will save a user
    public function processRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:5|same:confirm_password',
                'confirm_password' => 'required',
        ]);

        if ($validator->passes()) {

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();

            $message = "You have registered successfully.";
            Session()->flash('success', $message);

            return response()->json([
                'status' => true,
                'message' => $message,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;
            $showUser =[
                'id'=>$user->id,
                'name'=>$user->name,

            ];

            return response()->json(['message' => 'Login successful', 'access_token' => $token, 'token_type' => 'Bearer','User'=>$showUser], 200);
        } else {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
    public function profile()
    {

        // here we will get user id, which user logged in
        $id = Auth::user()->id;
        $user = User::where('id', $id)->first();

        return view('front.account.profile', [
            'user' => $user,
        ]);
    }

    // update profile function
    public function updateProfile(Request $request)
    {

        $id = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|min:5|max:20',
            'email' => 'required|email|unique:users,email,' . $id . ',id'
        ]);

        if ($validator->passes()) {

            $user = User::find($id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->mobile = $request->mobile;
            $user->designation = $request->designation;
            $user->save();

            Session()->flash('success', 'Profile updated successfully');

            return response()->json([
                'status' => true,
                'errors' => [],
            ]);

        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

    // /upload/change image from profile
    public function updateProfileImg(Request $request)
    {
        // dd($request->all());
        $id = Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048' // Adjust max size as needed
        ]);
        if ($validator->passes()) {

            $image = $request->image;
            $ext = $image->getClientOriginalExtension();
            $imageName = $id . '-' . time() . '.' . $ext;
            $image->move(public_path('/profile_img/'), $imageName);

            // $sourcePath = public_path('/profile_img/'. $imageName);
            // $manager = new ImageManager(Driver::class);
            // $image = $manager->read($sourcePath);

            // crop the best fitting 5:3 (150x150) ratio and resize to 150x150 pixel
            // $image->cover(150, 150);
            // $image->toPng()->save(public_path('/profile_img/thumb/'. $imageName));

            // This code will create a small thumbnail
            $sourcePath = public_path() . '/profile_img/' . $imageName;
            $destPath = public_path() . '/profile_img/thumb/' . $imageName;
            $image = Image::make($sourcePath);
            $image->fit(300, 275);
            $image->save($destPath);

            // delete old profile image, when user update his/her new image
            File::delete(public_path('/profile_img/' . Auth::user()->image));
            File::delete(public_path('/profile_img/thumb/' . Auth::user()->image));

            User::where('id', $id)->update(['image' => $imageName]);

            Session()->flash('success', 'Profile image updated successfully.');
            return response()->json([
                'status' => true,
                'errors' => [],
            ]);

        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

    public function saveJob(Request $request){

        $rules = [
            'title' => 'required|min:5|max:200',
            'vacancy' => 'required|integer',
            'location' => 'required|max:50',
            'description' => 'required',
            // 'description_wysiwyg' => 'required', // Rules for the WYSIWYG editor
            'experience' => 'required',
            'company_name' => 'required|min:3|max:75',
        ];

        $validator = Validator::make($request->all(),$rules);
        if($validator->passes()){

            $job = new Job();
            $job->title = $request->title;
            $job->user_id = Auth::user()->id;
            $job->vacancy = $request->vacancy;
            $job->salary = $request->salary;
            $job->location = $request->location;
            $job->description = $request->description;
            $job->benefits = $request->benefits;
            $job->responsibility = $request->responsibility;
            $job->qualifications = $request->qualifications;
            $job->keywords = $request->keywords;
            $job->experience = $request->experience;
            $job->company_name = $request->company_name;
            $job->company_location = $request->company_location;
            $job->company_website = $request->company_website;
            $job->save();

            return response()->json([
                'message' => 'Job Post created.',
                'status' => true,
                'errors' => [],
            ]);
        }else{
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }


    // Show all jobs
    public function myJobs()
    {
        // dd(Auth::user()->id);
        $jobs = Job::where('user_id', Auth::user()->id)->orderBy('created_at', 'DESC')->get();
        return response()->json($jobs);
    }

    // Update Job
    public function updateJob($jobId, Request $request)
    {

        $rules = [
            'title' => 'required|min:5|max:200',
            'vacancy' => 'required|integer',
            'location' => 'required|max:50',
            'description' => 'required',
            'experience' => 'required',
            'company_name' => 'required|min:3|max:75',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->passes()) {

            $job = Job::find($jobId);
            if(empty($job)){
                return response()->json([
                    'error' => 'Job post not found.',
                ],404 );
            }
            $job->title = $request->title;
            $job->user_id = Auth::user()->id;
            $job->vacancy = $request->vacancy;
            $job->salary = $request->salary;
            $job->location = $request->location;
            $job->description = $request->description;
            $job->benefits = $request->benefits;
            $job->responsibility = $request->responsibility;
            $job->qualifications = $request->qualifications;
            $job->keywords = $request->keywords;
            $job->experience = $request->experience;
            $job->company_name = $request->company_name;
            $job->company_location = $request->company_location;
            $job->company_website = $request->company_website;
            $job->save();

            Session()->flash('success', 'Job updated successfully.');
            return response()->json([
                'message' => 'Job updated.',
                'status' => true,
            ]);

        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }


    // This method will delete job
    public function deleteJob(Request $request)
    {

        $job = Job::where([
            'user_id' => Auth::user()->id,
            'id' => $request->jobId,
        ])->first();

        if ($job == null) {
            return response()->json([
                'status' => true,
                'error'=> 'Either Job deleted or not found.',
            ]);
        }

        Job::where('id', $request->jobId)->delete();
        return response()->json([
            'status' => true,
            'message'=> 'Job deleted successfully.'
        ]);

    }

    public function myJobApplications()
    {

        $jobApplications = JobApplication::where('user_id', Auth::user()->id)->with(['job', 'job.applications'])->orderBy('created_at', 'DESC')->paginate(10);

        return response()->json([$jobApplications]);
    }


    // remove applied jobs
    public function removeJobs(Request $request)
    {

        $jobApplication = JobApplication::where(
            [
                'id' => $request->id,
                'user_id' => Auth::user()->id,
            ]
        )->first();
        if ($jobApplication == null) {
            return response()->json([
                'status' => false,
                'error'=>'Job application not found'
            ]);
        }
        JobApplication::find($request->id)->delete();
        return response()->json([
            'status' => true,
            'success'=> 'Job application removed successfully.'
        ]);
    }

    // Change password function
    public function changePassword(Request $request)
    {
        $data = [
            'old_password' => 'required',
            'new_password' => 'required|min:5',
            'confirm_password' => 'required|same:new_password',
        ];
        $validator = Validator::make($request->all(), $data);
        if ($validator->passes()) {

            $user = User::select('id', 'password')->where('id', Auth::user()->id)->first();
            if (!Hash::check($request->old_password, $user->password)) {

                return response()->json([
                    'status' => true,
                    'error'=>'Your old password is incorrect, Please try again'
                ]);
            }

            User::where('id', $user->id)->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json([
                'status' => true,
                'success'=>'Your have successfully change your password'
            ]);

        } else {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

}
