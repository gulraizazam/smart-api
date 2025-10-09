<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Auth;

class Voucher extends Model
{
    use HasFactory;
    protected $guarded =[];

    public function voucherHasLocations()
    {
        return $this->hasMany(VoucherHasLocations::class, 'voucher_id', 'id');
    }


    public static function createVoucher($data)
    {

        $record = self::create($data);

       

        return $record;
    }
    public function userVouchers()
    {
        return $this->hasMany(UserVouchers::class, 'voucher_id');
    }
    public static function updateVoucher($data, $id)
    {

        $old_data = (Voucher::find($id))->toArray();

        $record = Voucher::findOrFail($id);

        $record->update($data);

        return $record;
    }
    public static function inactiveRecord($id)
    {

        $voucher = Voucher::getData($id);

        if ($voucher == null) {

            return false;
        } else {

            $record = $voucher->update(['active' => 0]);

            

            return $record;
        }
    }
    public static function getData($id)
    {
        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }
    public static function activeRecord($id)
    {

        $voucher = Voucher::getData($id);

        if ($voucher == null) {

            return false;
        } else {

            $record = $voucher->update(['active' => 1]);

           

            return $record;
        }
    }
    public static function deleteRecord($id)
    {

        $voucher = Voucher::getData($id);

        if (!$voucher) {

            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.voucherTypes.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Voucher::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource.')->error()->important();
        }

        $record = $voucher->delete();

        //log request for delete for audit trail

        

        flash('Record has been deleted successfully.')->success()->important();

        return 'Record has been deleted successfully';
    }
    public static function isChildExists($id, $account_id)
    {
        if (
            VoucherHasLocations::where(['voucher_id' => $id])->count() 
            
        ) {
            return true;
        }

        return false;
    }
    public static function getVoucher($account_id)
    {

        $date = Carbon::now();

        return self::where([
            ['start', '<=', $date],
            ['end', '>=', $date],
            ['active', '=', '1'],
            ['account_id', '=', $account_id],
        ])->get();
    }
    public function dateFormat($date, $format = 'Y-m-d')
    {
        return date($format, strtotime($date));
    }

    
}
