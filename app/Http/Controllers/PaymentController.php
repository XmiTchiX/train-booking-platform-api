<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\ReservationController as Tool;

class PaymentController extends Tool
{

    public function create(Request $request){
        require_once('C:\Users\HP\Documents\Project\2cp_project_API\vendor\autoload.php');

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $id=auth('sanctum')->id();
        $user=User::find($id);

        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
        if($user->stripe_id==null){
            $user->createAsStripeCustomer();
            $stripeId = $user->stripe_id;
            $stripe->customers->update($stripeId, [
                'name' => $user->first_name." ".$user->last_name,
                'source' => $request->stripeToken
            ]);
        } else {
            $stripeId = $user->stripe_id;
            $stripe->customers->update($stripeId, [
                'source' => $request->stripeToken
            ]);
        }


        if($request->classe == 'F'){
            $charge = \Stripe\Charge::create(
                [
                    "amount" => $this->pricing($id,$request->landing_station, $request->boarding_station)['F'] * count($request->passengers),
                    "currency" => "dzd",
                    "customer" => $stripeId,
                    "description" => "Payment for First Class"
                ]
            );
        } else if($request->classe == 'S') {
            $charge = \Stripe\Charge::create(
                [
                    "amount" => $this->pricing($id,$request->landing_station, $request->boarding_station)['S'] * count($request->passengers),
                    "currency" => "dzd",
                    "customer" => $stripeId,
                    "description" => "Payment for Second Class"
                ]
            );
        }

        foreach($request->passengers as $passenger){
            Ticket::create([
                'user_id' => $user->id,
                'travel_id' => $request->tid,
                'passenger_name' => $passenger->name,
                'travel_class' => $request->classe,
                'payment_method' => 'card',
                'payment_token' => $charge['id'],
                'validated' => false,
                'boarding_station' => $request->boarding_station,
                'landing_station' => $request->landing_station,
                'price' => $charge['amount'] / 100,
                'qrcode_token' => Str::random(64)
            ]);
        }

        $this->PassNumberInc($request->tid, $request);

        return response()->json([
            "status" => "payment made, ticket created",
            "message" => "ok"
        ], 200);
    }
}
