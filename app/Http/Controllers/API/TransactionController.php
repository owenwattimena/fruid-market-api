<?php

namespace App\Http\Controllers\API;

use Exception;
use Midtrans\Config;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function all(Request $request){
        $id = $request->input('id');
        $limit = $request->input('limit',6);
        $food_id = $request->input('food_id');
        $status = $request->input('status');

        if($id){
            $transaction = Transaction::with(['food', 'user'])->find($id);
            if($transaction){
                return ResponseFormatter::success($transaction, 'Transaction data retrieved successfully');
            }else{
                return ResponseFormatter::error(null, 'Trsanction data is missing', 404);
            }
        }

        $transaction = transaction::with(['food', 'user'])->where('user_id', Auth::user()->id);

        if($food_id){
            $transaction->where('food_id', $food_id);
        }
        
        if($status){
            $transaction->where('status', $status);
        }
        

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Transaction list data was successfully retrieved'
        );
    }

    public function update(Request $request){
        $transaction = Transaction::findOrFail($id);
        $transaction->update($requset->all());
        return ResponseFormatter::success(
            $transaction,
            'Transaction was successfully updated'
        );
    }

    public function checkout(Request $request){
        $request->validate([
            'food_id' => 'required|exist:food,id',
            'user_id' => 'required|exist:user,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required',
        ]);

        $transaction = Transaction::create([
            'food_id'=>$request->food_id,
            'user_id'=>$request->user_id,
            'quantity'=>$request->quantity,
            'total'=>$request->total,
            'status'=>$request->status,
            'payment_url' => ''
        ]);

        //Midtrans Configuration
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Get transaction
        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        //Make Midtrans Transaction
        $midtrans = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => (int) $transaction->total
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => []
        ];
        //Calling Midtrans
        try {
            // get midtrans payment page
            $paymentUrl = Snap::createTransaction($midtrans)->reqirect_url;
            $transaction->payment_url = $paymentUrl;
            $transaction->save(); 
            
            //return data to API
            return ResponseFormatter::success($transaction, 'Transaction successfully');
        } catch (Exception $error) {
            # code...
            return ResponseFormatter::error($error->getMessage(), 'Transaction failed');
        }
    }
}