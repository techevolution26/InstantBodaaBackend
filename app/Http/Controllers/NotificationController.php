<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller {

    // GET/api/notifications

    public function index( Request $request ) {
        return response()->json(
            $request->user()
            ->notifications()
            ->orderBy( 'created_at', 'desc' )
            ->paginate( 20 )
        );
    }
}
