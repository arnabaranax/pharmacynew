<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DateTime;
use Exception;
use App\Models\User;
use App\Models\Token;
use App\Models\Schedule;
use App\Models\Institute;
use Illuminate\Support\Str;
use App\Models\StudentChoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use App\Models\Registerstudent;
use App\Models\StudentActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\PharmacyPhotoSign;
use App\Models\PaymentTransaction;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Models\PharmacyAppl_ElgbExam;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\StudentChoiceResource;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\StudentActivityResource;



class StudentController extends Controller
{
    public function studentInfoUpdate(Request $request)
    {
        $request->validate([
            'form_no' => ['required'],
        ]);

        try {
            $student = Registerstudent::where('s_appl_form_num', $request->form_no)->first();
            if (!$student) {
                return response()->json([
                    'error' => true,
                    'message' => 'Student record not found!'
                ], 404);
            }
            if ($request->is_updated) {
                $request->validate([
                    'first_name' => ['required'],
                    'middle_name' => ['nullable'],
                    'last_name' => ['required'],
                    'father_name' => ['required'],
                    'mother_name' => ['required'],
                    'dob' => ['required'],
                    'email' => ['required'],
                    'gender' => ['required'],
                    'address' => ['required'],
                    'ps' => ['required'],
                    'po' => ['required'],
                    'pin' => ['required'],
                    'is_married' => ['nullable'],
                    'is_kanyashree' => ['nullable'],
                    'is_pwd' => ['nullable'],
                    's_photo' => ['required'],
                    's_sign'  => ['required'],
                ]);
                $aadharNo = $student->s_aadhar_original;
                $photoPath = null;
                $signPath = null;
                if ($request->hasFile('s_photo')) {
                    $image = $request->file('s_photo');
                    $imageName = $request->form_no . '_image.' . $image->getClientOriginalExtension();
                    $photoPath = 'uploads/' . $imageName;
                    $image->storeAs('uploads/', $imageName, 'public');
                } else {
                    $photoPath = $student->s_photo;
                }
                // dd($photoPath);

                if ($request->hasFile('s_sign')) {
                    $signature = $request->file('s_sign');
                    $signatureName = $request->form_no . '_sign.' . $signature->getClientOriginalExtension();
                    $signPath = 'uploads/' . $signatureName;
                    $signature->storeAs('uploads/', $signatureName, 'public');
                } else {
                    $signPath = $student->s_sign;
                }
                // $photoSign = PharmacyPhotoSign::where('student_aadhar_no', $aadharNo)->first();
                // if ($photoSign) {
                //     $photoSign->update([
                //         'student_photo' => $photoPath ?? $photoSign->student_photo,
                //         'student_signature' => $signPath ?? $photoSign->student_signature,
                //     ]);
                // } else {
                //     PharmacyPhotoSign::create([
                //         'student_aadhar_no' => $aadharNo,
                //         'student_photo' => $photoPath,
                //         'student_signature' => $signPath,
                //     ]);
                // }


                $student->update([
                    // dd($request->all()),
                    's_first_name' => $request->first_name,
                    's_middle_name' => $request->middle_name,
                    's_last_name' => $request->last_name,
                    's_candidate_name' => Str::replace('  ', ' ', "{$request->first_name} {$request->middle_name} {$request->last_name}"),
                    's_father_name' => $request->father_name,
                    's_mother_name' => $request->mother_name,
                    's_dob' => $request->dob,
                    's_email' => $request->email,
                    's_gender' => $request->gender,
                    'address' => $request->address,
                    'ps' => $request->ps,
                    'po' => $request->po,
                    'pin' => $request->pin,
                    // 'is_married' => $request->is_married,
                    // 'is_kanyashree' => $request->is_kanyashree,
                    // 's_pwd' => $request->is_pwd,
                    'is_married' => $request->is_married ? 1 : 0,
                    'is_kanyashree' => $request->is_kanyashree ? 1 : 0,
                    's_pwd' => $request->is_pwd ? 1 : 0,
                    'is_profile_updated' => true,
                    's_photo' => $photoPath,
                    's_sign' => $signPath,
                ]);

                auditTrail($student->s_id, "{$student->s_candidate_name} updated details");
                studentActivite($student->s_id, "{$student->s_candidate_name} updated details");
            } else {
                $student->update([
                    'is_profile_updated' => true
                ]);

                auditTrail($student->s_id, "{$student->s_candidate_name} confirmed details");
                studentActivite($student->s_id, "{$student->s_candidate_name} confirmed details");
            }

            $student = Registerstudent::where('s_appl_form_num', $request->form_no)->first();
            // $photoSign = PharmacyPhotoSign::where('student_aadhar_no', $aadharNo)->first();
            // dd($photoSign);

            $rank_data = [];
            $userRank = $student;

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

            foreach ($rankArr as $val) {
                $userRankData = (int)$userRank[$val];
                if (!is_null($userRankData) && ($userRankData != 0)) {
                    array_push($rank_data, [
                        'category' => casteValue(Str::upper(explode('_', $val)[1])),
                        'rank' => $userRankData
                    ]);
                }
            }

            $check_choice_fillup = config_schedule('CHOICE_FILLUP');
            $choice_sehedule = $check_choice_fillup['status'];

            $check_accept = config_schedule('ACCEPT');
            $allotment_schedule = $check_accept['status'];

            return response()->json([
                'error' => false,
                'message' => 'Updated Successfully',
                'profile_update'   => (bool)$student->is_profile_updated,
                'choice_sehedule' => ($student->is_profile_updated == 1) && $choice_sehedule,
                'allotment_schedule' => ($student->is_choice_fill_up == 1) && ($student->is_lock_manual == 1) && $allotment_schedule,
                'user' => [
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
                    's_photo' => URL::to("storage/{$student->s_photo}"),
                    's_sign' => URL::to("storage/{$student->s_sign}"),
                    // 's_photo' => ($photoSign && $photoSign->student_photo) ? URL::to("storage/{$photoSign->student_photo}") : "",
                    // 's_sign' => ($photoSign && $photoSign->student_signature) ? URL::to("storage/{$photoSign->student_signature}") : "",
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
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
    public function downloadReceipt($from_num)
    {
        try {
            $registerstudent = DB::table('final_pharmacy_register_student')
                ->where(['s_appl_form_num' => $from_num])
                ->leftJoin('institute_master', 'i_code', '=', 's_inst_code')
                ->select(
                    'final_pharmacy_register_student.*',
                    'institute_master.i_id',
                    'institute_master.i_name',
                    'institute_master.i_code',
                    // 'pharmacy_photo_signature.student_photo',
                    // 'pharmacy_photo_signature.student_signature'
                )
                ->first();
            // dd($registerstudent);
            $payment = PaymentTransaction::where('pmnt_stud_id', $registerstudent->s_id)
                ->where('pmnt_pay_type', 'APPLICATION')
                ->where('trans_status', 'SUCCESS')
                ->first();
            // dd($payment);

            $pdf = PDF::loadView('exports.applicationform', [
                'registerstudent' => $registerstudent,
                'payment' => $payment,
            ]);

            return $pdf->setPaper('a4', 'portrait')
                ->setOption(['defaultFont' => 'sans-serif',])
                ->stream('applicationform.pdf');
        } catch (Exception $e) {
            generateLaravelLog($e);
            return response()->json([
                'error' =>  true,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
