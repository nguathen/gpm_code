<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Profile;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

// Simple, so not use middleware
class AdminController extends Controller
{
    public function index(){
        $loginUser = Auth::user();
        if ($loginUser == null || $loginUser->role != 2)
            return redirect('/admin/auth');

        $users = User::where('id', '<>', $loginUser->id)->orderBy('role', 'desc')->get();

        $storageType = 's3';
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting != null)
            $storageType = $setting->value;

        return view('index', compact('users', 'storageType'));
    }

    public function toogleActiveUser($id) {
        $user = User::find($id);
        if ($user == null)
            return;

        if ($user->active == 0) $user->active = 1;
        else if ($user->active == 1) $user->active = 0;

        $user->save();
        return redirect()->back();
    }

    public function setStorageType(Request $request){
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting == null)
            $setting = new Setting();

        $setting->name = 'storage_type';
        $setting->value = $request->type;
        $setting->save();

        if ($setting->value == 'hosting'){
            Artisan::call('storage:link');
        }

        return redirect()->back()->with('msg', 'Storge type is changed to: '.$setting->value);
    }

    public function resetProfileStatus(){
        $profiles = Profile::get();
        foreach ($profiles as $profile){
            $profile->status = 1;
            $profile->save();
        }
        return redirect()->back()->with('msg', 'Reset profile status successfully');
    }
}
