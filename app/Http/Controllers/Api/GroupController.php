<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Group;

class GroupController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = Group::where('id', '!=', 0)->orderBy('sort')->get(); // 23.7.2024 0 is trash
        return $this->getJsonResponse(true, 'Thành công', $groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = new Group();
        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->created_by = $user->id;
        $group->save();

        return $this->getJsonResponse(true, 'Thành công', $group);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);

        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->save();

        return $this->getJsonResponse(true, 'Cập nhật thành công', null);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại!', null);

        if ($group->profiles->count() > 0)
            return $this->getJsonResponse(false, 'Không thể xóa Group có liên kết với Profiles!', null);

        $group->delete();

        return $this->getJsonResponse(true, 'Xóa thành công', null);
    }

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = Group::count();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }
}
