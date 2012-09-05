<?php

/*

Description:  DPO API integration class
Version:      0.1.0
Author:       5ifty&5ifty - A humanitarian focused creative agency
Author URI:   http://www.fiftyandfifty.org/
Contributors: bryan shanaver

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
Neither the name of Alex Moss or pleer nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


*/

class DNTLY_TO_DPO extends DNTLY_API {
		
	var $dpo_endpoint = "https://www.donorperfect.net/prod/xmlrequest.asp";
	var $dpo_options;
	var $origintype = "DONATELY";	
		
	function __construct() {
		parent::__construct();
		$this->dpo_options = get_option('dpo_options');
	}
		
	function create_response_object(){
		$response_object = new stdClass();
		$response_object->success = false;
		return $response_object;
	}
		
	function make_dpo_api_request($params){
		
		$url = $this->dpo_endpoint . "?" .str_replace("''", 'null', $params) . "&login=". $this->dpo_options['user'] . "&pass=" . $this->dpo_options['password'];
		print " url= " . $url . "\n\n";
		
		$response = simplexml_load_file($url);
		$response_object = $this->create_response_object();
		
		if($response->error){
			$response_object->error = true;
			$response_object->message = $response->error;
		}
		elseif( is_object($response) ){
			$response_object->success = true;
			$response_object->dpo_response = $response;
		}
		else{
			$response_object->error = true;
			$response_object->message = "Empty result set";
		}
		return $response_object;
	}
	
