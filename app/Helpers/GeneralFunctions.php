<?php
	/**
	 * Created by PhpStorm.
	 * User: REDSignal
	 * Date: 3/22/2018
	 * Time: 3:49 PM.
	 */
	
	namespace App\Helpers;
	
	use App\Models\Appointments;
	use Illuminate\Support\Facades\Gate;
	
	class GeneralFunctions
	{
		public static function cleanNumber($phoneNumber)
		{
			$phoneNumber = str_replace(' ', '', $phoneNumber); // Replaces all spaces with hyphens.
			$phoneNumber = str_replace('-', '', $phoneNumber); // Replaces all spaces with hyphens.
			
			return self::cleanCountryCodes(preg_replace('/[^0-9\-]/', '', $phoneNumber)); // Removes special chars.
		}
		
		private static function cleanCountryCodes($phoneNumber)
		{
			//if($_SERVER['REMOTE_ADDR'] == '202.166.167.242'){dd($phoneNumber);}
			// Remove Zero Leading
			if ($phoneNumber[0] == '0') {
				return $phoneNumber = substr($phoneNumber, 1);
			}
			// Remove Coutnry
			if ($phoneNumber[0] == '9' && $phoneNumber[1] == '2') {
				return $phoneNumber = substr($phoneNumber, 2);
			}
			// Remove Zero Leading
			if ($phoneNumber[0] == '0') {
				return $phoneNumber = substr($phoneNumber, 1);
			}
			
			return $phoneNumber;
		}
		
		public static function prepareNumber($phoneNumber)
		{
			// Adjust Country Code for Pakistan
			if ($phoneNumber[0] == '3' && (strlen($phoneNumber) >= 9 && strlen($phoneNumber) <= 11)) {
				return '92'.$phoneNumber;
			} else {
				return $phoneNumber;
			}
		}
		
		public static function prepareNumber4Call($phoneNumber,$type = 0)
		{
			if (!Gate::allows('contact')) {
				return '***********';
			} else {
				// Adjust Country Code for Pakistan
				if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 0) {
					return '+92'.$phoneNumber;
				}elseif($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 1){
					return '0'.$phoneNumber;
				} else {
					return $phoneNumber;
				}
			}
		}
		public static function prepareNumber4CallSMS($phoneNumber)
		{
			// Adjust Country Code for Pakistan
			if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10) {
				return '+92'.$phoneNumber;
			} else {
				return $phoneNumber;
			}
		}
		
		/**
		 * @param $type in string form
		 *
		 * @return number numeric constant value
		 */
		public static function AppointmentType($type)
		{
			return $type == config('constants.appointment_type_consultancy_string') ? config('constants.appointment_type_consultancy') : config('constants.appointment_type_service');
		}
		
		public static function contactStatus($contact)
		{
			if (!Gate::allows('contact')) {
				return '***********';
			} else {
				return $contact;
			}
		}
		public static function patientSearch($id){
			if(is_numeric($id)){
				return $id;
			}else{
				if(strpos($id,"C-") == 0){
					$id=str_replace("C-","",$id);
					if(strpos($id,"c-") == 0){
						return str_replace("c-","",$id);
					}else{
						return $id;
					}
				}else{
					return $id;
				}
			}
		}
		public static function patientSearchStringAdd($id){
			if(is_numeric($id)){
				return 'C-'.$id;
			}else{
				return $id;
			}
		}
		
		public static function clearnString($string) {
			
			return str_replace([' ','-', '+'], '', $string);
		}
		
		public static function getAppointmentType($appointment_id) {
			$appointment = Appointments::select('appointment_type_id')->find($appointment_id);
			
			return $appointment->appointment_type_id ?? 0;
		}
		
	}
