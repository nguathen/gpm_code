<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Group;
use App\Models\User;

class SettingController extends BaseController
{
    /**
     * Get s3 setting
     *
     * @return string
     */
    public function getS3Setting(Request $request)
    {
        try {
            $apiKey = env('S3_KEY');
            $apiSecret = env('S3_PASSWORD');
            $apiBucket = env('S3_BUCKET');
            $apiRegion = env('S3_REGION');

            $reps = ['s3_api_key' => $apiKey, 's3_api_secret' => $apiSecret, 's3_api_bucket' => $apiBucket, 's3_api_region' => $apiRegion];
            return $this->getJsonResponse(true, 'OK', $reps);
        } catch (\Exception $ex) {
            return $this->getJsonResponse(false, 'Chưa cài đặt đủ thông tin S3 API', null);
        }
    }

    // Set or apply setting
    private function setSetting($key, $value){
        $setting = Setting::where('name', $key)->first();

        if ($setting == null){
            $setting = new Setting();
            $setting->name = $key;
            $setting->value = $value;
            $setting->save();
            return;
        }

        $setting->value = $value;
        $setting->save();
    }

    public function getStorageTypeSetting()
    {
        $setting = Setting::where('name', 'storage_type')->first();
        if ($setting == null)
            return $this->getJsonResponse(true, 'OK', 's3');

        return $this->getJsonResponse(true, 'OK', $setting->value);
    }

    // 23.7.2024 check version of private server
    public function getPrivateServerVersion()
    {
        $version = 11;
        $response = [];
        // check trash group
        if($version >= 11) {
            $groupTrashId = 0;
            $groupTrashName = 'Trash auto create (update private server version 11)';

            if (!Group::where('id', $groupTrashId)->exists()) {
                try {
                    $userAdmin = User::where('role', 2)->first()->id;

                    $group = new Group();
                    $group->name = $groupTrashName;
                    $group->sort = 2147483647; // int max
                    $group->created_by = $userAdmin;
                    $group->save();

                } catch (\Exception $e){
                    $version -= 1;
                    $response['message'] = 'Can not create Trash group';
                }
            }

            $group = Group::where('name', $groupTrashName)->first();

            if ($group == null) {
                $version -= 1;
            }
            else if($group->id != $groupTrashId) {
                try {
                    $group->id = $groupTrashId;
                    $group->save();
                } catch (\Exception $e){
                    $response['message'] = 'Can not update id group Trash';
                    $version -= 1;
                }
            }
        }

        $response['version'] = $version;
        return $this->getJsonResponse(true, 'OK', $response);
    }

}
