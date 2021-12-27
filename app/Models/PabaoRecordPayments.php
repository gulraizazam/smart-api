<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpFoundation\Request;

class PabaoRecordPayments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'amount', 'date_paid', 'pabao_record_id', 'created_by','updated_by', 'account_id','created_at','updated_at','deleted_at'
    ];

    protected $table = 'pabao_record_payments';

    /**
     * Get the lead that owns the comments.
     */
    public function pabao_record()
    {
        return $this->belongsTo('App\Models\PabaoRecords');
    }

    /**
     * Get the User that owns the Lead comment.
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'created_by');
    }

    /*
     * Save reocord in payment record module
     */
    static public function CreateRecord($request,$account_id,$user_id){

        $data['amount'] = $request->amount;
        $data['date_paid'] = $request->date_paid;
        $data['pabao_record_id'] = $request->id;
        $data['created_by'] = $user_id;
        $data['updated_by'] = $user_id;
        $data['account_id'] = $account_id;

        $record = self::create($data);

        return $record;
    }

    static public function DeleteRecord($id){

        $payment_record = self::find($id);
        if($payment_record){

            $payment_record->delete();
            return true;
        } else {
            return false;
        }
    }
}
