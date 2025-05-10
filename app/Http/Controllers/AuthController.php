<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\RegisterStudent;
use Exception;
use App\Models\Otp;
use App\Models\Token;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use App\Models\StudentChoice;

class AuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $validated = Validator::make($request->all(), [
            'user_phone' => ['required'],
            'aadhar_num' => ['required'],
            // 'login_type' => ['required']
        ]);

        if ($validated->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validated->errors()
            ]);
        }
        $login_phone = $request->user_phone;
        $login_aadhar = $request->aadhar_num;
        // $login_type = $request->login_type;

        $student = RegisterStudent::where([
            's_phone' => $login_phone,
            'is_active' => 1,
            // 'adm_type'=>$login_type
        ])->first();
        // dd($student);
        if (!$student) {
            return response()->json([
                'error' => true,
                'message' => 'No data Found'
            ], 200);
        }
        $otp_code = Config::get('app.env') === 'production' ? rand(1111, 9999) : 1234;
        $student_phone = $student->s_phone;
        $sms_message_user = "{$otp_code} is your One Time Password (OTP). Don't share this with anyone. - WBSCTE&VE&SD";

        $student_adhar = substr(decryptHEXFormat($student->s_aadhar_no, env('ENC_KEY')), -4);

        if ($student_adhar == $login_aadhar) {
            $otp_res = Otp::where('username', $student_phone)->first();
        } else {
            return response()->json([
                'error' => true,
                'message' => "Aadhar Number not matched"
            ], 200);
        }

        if ($otp_res) {
            $last_otp_date = substr(trim($otp_res->otp_created_on), 0, 10);

            if ($last_otp_date == $today) {
                $minutes = getTimeDiffInMinute($now, $otp_res->otp_created_on);

                if ($otp_res->otp_count < 9) {
                    if ($minutes > 2) {
                        send_sms($student_phone, $sms_message_user);

                        $otp_res->update([
                            'username' => $student_phone,
                            'otp' => $otp_code,
                            'otp_created_on' => $now,
                            'otp_count' => intval($otp_res->otp_count) + 1
                        ]);

                        $otp_send = true;
                    } else {
                        return response()->json([
                            'error' => true,
                            'message' => "Your previous OTP was generated in last 2 minutes"
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => "You exceed the OTP generation limit for today. Try again tomorrow."
                    ], 200);
                }
            } else {
                send_sms($student_phone, $sms_message_user);

                $otp_res->update([
                    'username' => $student_phone,
                    'otp' => $otp_code,
                    'otp_created_on' => $now,
                    'otp_count' => 1
                ]);

                $otp_send = true;
            }
        } else {
            send_sms($student_phone, $sms_message_user);

            Otp::updateOrCreate([
                'username' => $student_phone,
            ], [
                'otp' => $otp_code,
                'otp_created_on' => $now,
                'otp_count' => 1
            ]);

            $otp_send = true;
        }

        if ($otp_send) {
            $otp_exp_time  = date('Y-m-d H:i:s', strtotime('+120 seconds', strtotime($now)));

            return response()->json([
                'error' => false,
                'message' => 'Otp sent successfully',
                'otp_expire_time' => formatDate($otp_exp_time, 'Y-m-d H:i:s', 'M j, Y H:i:s'),
            ], 200);
        }
    }

    //Validate OTP during Login
    public function validateSecurityCode(Request $request)
    {
        $now = date('Y-m-d H:i:s');
        $validated = Validator::make($request->all(), [
            'user_phone' => ['required'],
            'security_code' => ['required'],
        ]);

        if ($validated->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validated->errors()
            ]);
        }

        $login_phone = $request->user_phone;
        $login_otp = $request->security_code;

        $otp = Otp::where([
            'username' => $login_phone,
            'otp' => $login_otp
        ])->first();

        if ($otp) {
            DB::beginTransaction();
            try {
                $student = RegisterStudent::where([
                    's_phone' => $login_phone,
                    'is_active' => '1'
                ])->first();

                if ($student) {
                    $rankArr = [
                        's_gen_rank',
                        's_sc_rank',
                        's_st_rank',
                        's_obca_rank',
                        's_obcb_rank',
                        's_pwd_rank',
                        's_tfw_rank',
                        's_ews_rank',
                        's_llq_rank',
                        's_exsm_rank'
                    ];

                    $rank_data = [];

                    foreach ($rankArr as $val) {
                        $userRankData = (int)$student[$val];

                        if (!is_null($userRankData) && ($userRankData != 0)) {
                            array_push($rank_data, [
                                'category' => casteValue(Str::upper(explode('_', $val)[1])),
                                'rank' => $userRankData
                            ]);
                        }
                    }

                    $token = md5($now . rand(10000000, 99999999));
                    $expiry = date("Y-m-d H:i:s", strtotime('+4 hours', strtotime($now)));

                    Token::updateOrCreate([
                        't_user_id' => $student->s_id,
                    ], [
                        't_token' => $token,
                        't_generated_on' => $now,
                        't_expired_on' => $expiry,
                    ]);
                    // $pharmacyPhotoSign = PharmacyPhotoSign::where('student_aadhar_no', $student->s_aadhar_original)->first();
                    // dd($pharmacyPhotoSign);
                    $s_photo = null;
                    $s_sign = null;

                    $user_data =  [
                        's_id' => $student->s_id,
                        's_uuid' => $student->s_uuid,
                        's_ref' => md5($student->s_id),
                        's_index_num' => $student->s_index_num,
                        's_appl_form_num' => $student->s_appl_form_num,
                        's_first_name' => $student->s_first_name,
                        's_middle_name' => $student->s_middle_name,
                        's_last_name' => $student->s_last_name,
                        's_full_name' => $student->s_candidate_name,
                        's_father_name' => $student->s_father_name,
                        's_mother_name' => $student->s_mother_name,
                        's_dob' => $student->s_dob,
                        's_aadhar_no' => $student->s_aadhar_no,
                        's_phone' => $student->s_phone,
                        's_email' => $student->s_email,
                        's_gender' => $student->s_gender,
                        's_religion' => $student->s_religion,
                        's_caste' => $student->s_caste,
                        's_tfw' => $student->s_tfw,
                        's_pwd' => $student->s_pwd,
                        's_llq' => $student->s_llq,
                        's_exsm' => $student->s_exsm,
                        's_alloted_category' => $student->s_alloted_category,
                        's_alloted_round' => $student->s_alloted_round,
                        's_choice_id' => $student->s_choice_id,
                        's_trade_code' => $student->s_trade_code,
                        's_inst_code' => $student->s_inst_code,
                        'is_alloted' => $student->is_alloted,
                        'is_choice_fill_up' => $student->is_choice_fill_up,
                        'is_payment' => $student->is_payment,
                        'is_upgrade' => $student->is_upgrade,
                        's_photo' => $s_photo,
                        's_sign'=>$s_sign,
                        // 's_photo' => $pharmacyPhotoSign ? URL::to("storage/{$pharmacyPhotoSign->student_photo}") : null,
                        // 's_sign' => $pharmacyPhotoSign ? URL::to("storage/{$pharmacyPhotoSign->student_signature}") : null,
                        's_home_district' => !is_null($student->s_home_district) ? $student->s_home_district : "",
                        's_schooling_district' => !is_null($student->s_schooling_district) ? $student->s_schooling_district : "",
                        's_state_id' => $student->s_state_id,
                        'is_active' => $student->is_active,
                        'is_lock_manual' => $student->is_lock_manual,
                        'is_lock_auto' => $student->is_lock_auto,
                        'created_at' => $student->created_at,
                        'updated_at' => $student->updated_at,
                        'manual_lock_at' => $student->manual_lock_at,
                        'auto_lock_at' => $student->auto_lock_at,
                        'rank' => $rank_data,
                        'address' => $student->address,
                        'ps' => $student->ps,
                        'po' => $student->po,
                        'pin' => $student->pin,
                        'is_married' => (bool)$student->is_married,
                        'is_kanyashree' => (bool)$student->is_kanyashree,
                        'role_id' => 2,
                        's_admited_status'=>(bool)$student->s_admited_status,
                        'is_registration_payment'=>(bool)$student->is_registration_payment,
                        'adm_type'=>$student->adm_type,

                    ];

                    $student_name = $student->s_candidate_name;

                    auditTrail($student->s_id, "{$student_name} has been logged in successfully");
                    studentActivite($student->s_id, "{$student_name} has been logged in successfully");

                    $otp->delete();

                    DB::commit();

                    $profile_updated = $choice_fillup_page = $payment_page = $allotement_page = $choice_preview_page = false;
                    $payment_done =  $upgrade_done = $admitted = $accept_allotement = $upgrade_payment_done = $reject =  $schedule_choice_fillup = $schedule_admission = $student_auto_reject = false;

                    $checkChoice = $student->is_lock_manual;
                    $checkChoiceAuto = $student->is_lock_auto;
                    $checkPayment = $student->is_payment;
                    $checkallotement = $student->is_alloted;
                    $checkPayment = $student->is_payment;
                    $checkallotement = $student->is_alloted;
                    $profile_updated = (bool)$student->is_profile_updated;
                    $checkUpgrade = $student->is_upgrade;
                    $checkUpgradePayment = $student->is_upgrade_payment;
                    $checkAdmitted = $student->s_admited_status;
                    $checkAllotementAccept = $student->is_allotment_accept;
                    $student_reject_remarks = $student->s_remarks;
                    $checkUpgrade = $student->is_upgrade;
                    $checkUpgradePayment = $student->is_upgrade_payment;
                    $checkAllotementAccept = $student->is_allotment_accept;
                    $checkStatusReject = $student->s_admited_status;
                    $student_reject_remarks = $student->s_remarks;

                    $check_choice_fillup = config_schedule('CHOICE_FILLUP');
                    $check_choice_status = $check_choice_fillup['status'];

                    $check_accept = config_schedule('ACCEPT');
                    $check_accept_status = $check_accept['status'];

                    $check_upgrade = config_schedule('UPGRADE');
                    $check_upgrade_status = $check_upgrade['status'];

                    $check_admission = config_schedule('ADMISSION');
                    $check_admission_status = $check_admission['status'];

                    $checkStudentAutoRejectRound = $student->s_auto_reject;

                    $got_first_choice = StudentChoice::where([
                        'ch_stu_id' => $student->s_id,
                        'ch_inst_code' => $student->s_inst_code,
                        'ch_pref_no' => 1,
                    ])->first();
                    // $photosign= PharmacyPhotoSign::where([
                    // 'student_aadhar_no'=>$student->s_aadhar_original])
                    // ->first();
                    // $check_photo_sign = $photosign ? true : false;

                    if ($check_choice_status && ($checkChoice == 0) &&  ($checkChoiceAuto == 0)) {
                        $choice_fillup_page = true;
                    }

                    if ((($checkChoice == 1) ||  ($checkChoiceAuto == 1)) && ($checkallotement == 0)) { //&& ($checkallotement == 0)
                        $choice_preview_page = true;
                    }

                    if ((($checkChoice == 1) || ($checkChoiceAuto == 1)) && ($checkPayment == 0)) {
                        $payment_page = true;
                    }

                    if ((($checkChoice == 1) || ($checkChoiceAuto == 1))) {
                        $allotement_page = true;
                    }

                    // if ((($checkChoice == 1) || ($checkChoiceAuto == 1)) && ($checkallotement == 1)) {
                    //     $allotement_page = true;
                    // }

                    if ($checkPayment == 1) {
                        $payment_done = true;
                    }

                    if ($checkUpgrade == 1) {
                        $upgrade_done = true;
                    }
                    if ($checkUpgradePayment == 1) {
                        $upgrade_payment_done = true;
                    }
                    if ($checkAdmitted == 1) {
                        $admitted = true;
                    }
                    if ($checkAllotementAccept == 1) {
                        $accept_allotement = true;
                    }
                    if ($checkStatusReject == 2) {
                        $reject = true;
                    }
                    if ($check_choice_status == true) {
                        $schedule_choice_fillup = true;
                    }
                    if ($check_admission_status == true) {
                        $schedule_admission = true;
                    }
                    if ($checkStudentAutoRejectRound == 1) {
                        $student_auto_reject = true;
                    }

                    return response()->json([
                        'error'             =>  false,
                        'token'             =>  $token,
                        'token_expired_on'  =>  $expiry,
                        'user'              =>  json_encode($user_data),
                        'redirect' => [
                            'profile_update'   => $profile_updated,
                            'choice_fillup_page'   => $choice_fillup_page,
                            'payment_page'   => $payment_page,
                            'choice_preview_page'   => $choice_preview_page,
                            'payment_done' => $payment_done,
                            'allotement_page'   => $allotement_page,
                            'upgrade_done' => $upgrade_done,
                            'upgrade_payment_done' => $upgrade_payment_done,
                            'student_admitted' => $admitted,
                            'student_allotment_accepted' => $accept_allotement,
                            'allotment_accepted' => $accept_allotement,
                            'student_reject_status' => $reject,
                            'student_reject_remarks' => $student_reject_remarks,
                            'schedule_choice_fillup' => $schedule_choice_fillup,
                            'schedule_acceptance' => $check_accept_status,
                            'schedule_upgradation' => $check_upgrade_status,
                            'schedule_admission' => $schedule_admission,
                            'student_auto_reject' => $student_auto_reject,
                            'registration_fees_paid' => (bool)$student->is_registration_payment,
                            'can_upgrade' => $student->is_alloted == 1 && is_null($got_first_choice) ? true : false,
                            'upgrade_enabled' => env('UPGRADE_ENABLED'),
                            'login_type' => $request->login_type ?: null,
                            'is_spot_payment' => (bool)$student->is_spot_payment,
                            'overall_status' => getOverallStatus($student->s_id),
                            // 'check_photo_sign' => $check_photo_sign
                        ]
                    ], 200);
                } else {
                    return response()->json([
                        'error'     =>  true,
                        'message'   =>  'OTP is wrong'
                    ], 200);
                }
            } catch (Exception $e) {
                DB::rollBack();
                generateLaravelLog($e);

                return response()->json([
                    'error' => true,
                    'code' => 'INT_00001',
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            return response()->json([
                'error'     =>  true,
                'message'   =>  'Either Phone number and/or security code does not match'
            ], 400);
        }
    }
}
