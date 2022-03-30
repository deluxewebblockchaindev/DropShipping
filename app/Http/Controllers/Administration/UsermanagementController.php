<?php

namespace App\Http\Controllers\Administration;

use Session;
use Mail;
use Config;
use Exception;

use Illuminate\Http\Request;
use Illuminate\Support\Facades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Login;
use App\Models\Customerdomains;
use App\Models\Tenstreetconfig;

class UsermanagementController extends Controller
{
    public function __construct()
    {
       $this->middleware('auth:api');
    }
    private function send_email($user) {
        $link = env("APP_URL","https://portal.yoko.us/");
        $link .= "users/email/verification/";
        $link .= $user->verification_token;

        $subject = "Verify your email address";
        $msg = "Dear User, <br>Please verify your email address to complete your registration by clicking the link below.<br>$link<br>Sincerely, <br>The Yoko Networks team";
        Mail::send([],[], function($message) use($user, $subject, $msg) {
            $message->to($user->emailaddress, $user->username)->subject($subject);
            $message->setBody($msg,'text/html'); // assuming text/plain
            $message->from(env("MAIL_FROM_ADDRESS", "noreply@yoko.us"), env("MAIL_FROM_NAME", "Yoko Networks"));
        });
        if (Mail::failures()) {
            //return response()->Fail('Sorry! Please try again latter');
            return false;
        } else {
            //return response()->json('Ok', 201);
            return true;
        }
    }    
    private function email_resettingpasswordinstruction($user) {
        $link = env("APP_URL","https://portal.yoko.us/");
        $link .= "password/reset/";
        $link .= $user->reset_token;

        $subject = "Instructions for resetting password";
        $msg = "Hi ".$user->name."!<br>We received a request to reset the password for your user account. You can change your password using the link below.<br>$link<br>If you haven't sent a request to change the password, you don't need to worry about this message.<br>Your password will not be changed unless you use the link above.<br>The Yoko Networks team";
        Mail::send([],[], function($message) use($user, $subject, $msg) {
            $message->to($user->emailaddress, $user->username)->subject($subject);
            $message->setBody($msg,'text/html'); // assuming text/plain
            $message->from(env("MAIL_FROM_ADDRESS", "noreply@yoko.us"), env("MAIL_FROM_NAME", "Yoko Networks"));
        });
        if (Mail::failures()) {
            //return response()->Fail('Sorry! Please try again latter');
            return false;
        } else {
            //return response()->json('Ok', 201);
            return true;
        }
    }
    public function add(Request $request)
    {
        if(User::where('name',$request->username)->first()) {
            throw new Exception("The username has been duplicated.");
        };
        if(User::where('email',$request->emailaddress)->first()) {
            throw new Exception("The email address has been registered already.");
        };
        $request->verification_token = create_guid();
        if($this->send_email($request)) {
            $user = User::create([
                'name' => $request->username,
                'email' => $request->emailaddress,
                'role_id' => $request->roleid,
                'verification_token' => $request->verification_token
            ]);
            return response()->json($user);
        } else {
            return response()->json(['result'=>'Please try again.']);
        }
    }
    public function addnormaluser(Request $request)
    {
        if(User::where('name',$request->username)->first()) {
            throw new Exception("The username has been duplicated.");
        };
        if(User::where('email',$request->emailaddress)->first()) {
            throw new Exception("The email address has been registered already.");
        };
        $request->verification_token = create_guid();
        if($this->send_email($request)) {
            $user = User::create([
                'name' => $request->username,
                'email' => $request->emailaddress,
                'role_id' => 2,
                'verification_token' => $request->verification_token
            ]);
            return response()->json($user);
        } else {
            return response()->json(['result'=>'Please try again.']);
        }
    }
    public function addtenstreetcustomerconfig(Request $request)
    {
        if(Tenstreetconfig::where('queue_name',$request->queuename)->first()) {
            throw new Exception("The Queue Name has been registered already.");
        };
        if(Tenstreetconfig::where('origination_did',$request->origination_did)->first()) {
            throw new Exception("The Origination DID has been duplicated.");
        };
        
        $tc = Tenstreetconfig::create([
            'domain' => $request->domain,
            'queue_name' => $request->queuename,
            'origination_did' => $request->origination_did,
            'ani_did' => $request->ani_did,
            'status' => $request->status
        ]);
        return response()->json($tc);
    }
    public function updatetenstreetcustomerconfig(Request $request)
    {
        $tc = Tenstreetconfig::where('id',$request->u)->first();
        $tc = $tc->update([
            'domain' => $request->edit_domain,
            'queue_name' => $request->edit_queuename,
            'origination_did' => $request->edit_origination_did,
            'ani_did' => $request->edit_ani_did,
            'status' => $request->edit_status
        ]);
        return response()->json($tc);
    }
    public function getcustomerdomains(Request $request)
    {
        $uid = $request->uid;
        if($uid) {
            $domains = Customerdomains::where('user_id', $uid)->pluck('domain')->toArray();
            return response()->json($domains);
        } else {
            throw new Exception("Unknown error has been occurred.");
        }
    }
    public function gettenstreetcustomerconfig(Request $request)
    {
        $tcid = $request->tcid;
        if($tcid) {
            $tsconfig = Tenstreetconfig::where('id', $tcid)->first();
            return response()->json($tsconfig);
        } else {
            throw new Exception("Unknown error has been occurred.");
        }
    }
    public function updatedomains(Request $request)
    {
        $domains = explode(',', $request->domains);
        $uid = $request->u;

        if($uid) {
            Customerdomains::where('user_id', $uid)->delete();
            foreach($domains as $domain) {
                Customerdomains::create(['user_id'=>$uid, 'domain'=>$domain]);
            }
            return response()->json([]);
        } else {
            throw new Exception("Unknown error has been occurred.");
        }
    }
    public function resetpassword(Request $request)
    {
        $email = $request->u;
        //$currentpassword = $request->currentpassword;
        $newpassword = $request->newpassword;
        $confirmpassword = $request->confirmpassword;

        //if($newpassword && $confirmpassword && $currentpassword) {
        if($newpassword && $confirmpassword) {
            if($newpassword==$confirmpassword) {
                $user = User::where('email', $email)->first();
                //$user = User::where('email', $email)->where('password',bcrypt($currentpassword))->first();
                if($user) {
                    $encrypted = bcrypt($newpassword);
                    $user->password = $encrypted;
                    $user->salt = $encrypted;
                    $user->reset_token = $encrypted;
                    $user->api_token = 'APH562OB05pE9ooYqwBuDiZI8wIpui1Wz0PMLg9d27VSN2Go5YPKj0Jhmfs5';
                    $user->update();

                    return redirect('/logout');
                }
            }
        }
        throw new Exception("Unknown error has been occurred.");
    }
    public function sendnewpassword(Request $request)
    {
        $email = $request->emailaddress;
        $user = User::where('email', $email)->first();
        if($user) {
            $user->reset_token = bcrypt(generateRandomString(10));
            $user->update();
            if($this->email_resettingpasswordinstruction($user)) return true;
        }
        throw new Exception("Unknown error has been occurred.");
    }
    public function usersdata()
    {
        $data = User::orderBy('id','asc')->get();
        $result = array();
        $result['draw'] = 1;
        $result['recordsTotal'] = count($data);
        $result['recordsFiltered'] = count($data);
        $result['data'] = $data;
        return response()->json($result);
    }
    public function userloginhistory()
    {
        $data = Login::orderBy('id','asc')->get();
        $result = array();
        $result['draw'] = 1;
        $result['recordsTotal'] = count($data);
        $result['recordsFiltered'] = count($data);
        $result['data'] = $data;
        return response()->json($result);
    }
}