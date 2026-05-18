<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderRefund extends Model
{
    use HasFactory;
    use ParsesDateRange;

    protected $fillable = [
        'account_id',
        'order_id',
        'patient_id',
        'buyer_type',
        'employee_id',
        'total_price',
        'status',
        'created_by',
        'location_id',
        'payment_mode',
        'quantity',
    ];

    protected $table = 'order_refunds';

    public static function refund($id, $request)
    {

        $old_order = OrderRefund::where('order_id', $id)->first();
        $order = Order::find($id);
        $productTotals = [];
        $new_order = [];
        // Iterate through the arrays
        for ($i = 0; $i < count($request['product_id']); $i++) {
            $productId = $request['product_id'][$i];
            $productPrice = (float) $request['product_price'][$i];
            $quantity = (int) $request['quantity'][$i];
            // Calculate the total for this product
            $total = $productPrice * $quantity;
            // Store the total in the result array, using the product ID as the key
            $productTotals[$productId] = $total;
        }
        $new_order['created_by'] = Auth::id();
        $new_order['total_price'] = array_sum($productTotals);
        unset($new_order['id']);
        $refund = new OrderRefund;
        $refund->account_id = Auth::user()->account_id;
        $refund->order_id = $order->id;
        $refund->patient_id = $order->patient_id;
        $refund->total_price = $new_order['total_price'];
        $refund->created_by = $new_order['created_by'];
        $refund->location_id = $order->location_id;
        $refund->payment_mode = $order->payment_mode;
        $refund->quantity = array_sum($request->quantity);
        $refund->save();
        Order::whereId($order->id)->update(['is_refunded' => 1]);

        return $refund;
    }

    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false, $order_type = 'sale')
    {
        $where = self::general_filters($request, $account_id, $apply_filter);
        $product_id = [];
        if ($request['query'] != null) {
            if ($request['query']['search']['product_id'] != null) {
                $product_id = Product::where('name', 'like', '%'.$request['query']['search']['product_id'].'%')->pluck('id')->toArray();
            }
        }

        if (count($where)) {

            return OrderRefund::where($where)

                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderrefunddetails.product')->whereHas('orderrefunddetails.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                })
                ->count();

        } else {

            return OrderRefund::where(function ($query) {
                $query->whereIn('location_id', ACL::getUserCentres());

            })
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderrefunddetails.product')->whereHas('orderrefunddetails.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                })
                ->count();
        }
    }

    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false, $order_type = 'sale')
    {
        $where = self::general_filters($request, $account_id, $apply_filter);
        $product_id = [];
        if ($request['query'] != null) {
            if ($request['query']['search']['product_id'] != null) {
                $product_id = Product::where('name', 'like', '%'.$request['query']['search']['product_id'].'%')->pluck('id');
            }
        }

        if (count($where)) {

            return OrderRefund::with('patients')->where($where)
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderrefunddetails.product')->whereHas('orderrefunddetails.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                }, fn ($q) => $q->with('orderrefunddetails.product'))
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres());
                })
                // ->when($order_type != 'refund', function ($q) use ($order_type) {
                //     $q->whereNull('refund_order_id')->where('order_type', $order_type);
                // }, function ($q) use ($order_type) {
                //     $q->where('order_type', $order_type);
                // })
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
        } else {

            return OrderRefund::with('patients')->where($where)
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderrefunddetails.product')->whereHas('orderrefunddetails.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                }, fn ($q) => $q->with('orderrefunddetails.product'))
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres());
                })
                // ->when($order_type != 'refund', function ($q) use ($order_type) {
                //     $q->whereNull('refund_order_id')->where('order_type', $order_type);
                // }, function ($q) use ($order_type) {
                //     $q->where('order_type', $order_type);
                // })
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
        }
    }

    public static function general_filters($request, $account_id, $search = false, $filter_flag = false)
    {

        $where = [];
        $filters = getFilters($request->all());
        [$start_date_time, $end_date_time] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if ($filters) {
            if (hasFilter($filters, 'order_id')) {
                $where[][] = ['order_id' => $filters['order_id']];
            }
            if (hasFilter($filters, 'patient_id')) {
                $where[][] = ['patient_id' => $filters['patient_id']];
            }
            if (hasFilter($filters, 'location_type')) {
                if ($filters['location_type'] == 'branch') {
                    $where[][] = ['location_id' => $filters['location']];
                } elseif ($filters['location_type'] == 'warehouse') {
                    $where[][] = ['warehouse_id' => $filters['location']];
                } else {
                    Filters::forget(Auth::user()->id, 'location', 'name');
                }
            }
            if (hasFilter($filters, 'created_by')) {
                $where[][] = ['created_by' => $filters['created_by']];
            }
            if (hasFilter($filters, 'updated_by')) {
                $where[][] = ['updated_by' => $filters['updated_by']];
            }
            if (hasFilter($filters, 'created_at')) {
                $where[] = ['created_at', '>=', $start_date_time];
                $where[] = ['created_at', '<=', $end_date_time];
            }

        }

        return $where;
    }

    public function patients(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function orderrefunddetails(): HasMany
    {
        return $this->hasMany(OrderRefundDetail::class, 'order_refund_id')->with('product');
    }
}
