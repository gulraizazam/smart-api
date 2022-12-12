<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\Queue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SyncAppointmentsFire1 extends Queue
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;

    /**
     * Holds Illuminate\Database\Eloquent\Collection $setting object
     *
     */
    private $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        echo 'Sync Appointments is fired' . "\n";

        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('brightpearl');
    }

    /**
     * Handle the event.
     *
     * @param  void
     * @return void|string
     */
    public function handle()
    {
//        echo 'so far so good' . "\n";
//        return true;
//
//        $payload = $this->payload;
//
//        /**
//         * Intialize Brightpearl Object to interact with APIs
//         */
//        $brightpearl = Brightpearl::settings($payload['brightpearl']);
//
//        $response = $brightpearl->getOrder(str_replace('/order/', '', $payload['url']));
//
////                dd($response);
//
//        if($response['status']) {
//            /**
//             * add/update data into system
//             */
//            foreach ($response['data']['response'] as $row) {
//
//                Order::updateOrCreate(array(
//                    'order_id' => $row['id'],
//                ), array(
//                    'order_id' => $row['id'],
//                    'parent_order_id' => $row['parentOrderId'],
//                    'order_type_code' => $row['orderTypeCode'],
//                    'order_status_id' => isset($row['orderStatus']['orderStatusId']) ? $row['orderStatus']['orderStatusId'] : 0,
//                    'name' => isset($row['orderStatus']['name']) ? $row['orderStatus']['name'] : '',
//                    'order_payment_status' => isset($row['orderPaymentStatus']) ? $row['orderPaymentStatus'] : '',
//                    'stock_status_code' => $row['stockStatusCode'],
//                    'allocation_status_code' => $row['allocationStatusCode'],
//                    'shipping_status_code' => $row['shippingStatusCode'],
//                    'placed_on' => Carbon::parse($row['placedOn'])->toDateString(),
//                    'created_at' => Carbon::parse($row['createdOn'])->toDateString(),
//                    'updated_at' => isset($row['updatedOn']) ? Carbon::parse($row['updatedOn'])->toDateString() : Carbon::now(),
//                    'created_by_id' => $row['createdById'],
//                    'price_list_id' => $row['priceListId'],
//                    'shipping_method_id' => isset($row['delivery']['shippingMethodId']) ? $row['delivery']['shippingMethodId'] : 0,
//
//                    'invoice_reference' => isset($row['invoices'][0]['invoiceReference']) ? $row['invoices'][0]['invoiceReference'] : '',
//                    'tax_date' => isset($row['invoices'][0]['taxDate']) ? Carbon::parse($row['invoices'][0]['taxDate'])->toDateString() : null,
//                    'due_date' => isset($row['invoices'][0]['dueDate']) ? Carbon::parse($row['invoices'][0]['dueDate'])->toDateString() : null,
//
//                    'accounting_currency_code' => isset($row['currency']['accountingCurrencyCode']) ? $row['currency']['accountingCurrencyCode'] : '',
//                    'order_currency_code' => isset($row['currency']['orderCurrencyCode']) ? $row['currency']['orderCurrencyCode'] : '',
//                    'exchange_rate' => isset($row['currency']['exchangeRate']) ? $row['currency']['exchangeRate'] : '',
//
//                    'net' => isset($row['totalValue']['net']) ? $row['totalValue']['net'] : 0.00,
//                    'tax_amount' => isset($row['totalValue']['taxAmount']) ? $row['totalValue']['taxAmount'] : 0.00,
//                    'base_net' => isset($row['totalValue']['baseNet']) ? $row['totalValue']['baseNet'] : 0.00,
//                    'base_tax_amount' => isset($row['totalValue']['baseTaxAmount']) ? $row['totalValue']['baseTaxAmount'] : 0.00,
//                    'base_total' => isset($row['totalValue']['baseTotal']) ? $row['totalValue']['baseTotal'] : 0.00,
//                    'total' => isset($row['totalValue']['total']) ? $row['totalValue']['total'] : 0.00,
//
//                    'staff_owner_contact_id' => isset($row['assignment']['current']['staffOwnerContactId']) ? $row['assignment']['current']['staffOwnerContactId'] : 0,
//                    'project_id' => isset($row['assignment']['current']['projectId']) ? $row['assignment']['current']['projectId'] : 0,
//                    'channel_id' => isset($row['assignment']['current']['channelId']) ? $row['assignment']['current']['channelId'] : 0,
//                    'lead_source_id' => isset($row['assignment']['current']['leadSourceId']) ? $row['assignment']['current']['leadSourceId'] : 0,
//                    'team_id' => isset($row['assignment']['current']['teamId']) ? $row['assignment']['current']['teamId'] : 0,
//
//                    'warehouse_id' => $row['warehouseId'],
//                ));
//
//                if(isset($row['orderRows']) && count($row['orderRows'])) {
//
//                    $OrderItemsArray = [];
//
//                    OrderItem::where('order_id', '=', $row['id'])->delete();
//
//                    foreach($row['orderRows'] as $orderItemId => $orderRow) {
//
//                        $OrderItemsArray[] = array(
//                            'order_id' => $row['id'],
//                            'order_item_id' => $orderItemId,
//                            'order_row_sequence' => $orderRow['orderRowSequence'],
//                            'product_id' => $orderRow['productId'],
//                            'product_name' => $orderRow['productName'],
//                            'product_sku' => isset($orderRow['productSku']) ? $orderRow['productSku'] : '',
//                            'quantity' => $orderRow['quantity']['magnitude'],
//
//                            'currency_code' => $orderRow['itemCost']['currencyCode'],
//                            'item_cost' => $orderRow['itemCost']['value'],
//
//                            'product_price' => $orderRow['productPrice']['value'],
//
//                            'discount_percentage' => $orderRow['discountPercentage'],
//
//                            'tax_rate' => $orderRow['rowValue']['taxRate'],
//                            'tax_calculator' => $orderRow['rowValue']['taxCalculator'],
//                            'net' => $orderRow['rowValue']['rowNet']['value'],
//                            'tax' => $orderRow['rowValue']['rowTax']['value'],
//                            'tax_class_id' => $orderRow['rowValue']['taxClassId'],
//
//                            'nominal_code' => $orderRow['nominalCode'],
//                        );
//                    }
//
//                    OrderItem::insert($OrderItemsArray);
//                }
//            }
//        }
//
//        echo $payload['url'] . " is processed \n\n";
    }
}