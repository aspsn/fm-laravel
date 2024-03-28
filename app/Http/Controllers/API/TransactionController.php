<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Exception;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $food_id = $request->input('food_id');
        $status = $request->input('status');
        $limit = $request->input('limit', 6);

        if ($id) {
            $transaction = Transaction::with('user_id', 'food_id')->find($id);

            if ($transaction) {
                return ResponseFormatter::success(
                    $transaction,
                    'Data transaksi berhasil di temukan'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data transaksi tidak di ada',
                    404
                );
            }
        }

        $transaction = Transaction::with(['food', 'user'])
            ->where('user_id', Auth::user()->id);

        if ($food_id) {
            $transaction->where('food_id' . $food_id);
        }

        if ($status) {
            $transaction->where('status' . $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Data list transaksi berhasil di ambil'
        );
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success(
            $transaction,
            'Berhasil mengupdate transaksi'
        );
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'food_id' => 'required|exists::food,id',
            'user_id' => 'required|exists::user,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required'
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => ''
        ]);

        //Konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        //Panggil transaksi yang tadi dibuat
        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        //Membuat transaksi midtrans
        $midtrans = [
            'transactions_details' => [
                'order_id' => $transaction->id,
                'gross_amount ' => (int) $transaction->total
            ],

            'customer_details' => [
                'fisrt_name' => $transaction->user->name,
                'email ' => (int) $transaction->user->email,
            ],

            'enable_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => []
        ];

        //Memanggil midtrans
        try {
            //Ambil halaman payment midtrans
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;
            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            //Mengembalikan data ke API
            return ResponseFormatter::success(
                $transaction,
                'Transaksi berhasil'
            );
        } catch (Exception $e) {
            return ResponseFormatter::error(
                $e->getMessage(),
                'Transaksi gagal'
            );
        }
    }
}
