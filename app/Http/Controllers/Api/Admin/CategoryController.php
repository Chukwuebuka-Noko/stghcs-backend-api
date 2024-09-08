<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::get();
        if ($categories->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Category(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Categories',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all categories at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($categories),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Category fetched successfully","data"=>$categories],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required','string','unique:categories,category']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $category = Category::create([
            'category' => $request->category
        ]);
        ActivityLog::create([
            'action' => 'Created New category',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new category at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $category->id,
            'subject_type' => get_class($category),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Created Category','message'=>'Category created successfully','data'=>$category], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $category = Category::where('id', $request->id)->first();
        if (!$category) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Category does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A Category Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a category details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $category->id,
            'subject_type' => get_class($category),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Category successfully fetched', 'data'=>$category], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required','string']
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }
        $category = Category::find($request->id);
        // Prepare the data to be updated
        $data = [
            'category' => $request->category
        ];

        // Update the category
        $category->update($data);
        ActivityLog::create([
            'action' => 'Updated Category',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a category at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $category->id,
            'subject_type' => get_class($category),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Category Updated','message'=>'Category updated successfully','data'=>$category], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $category = Category::find($request->id);
        if (!$category) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $category->delete();
        ActivityLog::create([
            'action' => 'Deleted A Category',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a category at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($category),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Category Deleted successfully']);
    }
}