	function save_user_defined_field($id, $udf_field_name, $udf_field_values){
		$user_defined_field_array = array(
			$matching_id 	= $id, //Specify either a donor_id value if updating a donor record, a gift_id value if updating a gift record or an other_id value if updating a dpotherinfo table value (see dp_saveotherinfo)
			$field_name 	= "'" . $udf_field_name . "'",
			$data_type 		= "'" . $udf_field_values['data_type'] . "'", //C- Character, D-Date, N- Numeric
			$char_value 	= "'" . $udf_field_values['char_value'] . "'", //Null if not a Character field
			$date_value 	= "'" . $udf_field_values['date_value'] . "'", //Null if not a Date field
			$number_value = "'" . $udf_field_values['number_value'] . "'", //Null if not a Number field
			$user_id 			= "'" . $this->dpo_login . "'"
		);
		$params = "action=dp_save_udf_xml&params=" . implode($user_defined_field_array, ',');
		
		$response_object = $this->make_dpo_api_request($params);
		if($response_object->success){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					// this returns the donor_id, not the unique insert id - unfortunately
					$response_object->data = (int)$att;
				}
			}	
		}
		return $response_object;
	}	
	
	function find_donor_in_dpo($donor){
		
		$params_general  = "action=SELECT TOP 1 dp.donor_id, dpudf.dntly_id FROM dp, dpudf WHERE dp.donor_id=dpudf.donor_id ";
		
		$params_search1  = "AND dpudf.dntly_id='{$donor->id}'";
		$params = $params_general . $params_search1;
		$response_object = $this->make_dpo_api_request($params_general . $params_search1);
		if($response_object->success && $response_object->dpo_response->record){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$response_object->data = (int)$att;
					$response_object->match_type = 'dntly_id';
				}
			}	
		}
		else{
			$params_search2  = "AND dp.first_name='{$donor->first_name}' AND dp.last_name='{$donor->last_name}' AND dp.email='{$donor->email}' ";
			$response_object = $this->make_dpo_api_request($params_general . $params_search2);
			if($response_object->success && $response_object->dpo_response->record){
				foreach($response_object->dpo_response->record->field->attributes() as $att){
					if( (int)$att > 1 ){
						$response_object->data = (int)$att;
						$response_object->match_type = 'first, last, email';
						$dntly_id = $this->save_user_defined_field((int)$att, 'dntly_id', array('data_type' => 'C', 'char_value' => $donor->id));
						$response_object->attach_dntly_id_result = $dntly_id;
					}
				}	
			}
			else{
				$params_search3  = "AND dp.first_name='{$donor->first_name}' AND dp.last_name='{$donor->last_name}' AND dp.zip='{$donor->zip_code}' AND dp.country='{$donor->country}' ";
				$response_object = $this->make_dpo_api_request($params_general . $params_search3);
				if($response_object->success && $response_object->dpo_response->record){
					foreach($response_object->dpo_response->record->field->attributes() as $att){
						if( (int)$att > 1 ){
							$response_object->data = (int)$att;
							$response_object->match_type = 'first, last, zip, country';
							$dntly_id = $this->save_user_defined_field((int)$att, 'dntly_id', array('data_type' => 'C', 'char_value' => $donor->id));
							$response_object->attach_dntly_id_result = $dntly_id;
						}
					}	
				}
			}
		}
		
		// TODO: if record was found, update it in DPO
		
		return $response_object;
		
	}
	
	function save_donor($donation){
		
		$save_donor_params_array = array(
			$donor_id = 0, //Enter 0 (zero) to create a new donor/constituent record or an existing donor_id
			$first_name = "'" . $donation->first_name . "'",
			$last_name = "'" . $donation->last_name . "'",
			$middle_name = "''",
			$suffix = "''",
			$title = "''",
			$salutation = "''",
			$prof_title = "''",
			$opt_line = "''",
			$address = "'" . $donation->street_address . "'",
			$address2 = "'" . $donation->street_address2 . "'",
			$city = "'" . $donation->city . "'",
			$state = "'" . $donation->state . "'",
			$zip = "'" . $donation->zip_code . "'",
			$country = "'" . $donation->country . "'",
			$address_type = "''",
			$home_phone = "'" . $donation->phone_number . "'",
			$business_phone = "''",
			$fax_phone = "''",
			$mobile_phone = "''",			
			$email = "'" . $donation->email . "'",
			$org_rec = "''",
			$donor_type = "''",
			$nomail = "'N'",
			$nomail_reason = "''",
			$narrative = "''",
			$user_id = "'".$this->dpo_login."'"
		);
		$params = "action=dp_savedonor&params=" . implode($save_donor_params_array, ',');
		
		$response_object = $this->make_dpo_api_request($params);
		if($response_object->success){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$donor_perfect_donor_id = (int)$att;
				}
			}	
		}
		else{
			return $response_object;
		}

		$origin_type = $this->save_user_defined_field($donor_perfect_donor_id, 'origintype', array('data_type' => 'C', 'char_value' => $this->origintype));
		$dntly_id = $this->save_user_defined_field($donor_perfect_donor_id, 'dntly_id', array('data_type' => 'C', 'char_value' => $donation->person_id));
				
		$response_object->data = array(
			'dpo_donor_id' => $donor_perfect_donor_id,
			'dpo_origin_type_donor_id' => $origin_type->data,
			'dpo_dntly_id_donor_id' => $dntly_id->data,
		);

		return $response_object;
	}
	
	function update_donor_in_dpo($dpo_donor_id, $old_donor_fields, $new_donor_fields){
			
		// TODO: figure out if we need to touch all fields when we only want to update a few - ANSWER: UPDATE SQL
			
		if( !strstr( $old_donor_fields->zip, $new_donor_fields->zip )){
			$usezip = $new_donor_fields->zip_code;
		}else{
			$usezip = $old_donor_fields->zip_code;
		}
			
		$params  = "action=UPDATE dp SET xxxx='xxxx' WHERE donor_id='{$dpo_donor_id}' ";
		$params .= "AND dpgiftudf.dntly_gift_id='{$donation->id}'";
		$response_object = $this->make_dpo_api_request($params);
		if($response_object->success && $response_object->dpo_response->record){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$response_object->data = (int)$att;
				}
			}	
		}
		return $response_object;
		
	}
	
	function find_donation_in_dpo($donation, $dpo_donor_id){
		$params  = "action=SELECT TOP 1 dpgift.gift_id, dpgiftudf.dntly_gift_id FROM dpgift, dpgiftudf WHERE dpgift.gift_id=dpgiftudf.gift_id ";
		$params .= "AND dpgiftudf.dntly_gift_id='{$donation->id}'"; //"AND dpgift.donor_id='{$dpo_donor_id}' AND dpgift.amount='{$donation->donation_amount}' ";
		$response_object = $this->make_dpo_api_request($params);
		if($response_object->success && $response_object->dpo_response->record){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$response_object->data = (int)$att;
				}
			}	
		}
		return $response_object;
	}
	
	function save_donation($dpo_donor_id, $donation){
				
		$tracking_codes = $this->split_tracking_codes($donation);
		$dpo_gift_type = $this->get_gift_type($donation);
		
		if($donation->recurring){
			$recurring = true;
			$dpo_record_type = 'P';
			// if there is no trans_id - this must be the parent recurring
			if($donation->transaction_id = ''){
				$dpo_gl_code = 'GEN';
				//"USWGSGxx" (where xx is CC for Credit Card and DD for ACH Bank Deduction)
				$dpo_solicit_code = 'USWGSG' + ($dpo_gift_type=='CC'?$dpo_gift_type:'');
			}
			else{
				$dpo_gl_code = $tracking_codes['subsource'];
				$dpo_solicit_code = $tracking_codes['source'];
			}
		}
		else{
			$recurring = false;
			$dpo_record_type = 'G';
			$dpo_gl_code = $tracking_codes['subsource'];
			$dpo_solicit_code = $tracking_codes['source'];
		}
		
		$save_donation_params_array = array(
			$gift_id 				= 0,
			$donor_id 				= $dpo_donor_id,
			$record_type 			= "'".$dpo_record_type."'",
			$gift_date 				= "'".$this->convert_date_for_dpo($donation->created_at)."'",  
			$amount 					= $donation->donation_amount,
			$gl_code 				= "'".$dpo_gl_code."'",
			$solicit_code 			= "'".$dpo_solicit_code."'",
			$sub_solicit_code 	= "'".$tracking_codes['utm_campaign']."'",
			$gift_type 				= "'".$dpo_gift_type."'",
			$split_gift 			= "'N'",
			$pledge_payment 		= "'N'",
			$reference 				= "'".$donation->transaction_id."'",
			$memory_honor 			= "'".($donation->on_behalf_of == ''?'':'H')."'",			
			$gfname 					= "''",
			$glname 					= "''",
			$fmv 						= "0",
			$batch_no 				= "0",
			$gift_narrative 		= "'".$donation->on_behalf_of."'",
			$ty_letter_no 			= "'".$this->get_thankyou_letter($donation)."'",			
			$glink 					= "''",
			$plink 					= "''",
			$nocalc 					= "'N'",
			$receipt 				= "'N'",
			$old_amount 			= "''",
			$user_id 				= "'".$this->dpo_login."'",			
			$campaign 				= "'".$tracking_codes['campaign']."'",
			$membership_type 		= "''",
			$membership_level 	= "''",
			$membership_enr_date = "''",
			$membership_exp_date = "''",
			$membership_link_ID 	= "''",			
			$address_id 			= "''"		
		);
		$params = "action=dp_savegift&params=" . implode($save_donation_params_array, ',');
				
		$response_object = $this->make_dpo_api_request($params);
		
		if($response_object->success){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$donor_perfect_donation_id = (int)$att;
				}
			}	
		}
		else{
			return $response_object;
		}
		
		$additional_params = "action=UPDATE dpgift SET currency='USD' WHERE gift_id='{$donor_perfect_donation_id}'";
		$additional_response_object = $this->make_dpo_api_request($additional_params);
		//print_r($additional_response_object);
		
		if($recurring){
			// if there is no trans_id - this must be the parent recurring
			if($donation->transaction_id = ''){
				$recurring_parent_params  = "action=UPDATE dpgift SET bill='{$donation->donation_amount}', frequency='M', start_date='{$this->convert_date_for_dpo($donation->created_at)}' ";
				$recurring_parent_params .= "WHERE gift_id='{$donor_perfect_donation_id}'"; 
				$recurring_response = $this->make_dpo_api_request($recurring_parent_params);
			}
			else{
				$parent_dpo_donation = $this->find_donation_in_dpo($donation->parent_id, $dpo_donor_id);
				if($parent_dpo_donation->success){
					$dpo_parent_id = $parent_dpo_donation->data;
				}else{
					return $response_object;
				}
				$recurring_child_params  = "action=UPDATE dpgift SET plink='{$dpo_parent_id}', bill='{$donation->donation_amount}', frequency='M', start_date='{$this->convert_date_for_dpo($donation->created_at)}' ";
				$recurring_child_params .= "WHERE gift_id='{$donor_perfect_donation_id}'"; 
				$recurring_response = $this->make_dpo_api_request($recurring_child_params);
			}
			$corp_code = $this->save_user_defined_field($donor_perfect_donation_id, 'corp_code', array('data_type' => 'C', 'char_value' => 'CC'));
			$country_code = $this->save_user_defined_field($donor_perfect_donation_id, 'country_code', array('data_type' => 'C', 'char_value' => $this->get_country_code($donation->country)));
			$gift_off_code = $this->save_user_defined_field($donor_perfect_donation_id, 'gift_off_code', array('data_type' => 'C', 'char_value' => 'US'));
			$eft = $this->save_user_defined_field($donor_perfect_donation_id, 'eft', array('data_type' => 'C', 'char_value' => 'N'));
			if( !$recurring_response->success ){
				print_r($recurring_response);
				return $recurring_response;
			}
		}
		
		$anon_gift = $this->save_user_defined_field($donor_perfect_donation_id, 'anongift', array('data_type' => 'C', 'char_value' => ($donation->anonymous?'Y':'N')));
		$origin_type = $this->save_user_defined_field($donor_perfect_donation_id, 'origintype', array('data_type' => 'C', 'char_value' => $this->origintype));
		$dntly_id = $this->save_user_defined_field($donor_perfect_donation_id, 'dntly_gift_id', array('data_type' => 'C', 'char_value' => $donation->id));
						
		$response_object->data = array(
			'dpo_donation_id' => $donor_perfect_donation_id,
			'dpo_addntl_donation_id' => $additional_response_object->data,
			'dpo_origin_type_donation_id' => $origin_type->data,
			'dpo_dntly_id_donation_id' => $dntly_id->data,
			'dpo_anon_gift_donation_id' => $anon_gift->data,
		);
		
		return $response_object;

	}
	
	

	
	function refund_donation(){
		
		//TODO: build this function
		
/*

If a gift record has been created in DPO and you cancel or void the transaction in Donately / Authorize.net then the procedure in DPO is as follows:

a) Create an identical record in DPO to the original gift record but multiply the following 4 fields by -1.
a. DPGIFT.AMOUNT
b. dpgiftudf.[AMOUNT_USD]
c. dpgiftudf.[AMOUNT_GBP]
d. dpgiftudf.[AMOUNT_EUR]
e. NOTE: b, c & d may be zero
b) Set the following values in the record:
a. dpgiftudf.[GIFT_NEGATED] = ‘Y’
b. dpgiftudf.[GIFT_ADJ_REASON] = ‘NEGATE_REFUND’; ‘NEGATE_DUP’; ‘NEGATE_AMOUNT’; ‘NEGATE_GL_CODE’; ‘NEGATE_BOUNCE’; ‘NEGATE_DONOR’ as appropriate
i. ‘NEGATE_REFUND’ – money was returned to the donor
ii. ‘NEGATE_DUP’ – duplicate gift recorded
iii. ‘NEGATE_AMOUNT’ – amount changed after transaction created in DPO
iv. NEGATE_GL_CODE’ – GL Code changed
v. ‘NEGATE_BOUNCE’ – transaction bounced after being first accepted
vi. ‘NEGATE_DONOR’ – gift applied to wrong donor account
c. dpgiftudf.[GIFT_ADJ_COMMENT] – use if you find a need, freeform text field

*/		
		
	}
	
	
	function cancel_recurring_parent_donation(){
		
		//TODO: build this function
		
/*

COMPLETION_CODE = see list below
2. COMPLETION_DATE = date

Completion codes:

Edit COMPLETION_CODE BCC Bad Credit Card Information
Edit COMPLETION_CODE CHANGEAMT Donor Changed Their Pledge Amount
Edit COMPLETION_CODE FINISH Finished Commitment
Edit COMPLETION_CODE LAPSED Lapsed – no receipts for at least 1 year
Edit COMPLETION_CODE MISTAKE Should Not Have Been Added
Edit COMPLETION_CODE NOFIRST First Gift Wasn’t Received After 90 Days
Edit COMPLETION_CODE NOMONEY Financially Unable To Continue

*/		
		
	}
	

	
	function get_thankyou_letter($donation){
/*		
	QUESTION: we dont have two types of on_behalf_of - so should we default to 'In Honor of'?
	QUESTION: how do we test for Shipmates, if it's attached to a fundraiser?		
		1)	Test Solicit_code to see if Crewmates; not required in 50&50
		2)	Test gift for $2,000 or greater, set TY_Letter_No = ‘MD’
		3)	Test for "in honor of", set TY_Letter_No = ‘HON’
		4)	Test for "in memory of", set TY_Letter_No = ‘MEM’
		5)	Test for Shipmates, set TY_Letter_No = ‘PSM’
		6)	Test for Gift Catalog; not required in 50&50
		7)	Test for Haiti Disaster Relief gifts; not required in 50&50
		8)	All else are Standard gifts, set TY_Letter_No = ‘ST’
*/		
		if( $donation->recurring & $donation->transaction_id == '' ){
			return 'STDEFT';
		}
		elseif( $donation->amount > 2000 ){
			return 'MD';
		}
		elseif( $donation->on_behalf_of != '' && stristr($donation->on_behalf_of, "In memory of" ) ){
			return 'MEM';
		}
		elseif( $donation->on_behalf_of != '' ){
			return 'HON';
		}
		elseif( $donation->fundraiser_id ){
			return 'PSM';
		}
		else{
			return 'ST';
		}
	}
	
	function split_tracking_codes($donation){
/*
		utm_source=Mercy_Ships
		utm_medium=email
		utm_campaign=email20120426
		source=USAE1204A
		subsource=GEN
*/		
		$tracking_codes = array();
		$split_tracking_codes = split('&', $donation->dump);
		foreach($split_tracking_codes as $code => $val){
			$tracking_codes[strtolower($code)] = $val;
		}
		return $tracking_codes;
	}	
	
	function get_country_code($country){
		$first_two = substr($country, 0, 2);
		
		switch ($country){
			case 'Canada':
				$code = 'CA';
				break;
			case 'United States':
			case 'USA':
				$code = 'US';
				break;
			default: 
				$code = strtoupper($country);
		}
		
		return $code;
	}
	
	function get_gift_type($donation){
		//QUESTION: how should we label check/cash donations, they are not ACH, right?
		//CC for Credit Card and ACHEFT for ACH Bank Deduction
		if($donation->donation_type = 'cc'){
			$dpo_gift_type = 'CC';
		}else{
			$dpo_gift_type = '';
		}		
		return $dpo_gift_type;
	}
	
	function convert_date_for_dpo($date){
		$split_date1 = split(' ', $date);
		$split_date2 = split('-', $split_date1[0]);
		return $split_date2[1] . "/" . $split_date2[2] . "/" . $split_date2[0];
	}
	
	function sync_donations($count=2, $offset=320){
		global $dntly_debugging;
		
		$timer = array();
		array_push($timer, array('*sync_donations* start' => date("H:i:s")));
		
		array_push($timer, array('start get_donations' => date("H:i:s")));
		$get_donations = $this->make_api_request("get_donations", true, array('count' => $count,'offset' => $offset));
		array_push($timer, array('finish get_donations' => date("H:i:s")));
				
		foreach($get_donations->donations as $d){
			$donation_may_exist = true;
			$dpo_donation = new stdClass();
			$dpo_donor_id = null;
			$created_message = null;
			$found_message = null;
			$error_message = null;
			$this_dntly_donation = "$" . $d->donation_amount . " " . ($d->recurring?'recurring':'one-time') . " donation by " . $d->email . " created at " . $d->created_at;

			array_push($timer, array('start find_donor_in_dpo' => date("H:i:s")));
			$dpo_donor = $this->find_donor_in_dpo($d);
			array_push($timer, array('finish find_donor_in_dpo' => date("H:i:s")));	
						
			if( $dpo_donor->success ){
				//update_donor_in_dpo() TODO: flesh this out
				$found_message .= " - Found dpo user! (matched: ".$dpo_donor->match_type.") id:" . $dpo_donor_id = $dpo_donor->data;
			}
			
			if( !is_numeric($dpo_donor_id) ){
				$donation_may_exist = false;
				array_push($timer, array('start save_donor' => date("H:i:s")));
				$dpo_donor = $this->save_donor($d);
				array_push($timer, array('finish save_donor' => date("H:i:s")));
				if($dpo_donor->success){
					$created_message .=  " - Created dpo user! " . $dpo_donor_id = $dpo_donor->data['dpo_donor_id'];
				}
				else{
					$error_message .= " - Error creating dpo user! \n-- " . $dpo_donor->message[0];;
				}					
			}
					
			if( !is_numeric($dpo_donor_id) ){
				dntly_transaction_logging("Error: " . $this_dntly_donation . $created_message. $error_message, 'error');
				continue;
			}	
			
			if($donation_may_exist){
				array_push($timer, array('start find_donation_in_dpo' => date("H:i:s")));
				$dpo_donation = $this->find_donation_in_dpo($d, $dpo_donor_id);
				array_push($timer, array('finish find_donation_in_dpo' => date("H:i:s")));
			}
				
			if( $dpo_donation->data ){
				$found_message .=  " - Found dpo donation! id:" . $dpo_donation = $dpo_donation->data;				
			}
			else{
				array_push($timer, array('start save_donation' => date("H:i:s")));
				$dpo_donation = $this->save_donation($dpo_donor_id, $d);
				array_push($timer, array('finish save_donation' => date("H:i:s")));
				if($dpo_donation->success){
					$created_message .=  " - Created dpo donation! id:" . $dpo_donation_id = $dpo_donation->data['dpo_donation_id'];
				}
				else{
					$error_message .= " - Error creating dpo donation! \n-- " . $dpo_donation->message[0];
				}
			}
			
			if( $error_message ){
				dntly_transaction_logging($this_dntly_donation . $error_message . $created_message . $found_message, 'error');
			}
			elseif( $created_message ){
				dntly_transaction_logging($this_dntly_donation . $created_message . $found_message, 'new_record');
			}
			else{
				dntly_transaction_logging($this_dntly_donation . $found_message, 'found_record');
			}

			array_push($timer, array('*sync_donations* end' => date("H:i:s")));
			
			if($dntly_debugging){
				dntly_transaction_logging(print_r($timer, true), 'debug');
			}
			
		}
		return 'Finished';		
		
	}
	
}


