<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppointmentSchedule;
use App\Models\Order;
use Auth;
class AppointmentScheduleController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:web');
    }

    public function index(){
        $schedules = AppointmentSchedule::all();

        return view('provider.appointment_schedule')->with([
            'schedules' => $schedules
        ]);
    }

    public function create(){
        $days = array(
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        );

        return view('provider.create_appointment_schedule')->with([
            'days' => $days
        ]);
    }


    public function store(Request $request){

        $auth_user = Auth::guard('web')->user();

        $rules = [
            'day'=>'required',
            'start_time'=>'required',
            'end_time'=>'required|after:start_time',
        ];
        $customMessages = [
            'day.required' => trans('user_validation.Day is required'),
            'start_time.required' => trans('user_validation.Start time is required'),
            'end_time.required' => trans('user_validation.End time is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $schedule = new AppointmentSchedule();
        $schedule->user_id = $auth_user->id;
        $schedule->day = $request->day;
        $schedule->start_time = $request->start_time;
        $schedule->end_time = $request->end_time;
        $schedule->status = $request->status;
        $schedule->save();

        $notification= trans('user_validation.Created Successfully');
        $notification=array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);

    }

    public function edit($id){
        $days = array(
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        );

        $schedule = $this->ownedSchedule($id);

        return view('provider.edit_appointment_schedule')->with([
            'days' => $days,
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, $id){

        $auth_user = Auth::guard('web')->user();

        $rules = [
            'day'=>'required',
            'start_time'=>'required',
            'end_time'=>'required|after:start_time',
        ];
        $customMessages = [
            'day.required' => trans('user_validation.Day is required'),
            'start_time.required' => trans('user_validation.Start time is required'),
            'end_time.required' => trans('user_validation.End time is required'),
        ];

        $this->validate($request, $rules, $customMessages);

        $schedule = $this->ownedSchedule($id);
        $schedule->user_id = $auth_user->id;
        $schedule->day = $request->day;
        $schedule->start_time = $request->start_time;
        $schedule->end_time = $request->end_time;
        $schedule->status = $request->status;
        $schedule->save();

        $notification= trans('user_validation.Updated Successfully');
        $notification=array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('provider.appointment-schedule.index')->with($notification);

    }

    public function destroy($id){
        $exist = Order::where('appointment_schedule_id', $id)->count();
        if($exist == 9){
            $schedule = $this->ownedSchedule($id);
            $schedule->delete();

            $notification= trans('user_validation.Deleted Successfully');
            $notification=array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->route('provider.appointment-schedule.index')->with($notification);
        }else{
            $notification= trans('user_validation.You can not delete this item, there are multiple booking exist under this schedule');
            $notification=array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->route('provider.appointment-schedule.index')->with($notification);
        }


    }

    /**
     * A schedule that belongs to the logged-in provider, or a 404.
     */
    private function ownedSchedule($id){
        return AppointmentSchedule::where(['id' => $id, 'user_id' => Auth::guard('web')->user()->id])->firstOrFail();
    }
}
