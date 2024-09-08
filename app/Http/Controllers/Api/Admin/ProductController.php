<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::get();
        if ($products->isEmpty()) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Product(s) does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View All Products',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed all products at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => auth()->id(),
            'subject_type' => get_class($products),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful',"message"=>"Product fetched successfully","data"=>$products],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => ['required','string'],
            'product_type' => ['required','string'],
            'description' => ['required','string'],
            'SKU' => ['required','string', 'unique:products,SKU'],
            'tag' => ['required'],
            'category_id' => ['required','exists:categories,id'],
            'points' => ['required','integer'],
            'location_id' => ['required','exists:locations,id'],
            'variation' => ['required'],
            'available_qty' => ['required','integer'],
            'created_by' => ['required','exists:users,id'],
            'status' => ['required','string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $tag = json_encode($request->tag);
        $variation = json_encode($request->variation);

        $product = Product::create([
            'product_name' => $request->product_name,
            'product_type' => $request->product_type,
            'description' => $request->description,
            'SKU' => $request->SKU,
            'tag' => $tag,
            'category_id' => $request->category_id,
            'points' => $request->points,
            'location_id' => $request->location_id,
            'variation' => $variation,
            'available_qty' => $request->available_qty,
            'created_by' => $request->created_by,
            'status' => $request->status,
        ]);
        ActivityLog::create([
            'action' => 'Created New product',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' created new product at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $product->id,
            'subject_type' => get_class($product),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>201,'response'=>'Product Created','message'=>'Product created successfully','data'=>$product], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $product = Product::where('id', $request->id)->first();
        if (!$product) {
            return response()->json(['status'=>404,'response'=>'Not Found','message'=>'Product does not exist'], 404);
        }
        ActivityLog::create([
            'action' => 'View A product Details',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' viewed a product details at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $product->id,
            'subject_type' => get_class($product),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Successful','message'=>'Product successfully fetched', 'data'=>$product], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => ['required','string'],
            'product_type' => ['required','string'],
            'description' => ['required','string'],
            'SKU' => ['required','string'],
            'tag' => ['required'],
            'category_id' => ['required','exists:categories,id'],
            'points' => ['required','integer'],
            'location_id' => ['required','exists:locations,id'],
            'variation' => ['required'],
            'available_qty' => ['required','integer'],
            'created_by' => ['required','exists:users,id'],
            'status' => ['required','string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>422,'response'=>'Unprocessable Content','errors' => $validator->errors()->all()], 422);
        }

        $tag = json_encode($request->tag);
        $variation = json_encode($request->variation);

        $product = Product::find($request->id);
        // Prepare the data to be updated
        $data = [
            'product_name' => $request->product_name,
            'product_type' => $request->product_type,
            'description' => $request->description,
            'SKU' => $request->SKU,
            'tag' => $tag,
            'category_id' => $request->category_id,
            'points' => $request->points,
            'location_id' => $request->location_id,
            'variation' => $variation,
            'available_qty' => $request->available_qty,
            'created_by' => $request->created_by,
            'status' => $request->status,
        ];

        // Update the product
        $product->update($data);
        ActivityLog::create([
            'action' => 'Updated Product',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' updated a product at '.Carbon::now()->format('h:i:s A'),
            'subject_id' => $product->id,
            'subject_type' => get_class($product),
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>200,'response'=>'Product Updated','message'=>'Product updated successfully','data'=>$product], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $product = Product::find($request->id);
        if (!$product) {
            return response()->json(['status'=>404,'response'=>'Not Found','message' => 'Not Found!'], 404);
        }
        $product->delete();
        ActivityLog::create([
            'action' => 'Deleted A Product',
            'description' => auth()->user()->last_name.' '.auth()->user()->first_name.' deleted a product at '.Carbon::now()->format('h:i:s A'),
            'subject_type' => get_class($product),
            'subject_id' => $request->id,
            'user_id' => auth()->id(),
        ]);
        return response()->json(['status'=>204,'response'=>'No Content','message' => 'Product Deleted successfully']);
    }
}
