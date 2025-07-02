<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\ServiceProvider;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller {
    // POST /api/register

    public function login( Request $request ) {
        $request->validate( [
            'email' => 'required|email',
            'password' => 'required',
        ] );

        $user = User::where( 'email', $request->email )->first();

        if ( !$user || !Hash::check( $request->password, $user->password ) ) {
            return response()->json( [ 'message' => 'Invalid credentials' ], 401 );
        }

        $token = $user->createToken( 'auth_token' )->plainTextToken;

        return response()->json( [
            'user' => $user,
            'token' => $token,
        ] );
    }

    public function register( Request $request ) {
        $validated = $request->validate( [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
            'password' => 'required|confirmed',
            'is_provider' => 'boolean',
        ] );

        $user = User::create( [
            'name' => $validated[ 'name' ],
            'email' => $validated[ 'email' ],
            'phone' => $validated[ 'phone' ],
            'password' => bcrypt( $validated[ 'password' ] ),
            'is_provider' => $validated[ 'is_provider' ] ?? false,
        ] );

        if ( $user->is_provider ) {
            ServiceProvider::create( [
                'user_id'      => $user->id,
                'bike_model'   => null,
                'plate_number' => null,
            ] );

            $token = $user->createToken( 'auth_token' )->plainTextToken;

            return response()->json( [
                'user' => $user,
                'token' => $token,
            ] );
        }

        // If not provider, still return token and user
        $token = $user->createToken( 'auth_token' )->plainTextToken;

        return response()->json( [
            'user' => $user,
            'token' => $token,
        ] );
    }

    // GET /api/me

    public function me( Request $request ) {
        return response()->json( $request->user() );
    }

    // PATCH /api/me

    public function updateProfile( Request $request ) {
        $user = $request->user();

        // validate textual fields + avatar
        $data = $request->validate( [
            'name'            => 'sometimes|string|max:255',
            'phone'           => 'sometimes|string|unique:users,phone,'.$user->id,
            'dob'             => 'sometimes|date',
            'blood_group'     => 'sometimes|string|in:A+,A−,B+,B−,AB+,AB−,O+,O−',
            'address'         => 'sometimes|string|max:500',
            'avatar'          => 'nullable|image|max:2048', // jpg/png
        ] );

        // handle avatar upload
        if ( $request->hasFile( 'avatar' ) ) {
            // delete old avatar if present
            if ( $user->avatar_url ) {
                // strip leading '/storage/' and delete
                $path = str_replace( '/storage/', '', $user->avatar_url );
                Storage::disk( 'public' )->delete( $path );
            }
            // store new one
            $path = $request->file( 'avatar' )->store( 'avatars', 'public' );
            $data[ 'avatar_url' ] = '/storage/' . $path;
        }

        // update user
        $user->update( $data );

        return response()->json( $user );
    }

    // POST /api/logout

    public function logout( Request $request ) {
        $request->user()->tokens()->delete();

        return response()->json( [ 'message' => 'Logged out' ] );
    }
}
