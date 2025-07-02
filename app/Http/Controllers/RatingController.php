<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

class RatingController extends Controller {

    // POST /api/ratings
    public function store( Request $request ) {
        $data = $request->validate( [
            'request_id' => 'required|exists:service_requests,id',
            'ratee_id'   => 'required|exists:users,id',
            'stars'      => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string',
        ] );

        $rating = Rating::create( [
            'request_id' => $data[ 'request_id' ],
            'rater_id'   => $request->user()->id,
            'ratee_id'   => $data[ 'ratee_id' ],
            'stars'      => $data[ 'stars' ],
            'comment'    => $data[ 'comment' ],
        ] );

        // update avg_rating if rating a provider
        if ( $ratee = ServiceProvider::where( 'user_id', $data[ 'ratee_id' ] )->first() ) {
            $ratee->avg_rating = Rating::where( 'ratee_id', $ratee->user_id )->avg( 'stars' );
            $ratee->save();
        }

        return response()->json( $rating, 201 );
    }
}
