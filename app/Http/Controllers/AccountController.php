<?php
namespace App\Http\Controllers;

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

class AccountController extends Controller
{
    // This method will show user registration page
    public function registration()
    {
        return view('front.account.registration');
    }

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

    // This method will show user login page
    public function login()
    {
        return view('front.account.login');
    }

    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:5',
        ]);
        if ($validator->passes()) {

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return redirect()->route('account.profile');
            } else {
                return redirect()->route('account.login')->with('error', 'Either email/password is incorrect');
            }

        } else {
            return redirect()->route('account.login')
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }
    }
    public function loginApi(Request $request): JsonResponse
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
    public function logoutApi(Request $request): JsonResponse
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

    public function logout()
    {
        Auth::logout();
        return redirect()->route('account.login');
    }


    public function createJob()
    {

        return view('front.account.job.create');
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

            Session()->flash('success','Job added successfully.');
            return response()->json([
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

        //metioned the code of the paginator in AppServiceProvider Class otherwise your paginator will not work perfectly

        $jobs = Job::where('user_id', Auth::user()->id)->orderBy('created_at', 'DESC')->paginate(10);
        return view('front.account.job.my-jobs', [
            'jobs' => $jobs,
        ]);
    }

    // edit Job page
    public function editJob($id)
    {

        // If user write another user id through url show him 404 page
        $job = Job::where([
            'user_id' => Auth::user()->id,
            'id' => $id,
        ])->first();

        if ($job == null) {
            abort(404);
        }

        return view('front.account.job.edit', [
            'job' => $job,
        ]);
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


    // This method will delete job
    public function deleteJob(Request $request)
    {

        $job = Job::where([
            'user_id' => Auth::user()->id,
            'id' => $request->jobId,
        ])->first();

        if ($job == null) {
            Session()->flash('error', 'Either Job deleted or not found.');
            return response()->json([
                'status' => true,
            ]);
        }

        Job::where('id', $request->jobId)->delete();
        Session()->flash('success', 'Job deleted successfully.');
        return response()->json([
            'status' => true,
        ]);

    }

    public function myJobApplications()
    {

        $jobApplications = JobApplication::where('user_id', Auth::user()->id)->with(['job', 'job.applications'])->orderBy('created_at', 'DESC')->paginate(10);

        return view('front.account.job.my-job-application', [
            'jobApplications' => $jobApplications,
        ]);
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
            Session()->flash('error', 'Job application not found');
            return response()->json([
                'status' => false,
            ]);
        }
        JobApplication::find($request->id)->delete();
        Session()->flash('success', 'Job application removed successfully.');
        return response()->json([
            'status' => true,
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

                Session::flash('error', 'Your old password is incorrect, Please try again');
                return response()->json([
                    'status' => true,
                ]);
            }

            User::where('id', $user->id)->update([
                'password' => Hash::make($request->new_password),
            ]);

            Session()->flash('success', 'Your have successfully change your password');
            return response()->json([
                'status' => true,
            ]);

        } else {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

}
