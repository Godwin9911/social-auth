<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Validator;
use App\User;
use App\SocialIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use Illuminate\Support\Facades\Hash;

class SocialAuthController extends Controller
{
    /* public function __construct(JWTAuth $jwt)
    {
        $this->jwt = $jwt;
    } */
    /* public function expireTime() {
        $myTTL = 120960; //minutes
        return auth()->factory()->setTTL($myTTL);
    } */
    public function authenticate()
    {
        // $this->expireTime();
        try {
            $validatedUser = Validator::make( request()->all(),[
                'id' => 'required',
                'name' => 'required',
                'firstName' => 'required',
                'lastName' => 'required',
                'email' => 'required',
                'photoUrl' => 'required',
                'provider' => 'required'
            ]);

            if ($validatedUser->fails()){
                $res['message'] = $validatedUser->messages();
                return response()->json($res, 401);
            }
            $user = $this->findOrCreateUser(
                request()->only('id','name', 'firstName', 'lastName', 'email', 'photoUrl', 'provider', 'password')
            );

            $credentials = request(['email', 'password']);

            try {
                if (! $token = JWTAuth::attempt($credentials)) {
                    return response()->json(['error' => 'invalid_credentials'], 400);
                }
            } catch (JWTException $e) {
                return response()->json(['error' => 'could_not_create_token'], 500);
            }

            return response()->json(compact('user', 'token'), 200);

        } catch (\Exception $e) {

            $res['message'] = 'Server Error';
            return response()->json($res, 500);

        }

    }

    public function findOrCreateUser(array $data)
    {
        $account = SocialIdentity::where('user_id', $data['id'])
                    ->where('provider', $data['provider'])
                    ->first();

        if($account) {
            return $account->user;
        } else {
            $user = User::where('email', $data['email'])->first();

            if(! $user) {
                $user = new User();

                $user = User::create([
                    'id' => $data['id'],
                    'firstName' => $data['firstName'],
                    'lastName' => $data['lastName'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'photoUrl' => $data['photoUrl'],
                    'password' => Hash::make($data['password']),
                ]);

            }

            //TODO Update to facebook profile

            $user->identities()->create([
                'provider' => $data['provider']
            ]);

            return $user;
        }
    }

    public function getAuthenticatedUser()
    {
        try {

                if (! $user = JWTAuth::parseToken()->authenticate()) {
                        return response()->json(['user_not_found'], 404);
                }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

                return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

                return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {

                return response()->json(['token_absent'], $e->getStatusCode());

        }

        return response()->json(compact('user'));
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    protected function respondWithToken($token)
    {
      return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => auth('api')->factory()->getTTL() * 60
      ]);
    }
}


