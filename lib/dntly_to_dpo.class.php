<?php

/*

Description:  DPO API integration class
Author:       5ifty&5ifty - A humanitarian focused creative agency
Author URI:   http://www.fiftyandfifty.org/
Contributors: shanaver

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
Neither the name of Alex Moss or pleer nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

class DNTLY_TO_DPO extends DNTLY_API {
		
	var $dpo_endpoint              = "https://www.donorperfect.net/prod/xmlrequest.asp";
	var $dpo_options;
	var $dpo_login;
	var $xml_errors                = null;
	var $origintype                = "DONATELY";	
	var $active_state              = 'active';
	var $canceled_state            = 'canceled';
	var $unique_identifier_prefix  = 'sub_';
		
	function __construct() {
		parent::__construct();
		$this->dpo_options = get_option('dpo_options');
		$this->dpo_login = $this->dpo_options['user'];
	}
		
	function create_response_object(){
		$response_object = new stdClass();
		$response_object->success = false;
		return $response_object;
	}

	function verify_api_settings(){
		if( $this->api_subdomain == 'www'){
			dntly_transaction_logging('Exiting... No account selected on the Donately settings page', 'error');
			return false;
		}
		if( $this->dpo_options['user'] == '' || $this->dpo_options['password'] == '' ){
			dntly_transaction_logging('Exiting... No DPO user and/or password set', 'error');
			return false;
		}
		return true;
	}
		
	function make_dpo_api_request($params){
		
		$url = urlencode($this->dpo_endpoint . "?" .str_replace("''", 'null', $params)) . "&login=". $this->dpo_options['user'] . "&pass=" . $this->dpo_options['password'];

		if( isset($this->dpo_options['console_calls']) ){
			dntly_transaction_logging("\n" . "api url: " . $this->dpo_endpoint . "?" .str_replace("''", 'null', $params) . "&login=". $this->dpo_options['user'] . "&pass=" . $this->dpo_options['password'] . "\n", 'print_debug');
		}

		$response_object = $this->create_response_object();

		libxml_use_internal_errors(true);
		$response = simplexml_load_file($url);
		if ( libxml_get_last_error() ) {
			$this->xml_errors .= print_r(libxml_get_last_error(), true) . "<br />\n";
		}
		libxml_clear_errors();
		libxml_use_internal_errors(false);
	
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

	function save_donation_event_id($event_id, $all_events){
		$events_array = explode(',', $all_events);
		if( !in_array($event_id, $events_array) ){
			array_push($events_array, $event_id);
			asort($events_array);
			$all_events = implode(',', $events_array);
			update_option('dntly_synced_donation_events', $all_events);
		}
		return $all_events;
	}
	
	function save_user_defined_field($id, $udf_field_name, $udf_field_values){
		$user_defined_field_array = array(
			$matching_id 	= $id, //Specify either a donor_id value if updating a donor record, a gift_id value if updating a gift record or an other_id value if updating a dpotherinfo table value (see dp_saveotherinfo)
			$field_name 	= "'" . $udf_field_name . "'",
			$data_type 		= "'" . (isset($udf_field_values['data_type']) ? $udf_field_values['data_type'] : "") . "'", //C- Character, D-Date, N- Numeric
			$char_value 	= "'" . (isset($udf_field_values['char_value']) ? $udf_field_values['char_value'] : "") . "'", //Null if not a Character field
			$date_value 	= "'" . (isset($udf_field_values['date_value']) ? $udf_field_values['date_value'] : "") . "'", //Null if not a Date field
			$number_value = "'" . (isset($udf_field_values['number_value']) ? $udf_field_values['number_value'] : "") . "'", //Null if not a Number field
			$user_id 			= "'" . $this->dpo_login . "'"
		);
		$params = "action=dp_save_udf_xml&params=" . implode($user_defined_field_array, ',');
		
		$response_object = $this->make_dpo_api_request($params);
		if( isset($response_object->dpo_response->record->field) ){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					// this returns the donor_id, not the unique insert id - unfortunately
					$response_object->data = (int)$att;
				}
			}	
		}
		return $response_object;
	}	
	
	function find_donor_in_dpo($donation){
		$params_general  = "action=SELECT TOP 1 dp.donor_id, dpudf.dntly_id FROM dp, dpudf WHERE dp.donor_id=dpudf.donor_id ";
		$params_search1  = "AND dpudf.dntly_id='{$donation->person_id}'";
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
			$params_search2  = "AND dp.first_name='{$donation->first_name}' AND dp.last_name='{$donation->last_name}' AND dp.email='{$donation->email}' ";
			$response_object = $this->make_dpo_api_request($params_general . $params_search2);
			if($response_object->success && $response_object->dpo_response->record){
				foreach($response_object->dpo_response->record->field->attributes() as $att){
					if( (int)$att > 1 ){
						$response_object->data = (int)$att;
						$response_object->match_type = 'first, last, email';
						$dntly_id = $this->save_user_defined_field((int)$att, 'dntly_id', array('data_type' => 'C', 'char_value' => $donation->person_id));
						$response_object->attach_dntly_id_result = $dntly_id;
					}
				}	
			}
			else{
				$params_search3  = "AND dp.first_name='{$donation->first_name}' AND dp.last_name='{$donation->last_name}' AND dp.zip='{$donation->zip_code}' AND dp.country='".$this->get_country_code($donation->country)."' ";
				$response_object = $this->make_dpo_api_request($params_general . $params_search3);
				if($response_object->success && $response_object->dpo_response->record){
					foreach($response_object->dpo_response->record->field->attributes() as $att){
						if( (int)$att > 1 ){
							$response_object->data = (int)$att;
							$response_object->match_type = 'first, last, zip, country';
							$dntly_id = $this->save_user_defined_field((int)$att, 'dntly_id', array('data_type' => 'C', 'char_value' => $donation->person_id));
							$response_object->attach_dntly_id_result = $dntly_id;
						}
					}	
				}
				else{
					$response_object->success = false;
					$response_object->match_type = "not found";
				}
			}
		}
		return $response_object;
	}

	function find_donation_in_dpo($dntly_id, $transaction_id, $type='gift'){
		$params  = "action=SELECT TOP 1 dpgift.gift_id, dpgiftudf.dntly_gift_id FROM dpgift, dpgiftudf WHERE dpgift.gift_id=dpgiftudf.gift_id ";
		if( $type == 'pledge' ){
			$params .= "AND dpgift.record_type = 'P' AND dpgiftudf.dntly_gift_id='{$dntly_id}'";
		}
		else{
			$params .= "AND dpgift.record_type = 'G' AND (dpgiftudf.dntly_gift_id='{$dntly_id}' OR dpgift.reference='{$transaction_id}')";
		}
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
	
	function dp_savedonor($donation){

		$response_object = new stdClass();
		
		$save_donor_params_array = array(
			$donor_id        = 0, //Enter 0 (zero) to create a new donor/constituent record or an existing donor_id
			$first_name      = "'" . $this->clean_string($donation->first_name) . "'",
			$last_name       = "'" . $this->clean_string($donation->last_name) . "'",
			$middle_name     = "''",
			$suffix          = "''",
			$title           = "''",
			$salutation      = "'" . $this->clean_string($donation->first_name) . "'",
			$prof_title      = "''",
			$opt_line        = "''",
			$address         = "'" . $this->clean_string($donation->street_address, true) . "'",
			$address2        = "'" . $this->clean_string($donation->street_address_2, true) . "'",
			$city            = "'" . $this->clean_string($donation->city) . "'",
			$state           = "'" . $this->clean_string($donation->state) . "'",
			$zip             = "'" . $this->clean_string($donation->zip_code) . "'",
			$country         = "'" . $this->get_country_code($donation->country) . "'",
			$address_type    = "''",
			$home_phone      = "'" . $this->clean_string($donation->phone_number) . "'",
			$business_phone  = "''",
			$fax_phone       = "''",
			$mobile_phone    = "''",			
			$email           = "'" . $this->clean_string($donation->email) . "'",
			$org_rec         = "'" . $this->get_org_rec($donation) . "'",
			$donor_type      = "'" . $this->get_donor_type($donation) . "'",
			$nomail          = "'N'",
			$nomail_reason   = "''",
			$narrative       = "''",
			$user_id         = "'".$this->dpo_login."'"
		);
		$params = "action=dp_savedonor&params=" . implode($save_donor_params_array, ',');
		
		$response_object = $this->make_dpo_api_request($params);

		if($response_object->success){
			if($response_object->dpo_response->record){
				foreach($response_object->dpo_response->record->field->attributes() as $att){
					if( (int)$att > 1 ){
						$donor_perfect_donor_id = (int)$att;
					}
				}	
			}
			else{
				$response_object->success = false;
				$response_object->message = 'unknown error';
				return $response_object;
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

	function dp_savegift($dpo_donor_id, $donation){

		$response_object = new stdClass();
		
		$save_donation_params_array = array(
			$gift_id             = 0,
			$donor_id            = $dpo_donor_id,
			$record_type         = "'".$donation->record_type."'",
			$gift_date           = "'".$this->convert_date_for_dpo($donation->created_at)."'",  
			$amount              = $this->convert_amount_in_cents_to_amount($donation->amount_in_cents),
			$gl_code             = "'".$donation->gl_code."'",
			$solicit_code        = "'".$donation->solicit_code."'",
			$sub_solicit_code    = "'".$donation->sub_solicit_code."'",
			$gift_type           = "'".$donation->gift_type."'",
			$split_gift          = "'N'",
			$pledge_payment      = "'".($donation->pledge_payment ? $donation->pledge_payment : 'N')."'",
			$reference           = "'".$donation->transaction_id."'",
			$memory_honor        = "'".$this->parse_honorarium($donation, 'memory_honor')."'",			
			$gfname              = "'".$this->parse_honorarium($donation, 'gfname')."'",
			$glname              = "'".$this->parse_honorarium($donation, 'glname')."'",
			$fmv                 = "0",
			$batch_no            = "0",
			$gift_narrative      = "'".$this->clean_string($donation->on_behalf_of)."'",
			$ty_letter_no        = "'".$this->get_thankyou_letter($donation)."'",			
			$glink               = "''",
			$plink               = "'".$donation->plink."'",
			$nocalc              = "'N'",
			$receipt             = "'N'",
			$old_amount          = "''",
			$user_id             = "'".$this->dpo_login."'",			
			$campaign            = "'".$donation->campaign."'",
			$membership_type     = "''",
			$membership_level    = "''",
			$membership_enr_date = "''",
			$membership_exp_date = "''",
			$membership_link_ID  = "''",			
			$address_id          = "''"		
		);
		$params = "action=dp_savegift&params=" . implode($save_donation_params_array, ',');

		$response_object = $this->make_dpo_api_request($params);

		if($response_object->success){
			if($response_object->dpo_response->record){
				foreach($response_object->dpo_response->record->field->attributes() as $att){
					if( (int)$att > 1 ){
						$donor_perfect_donation_id = (int)$att;
					}
				}
				$response_object->data = array(
					'dpo_donation_id' => $donor_perfect_donation_id
				);	
			}
			else{
				$response_object->success = false;
				$response_object->message = 'unknown error';
			}
		}

		return $response_object;

	}
	
	function update_donor($dpo_donor_id, $donation){
		$params  = "action=UPDATE dp SET first_name='{$this->clean_string($donation->first_name)}', last_name='{$this->clean_string($donation->last_name)}', address='{$this->clean_string($donation->street_address, true)}', address2='{$this->clean_string($donation->street_address_2, true)}',  ";
		$params .= "city='{$this->clean_string($donation->city)}', state='{$this->clean_string($donation->state)}', zip='{$donation->zip_code}', country='{$this->get_country_code($donation->country)}', home_phone='{$donation->phone_number}' ";
		$params .= "WHERE donor_id='{$dpo_donor_id}'";
		$response_object = $this->make_dpo_api_request($params);
		if($response_object->success){
			if( isset($response_object->dpo_response->record->field) ){ 
				foreach($response_object->dpo_response->record->field->attributes() as $att){
					if( (int)$att > 1 ){
						$response_object->data = (int)$att;
					}
				}
			}	
			$origin_type_response = $this->save_user_defined_field($dpo_donor_id, 'origintype', array('data_type' => 'C', 'char_value' => $this->origintype));
			$dntly_id_response    = $this->save_user_defined_field($dpo_donor_id, 'dntly_id', array('data_type' => 'C', 'char_value' => $donation->person_id));
			$response_object->data = array(
				'dpo_donor_id'               => $dpo_donor_id,
				'origin_type_response'       => (isset($origin_type_response->data) ? $origin_type_response->data : ''),
				'dntly_id_response'          => (isset($dntly_id_response->data) ? $dntly_id_response->data : ''),
			);	
		}
		if( isset($this->dpo_options['log_responses']) ){
			dntly_transaction_logging('update_donor response_object: ' . print_r($response_object, true));
		}
		return $response_object;
	}

	function update_pledge($dpo_pledge_record_id, $pledge_donation, $additional_updates){

		$response_object = new stdClass();

		$additional_pledge_updates          = "action=UPDATE dpgift SET currency='USD' ";
		$additional_pledge_updates         .= $additional_updates;
		$additional_pledge_updates         .= "WHERE gift_id='".$dpo_pledge_record_id."'"; 
		$additional_pledge_updates_response = $this->make_dpo_api_request($additional_pledge_updates);

		$eft_response             = $this->save_user_defined_field($dpo_pledge_record_id, 'eft', array('data_type' => 'C', 'char_value' => 'N'));
		$pledge_code_response     = $this->save_user_defined_field($dpo_pledge_record_id, 'pledge_code', array('data_type' => 'C', 'char_value' => 'SHIPMATE'));
		$webrecurring_response    = $this->save_user_defined_field($dpo_pledge_record_id, 'webrecurring', array('data_type' => 'C', 'char_value' => 'N'));
		$pldgurl_response         = $this->save_user_defined_field($dpo_pledge_record_id, 'pldgurl', array('data_type' => 'C', 'char_value' => $this->build_url('root') . '/admin/app#/people/' . $donation->person_id));
		//$cc_exp_response          = $this->save_user_defined_field($dpo_pledge_record_id, 'cc_exp', array('data_type' => 'C', 'char_value' => $donation->cc_exp));

		$corp_code_response       = $this->save_user_defined_field($dpo_pledge_record_id, 'corp_code', array('data_type' => 'C', 'char_value' => 'CC'));
		$country_code_response    = $this->save_user_defined_field($dpo_pledge_record_id, 'country_code', array('data_type' => 'C', 'char_value' => $this->get_country_code($pledge_donation->country)));
		$gift_off_code_response   = $this->save_user_defined_field($dpo_pledge_record_id, 'gift_off_code', array('data_type' => 'C', 'char_value' => 'US'));

		$anon_gift_response       = $this->save_user_defined_field($dpo_pledge_record_id, 'anongift', array('data_type' => 'C', 'char_value' => ($pledge_donation->anonymous?'Y':'N')));
		$origin_type_response     = $this->save_user_defined_field($dpo_pledge_record_id, 'origintype', array('data_type' => 'C', 'char_value' => $this->origintype));
		$dntly_id_response        = $this->save_user_defined_field($dpo_pledge_record_id, 'dntly_gift_id', array('data_type' => 'C', 'char_value' => $pledge_donation->subscription_unique_identifier));

		$response_object->data = array(
			'dpo_pledge_record_id'               => $dpo_pledge_record_id,
			'additional_pledge_updates_response' => (isset($additional_pledge_updates_response->data) ? $additional_pledge_updates_response->data : ''),
			'eft_response'                       => (isset($eft_response->data) ? $eft_response->data : ''),
			'pledge_code_response'               => (isset($pledge_code_response->data) ? $pledge_code_response->data : ''),
			'webrecurring_response'              => (isset($webrecurring_response->data) ? $webrecurring_response->data : ''),
			'pldgurl_response'                   => (isset($pldgurl_response->data) ? $pldgurl_response->data : $pldgurl_response),
			//'cc_exp_response'                    => (isset($cc_exp_response->data) ? $cc_exp_response->data : $cc_exp_response),
			'corp_code_response'                 => (isset($corp_code_response->data) ? $corp_code_response->data : ''),
			'country_code_response'              => (isset($country_code_response->data) ? $country_code_response->data : ''),
			'gift_off_code_response'             => (isset($gift_off_code_response->data) ? $gift_off_code_response->data : ''),
			'anon_gift_response'                 => (isset($anon_gift_response->data) ? $anon_gift_response->data : ''),
			'origin_type_response'               => (isset($origin_type_response->data) ? $origin_type_response->data : ''),
			'dntly_id_response'                  => (isset($dntly_id_response->data) ? $dntly_id_response->data : ''),
		);		

		if( isset($this->dpo_options['log_responses']) ){
			dntly_transaction_logging('update_pledge response_object: ' . print_r($response_object->data, true));
		}

		return $response_object;

	}
	
	function update_gift($dpo_gift_record_id, $donation, $additional_updates=''){
		
		$response_object = new stdClass();

		$additional_dpgift_updates          = "action=UPDATE dpgift SET currency='USD' ";
		$additional_dpgift_updates         .= $additional_updates;
		$additional_dpgift_updates         .= "WHERE gift_id='".$dpo_gift_record_id."'"; 
		$additional_dpgift_updates_response = $this->make_dpo_api_request($additional_dpgift_updates);

		if( $donation->recurring ){
			$webrecurring_response    = $this->save_user_defined_field($dpo_gift_record_id, 'webrecurring', array('data_type' => 'C', 'char_value' => 'Y'));
		}
		if( $donation->fundraiser_id ){
			$dntly_fr_id_response       = $this->save_user_defined_field($dpo_gift_record_id, 'dntly_fr_id', array('data_type' => 'C', 'char_value' => $donation->fundraiser_id));	
		}

		$corp_code_response       = $this->save_user_defined_field($dpo_gift_record_id, 'corp_code', array('data_type' => 'C', 'char_value' => 'CC'));
		$country_code_response    = $this->save_user_defined_field($dpo_gift_record_id, 'country_code', array('data_type' => 'C', 'char_value' => $this->get_country_code($donation->country)));
		$gift_off_code_response   = $this->save_user_defined_field($dpo_gift_record_id, 'gift_off_code', array('data_type' => 'C', 'char_value' => 'US'));

		$anon_gift_response       = $this->save_user_defined_field($dpo_gift_record_id, 'anongift', array('data_type' => 'C', 'char_value' => ($donation->anonymous?'Y':'N')));
		$origin_type_response     = $this->save_user_defined_field($dpo_gift_record_id, 'origintype', array('data_type' => 'C', 'char_value' => $this->origintype));
		$dntly_id_response        = $this->save_user_defined_field($dpo_gift_record_id, 'dntly_gift_id', array('data_type' => 'C', 'char_value' => $donation->id));

		$gift_narrative_response  = $this->save_user_defined_field($dpo_gift_record_id, 'gift_narrative', array('data_type' => 'C', 'char_value' => $donation->dump));

		$response_object->data = array(
			'dpo_donor_id'                       => $dpo_gift_record_id,
			'additional_dpgift_updates_response' => (isset($additional_dpgift_updates_response->data) ? $additional_dpgift_updates_response->data : ''),
			'webrecurring_response'              => (isset($webrecurring_response->data) ? $webrecurring_response->data : ''),
			'dntly_fr_id_response'               => (isset($dntly_fr_id_response->data) ? $dntly_fr_id_response->data : ''),
			'corp_code_response'                 => (isset($corp_code_response->data) ? $corp_code_response->data : ''),
			'country_code_response'              => (isset($country_code_response->data) ? $country_code_response->data : ''),
			'gift_off_code_response'             => (isset($gift_off_code_response->data) ? $gift_off_code_response->data : ''),
			'anon_gift_response'                 => (isset($anon_gift_response->data) ? $anon_gift_response->data : ''),
			'origin_type_response'               => (isset($origin_type_response->data) ? $origin_type_response->data : ''),
			'dntly_id_response'                  => (isset($dntly_id_response->data) ? $dntly_id_response->data : ''),
			'gift_narrative_response'            => (isset($gift_narrative_response->data) ? $gift_narrative_response->data : ''),
		);

		if( isset($this->dpo_options['log_responses']) ){
			dntly_transaction_logging('update_gift response_object: ' . print_r($response_object->data, true));
		}

		return $response_object;

	}	


	function add_gift($dpo_donor_id, $donation){

		$dpo_gift_record_id           = null;
		$dpo_gift_create_reponse      = null;
		$update_gift_responses        = null;

		$dpo_pledge_record_id         = null;
		$dpo_pledge_create_reponse    = null;
		$update_pledge_responses      = null;

		$created_message              = null;
		$found_message                = null;
		$error_message                = null;

		$additional_dpgift_updates    = '';

		$dpo_gift_create_reponse      = new stdClass();
		$dpo_pledge_create_reponse    = new stdClass();

		$donation->gift_type          = $this->get_gift_type($donation);

		$donation->record_type        = 'G';
		$donation->pledge_payment     = 'N';

		$tracking_codes               = $this->split_tracking_codes($donation);
		$donation->gl_code            = (isset($tracking_codes['gl']) ? $tracking_codes['gl'] : '');
		$donation->solicit_code       = (isset($tracking_codes['solicit_code']) ? $tracking_codes['solicit_code'] : '');
		$donation->sub_solicit_code   = (isset($tracking_codes['sub_solicit_code']) ? $tracking_codes['sub_solicit_code'] : '');
		$donation->campaign           = (isset($tracking_codes['campaign']) ? $tracking_codes['campaign'] : 'Dntly:' . $donation->campaign_id);

		$donation->plink              = '';

		if( $donation->recurring ){

			if( $donation->subscription_unique_identifier ){
				$dpo_pledge_record = $this->find_donation_in_dpo($donation->subscription_unique_identifier, null, 'pledge');

				// if original pledge record does not exist
				if( !isset($dpo_pledge_record->data) ){
					
					$pledge_donation = clone $donation;

					$pledge_donation->record_type     = 'P';
					$pledge_donation->gl_code         = 'GEN'; 
					$pledge_donation->solicit_code    = 'USWGSG' . $this->get_gift_type($pledge_donation);	
					$pledge_donation->pledge_payment  = 'N';
					$pledge_donation->plink           = '';
					$pledge_donation->transaction_id  = '';

					// create original pledge record
					$dpo_pledge_create_reponse = $this->dp_savegift($dpo_donor_id, $pledge_donation);

					if( isset($dpo_pledge_create_reponse->data) ){
						$dpo_pledge_record_id = $dpo_pledge_create_reponse->data['dpo_donation_id'];
						$created_message .=  " - Created original dpo pledge! id:" . $dpo_pledge_record_id;
						// update associated tables
						$additional_dpgift_updates = ",bill='".$this->convert_amount_in_cents_to_amount($pledge_donation->amount_in_cents)."', frequency='M', start_date='{$this->convert_date_for_dpo($pledge_donation->created_at)}' ";
						$update_pledge_responses = $this->update_pledge($dpo_pledge_record_id, $pledge_donation, $additional_dpgift_updates);
					}
					else{
						dntly_transaction_logging("Error: creating original dpo pledge, DPO Response: \n" . print_r($dpo_pledge_create_reponse , true) , 'print_debug');
						$error_message .= " - Error creating original dpo pledge donation! \n-- " . $dpo_pledge_create_reponse->message;
					}
				}
				else{
					$dpo_pledge_record_id = $dpo_pledge_record->data['dpo_donation_id'];
					$found_message .=  " - Found original dpo pledge id:" . $dpo_pledge_record_id;
				}

			}
			else{
				dntly_transaction_logging("Error: missing subscription_unique_identifier on this recurring donation: " . print_r($donation , true) , 'print_debug');
				$error_message .= " - Error: missing subscription_unique_identifier on this recurring donation ";
			}
			$donation->pledge_payment  = 'Y';
			$donation->plink = $dpo_pledge_record_id;
			// only the first recurring gets the actual solicit codes... for some reason... and the rest get hardcoded to the pledge values here
			if( $donation->parent_id ){
				$donation->gl_code         = 'GEN';
				$donation->solicit_code    = 'USWGSG' . $this->get_gift_type($donation);
			}
			$additional_dpgift_updates .= ",bill='".$this->convert_amount_in_cents_to_amount($donation->amount_in_cents)."', frequency='M', start_date='{$this->convert_date_for_dpo($donation->created_at)}' ";
		}
		else{
			// nothing except default gift type stuff
		}

		// create gift record
		$dpo_gift_create_reponse = $this->dp_savegift($dpo_donor_id, $donation);

		if( isset($dpo_gift_create_reponse->data) ){
			$dpo_gift_record_id = $dpo_gift_create_reponse->data['dpo_donation_id'];
			$created_message .=  " - Created dpo donation! id:" . $dpo_gift_record_id;
			// update associated tables
			$update_gift_responses = $this->update_gift($dpo_gift_record_id, $donation, $additional_dpgift_updates);
		}
		else{
			dntly_transaction_logging("Error creating dpo donation, DPO Response: \n" . print_r($dpo_gift_create_reponse, true), 'print_debug');
			$error_message .= " - Error creating dpo donation! ";
		}
		// reupdate the original pledge - maybe fixes DPO linking issue
		if( isset($pledge_donation) ){
			// this did not work
			//$update_pledge_responses = $this->update_pledge($dpo_pledge_record_id, $pledge_donation, $additional_dpgift_updates);
		}
		$dpo_gift_create_reponse->data = array(
			'dpo_gift_record_id'        => $dpo_gift_record_id,
			'dpo_gift_create_reponse'   => $dpo_gift_create_reponse,
			'update_gift_responses'     => $update_gift_responses,

			'dpo_pledge_record_id'      => $dpo_pledge_record_id,
			'dpo_pledge_create_reponse' => $dpo_pledge_create_reponse,			
			'update_pledge_responses'   => $update_pledge_responses,

			'created_message'           => $created_message,
			'found_message'             => $found_message,
			'error_message'             => $error_message,
		);
		if( isset($this->dpo_options['log_responses']) ){
			dntly_transaction_logging('add_gift response_object: ' . print_r($dpo_gift_create_reponse->data, true));
		}
		return $dpo_gift_create_reponse;
	}



	function process_donately_donations($count=5, $offset=0){

		$timer = array();
		array_push($timer, array('*sync_donations* start' => date("H:i:s")));

		$exclude_ids = get_option('dntly_synced_donation_events');

		dntly_transaction_logging('Start Get New Donately Donations | limit:' . $count . ' | event type:' . 'donation.created');

		if ( !$this->verify_api_settings() ) {die();}

		if($exclude_ids == ''){$exclude_ids = '0';}

		array_push($timer, array('start get_donations' => date("H:i:s")));
		$get_donations = $this->make_api_request("get_events", true, array('count' => $count, 'offset' => $offset, 'type' => 'donation.created', 'exclude_ids' => $exclude_ids, 'order' => 'ASC' ));
		array_push($timer, array('finish get_donations' => date("H:i:s")));

		if( !count($get_donations->events) ){
			dntly_transaction_logging('No new donation.created events found  | ' . substr_count($exclude_ids, ',') . ' donations have previously been processed'); 
		}

		$donations_found = 0;

		foreach($get_donations->events as $event){
			$donation_may_exist = true;
			$dpo_donation = new stdClass();
			$dpo_donor_id = null;
			$created_message = null;
			$found_message = null;
			$error_message = null;

			$d = $event->data->object;
			$this_dntly_donation = "$" . $this->convert_amount_in_cents_to_amount($d->amount_in_cents) . " " . ($d->recurring?'recurring':'one-time') . " donation (event id #".$event->id.") by " . $d->email . " created at " . $d->created_at;

			array_push($timer, array('start find_donor_in_dpo' => date("H:i:s")));
			$dpo_donor = $this->find_donor_in_dpo($d);
			array_push($timer, array('finish find_donor_in_dpo' => date("H:i:s")));	

			if( $dpo_donor->success ){
				$found_message .= " - Found dpo user & updated them! (matched: ".$dpo_donor->match_type.") id:" . $dpo_donor_id = $dpo_donor->data;
				array_push($timer, array('start update_donor_in_dpo' => date("H:i:s")));
				$this->update_donor($dpo_donor_id, $d);
				array_push($timer, array('finish update_donor_in_dpo' => date("H:i:s")));
			}

			if( !is_numeric($dpo_donor_id) ){
				$donation_may_exist = false;
				array_push($timer, array('start save_donor' => date("H:i:s")));
				$dpo_donor = $this->dp_savedonor($d);
				array_push($timer, array('finish save_donor' => date("H:i:s")));
				if($dpo_donor->success){
					$created_message .=  " - Created dpo user! " . $dpo_donor_id = $dpo_donor->data['dpo_donor_id'];
				}
				else{
					if( isset($this->dpo_options['console_debugger']) ){
						dntly_transaction_logging("Error creating dpo user, DPO Response: \n" . print_r($dpo_donor, true), 'print_debug');
					}
					$error_message .= " - Error creating dpo user! \n-- " . $dpo_donor->message;
				}					
			}
					
			if( !is_numeric($dpo_donor_id) ){
				dntly_transaction_logging("Error: " . $this_dntly_donation . $created_message. $error_message, 'error');
				continue;
			}	

			if($donation_may_exist){
				array_push($timer, array('start find_donation_in_dpo' => date("H:i:s")));
				$dpo_donation = $this->find_donation_in_dpo($d->id, $d->transaction_id, 'gift');
				array_push($timer, array('finish find_donation_in_dpo' => date("H:i:s")));
			}
				
			if( isset($dpo_donation->data) ){
				
				$dpo_donation_id = $dpo_donation->data;
				array_push($timer, array('start update_donation_in_dpo' => date("H:i:s")));
				$update_gift_responses = $this->update_gift($dpo_donation_id, $d);
				array_push($timer, array('finish update_donation_in_dpo' => date("H:i:s")));
				$found_message .=  " - Found dpo donation & updated! id:" . $dpo_donation_id;		
				$donations_found++;		
			
			}
			else{
				
				array_push($timer, array('start save_donation' => date("H:i:s")));
				$add_gift_response = $this->add_gift($dpo_donor_id, $d);
				array_push($timer, array('finish save_donation' => date("H:i:s")));

				$error_message    .= $add_gift_response->data['error_message'];
				$created_message  .= $add_gift_response->data['created_message'];
				$found_message    .= $add_gift_response->data['found_message'];		

			}

			if( $error_message ){
				dntly_transaction_logging($this_dntly_donation . $error_message . $created_message . $found_message, 'error');
			}
			elseif( $created_message ){
				$exclude_ids = $this->save_donation_event_id($event->id, $exclude_ids);
				dntly_transaction_logging($this_dntly_donation . $created_message . $found_message, 'new_record');
			}
			else{
				$exclude_ids = $this->save_donation_event_id($event->id, $exclude_ids);
				dntly_transaction_logging($this_dntly_donation . $found_message, 'found_record');
			}

			if( $this->xml_errors ){
				dntly_transaction_logging($this->xml_errors, 'error');
			}

			if( $donations_found >= DNTLYPO_OVERLAP_MAX ){
				dntly_transaction_logging("Exited after ".DNTLYPO_OVERLAP_MAX." concurrent records were found in DPO");
				break;
			}
			
		}

		array_push($timer, array('start get_subscription_cancels' => date("H:i:s")));
		$get_recurring_cancels = $this->make_api_request("get_events", true, array('count' => $count, 'offset' => $offset, 'type' => 'donation.subscription_canceled', 'exclude_ids' => $exclude_ids, 'order' => 'ASC' ));
		array_push($timer, array('finish get_subscription_cancels' => date("H:i:s")));

		if( !count($get_recurring_cancels->events) ){
			dntly_transaction_logging('No new donation.subscription_canceled events found'); 
		}

		foreach($get_recurring_cancels->events as $event){
			$dpo_donation = new stdClass();
			$dpo_donor_id = null;
			$created_message = null;
			$found_message = null;
			$error_message = null;

			$d = $event->data->object;
			$d->updated_at = ($event->created) / 1000;
			$this_dntly_donation = "$" . $this->convert_amount_in_cents_to_amount($d->amount_in_cents) . " cancel recurring donation (event id #".$event->id.") by " . $d->email . " created at " . $d->created_at;

			array_push($timer, array('start find_donation_in_dpo' => date("H:i:s")));
			$dpo_pledge_record = $this->find_donation_in_dpo($d->subscription_unique_identifier, null, 'pledge');
			array_push($timer, array('finish find_donation_in_dpo' => date("H:i:s")));

			if( isset($dpo_pledge_record->data) ){
				$dpo_pledge_record_id = $dpo_pledge_record->data['dpo_donation_id'];
				array_push($timer, array('start cancel_recurring_parent_donation' => date("H:i:s")));
				$dpo_cancel_recurring_response = $this->cancel_recurring_parent_donation($dpo_pledge_record_id, $d);
				array_push($timer, array('finish cancel_recurring_parent_donation' => date("H:i:s")));
				$found_message .=  " - Found dpo pledge & cancelled";
				// TODO log error if there is one
			}else{
				$error_message .= " - Error finding original dpo pledge! \n-- ";
			}

			if( $error_message ){
				dntly_transaction_logging($this_dntly_donation . $error_message . $created_message . $found_message, 'error');
			}
			else{
				$exclude_ids = $this->save_donation_event_id($event->id, $exclude_ids);
				dntly_transaction_logging($this_dntly_donation . $found_message, 'found_record');
			}			

		}

		array_push($timer, array('*sync_donations* end' => date("H:i:s")));

		if( isset($this->dpo_options['console_timer']) ){
			dntly_transaction_logging(print_r($timer, true), 'print_debug');
		}

		die();
		
	}



	function refund_donation($donor_perfect_donation_id, $donation){
		
/* TODO: finish this - need donation.refunded event */

/*
If a gift record has been created in DPO and you cancel or void the transaction in Donately / Authorize.net then the procedure in DPO is as follows:

a) Create an identical record in DPO to the original gift record but multiply the following 4 fields by -1.
a. DPGIFT.AMOUNT, dpgiftudf.[AMOUNT_USD], dpgiftudf.[AMOUNT_GBP], dpgiftudf.[AMOUNT_EUR]
a. dpgiftudf.[GIFT_NEGATED] = ‘Y’
b. dpgiftudf.[GIFT_ADJ_REASON] = ‘NEGATE_REFUND’; ‘NEGATE_DUP’; ‘NEGATE_AMOUNT’; ‘NEGATE_GL_CODE’; ‘NEGATE_BOUNCE’; ‘NEGATE_DONOR’ as appropriate
*/		

		$refund_params  = "action=UPDATE dpgiftudf SET gift_negated='Y', gift_adj_reason='NEGATE_REFUND', amount_usd='".($this->convert_amount_in_cents_to_amount($donation->amount_in_cents) * -1)."' ";
		$refund_params .= "WHERE gift_id='{$donor_perfect_donation_id}'";
		$refund_response = $this->make_dpo_api_request($refund_params);
		if( isset($refund_response->dpo_response->record->field) ){
			foreach($refund_response->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$donor_perfect_donation_id = (int)$att;
				}
			}	
		}
		else{
			return $refund_response;
		}
		print_r($refund_response);
		
	}
	
	
	function cancel_recurring_parent_donation($donor_perfect_donation_id, $donation){
		/*
		COMPLETION_DATE = date
		COMPLETION_CODE BCC  CHANGEAMT  FINISH  LAPSED  MISTAKE  NOFIRST  NOMONEY 
		*/		
		$recurring_parent_params  = "action=UPDATE dpgiftudf SET completion_date= '".$this->convert_date_for_dpo($donation->updated_at)."', completion_code='FINISH' ";
		$recurring_parent_params .= "WHERE gift_id='{$donor_perfect_donation_id}'";  // AND end_date=''
		$recurring_response = $this->make_dpo_api_request($recurring_parent_params);
		if( isset($response_object->dpo_response->record->field) ){
			foreach($response_object->dpo_response->record->field->attributes() as $att){
				if( (int)$att > 1 ){
					$donor_perfect_donation_id = (int)$att;
				}
			}	
		}
		if( isset($this->dpo_options['log_responses']) ){
			dntly_transaction_logging('cancel_recurring_parent_donation response_object: ' . print_r($recurring_response, true));
		}
		return $recurring_response;
	}	

	function get_thankyou_letter($donation){
		/*		
			1)	Test Solicit_code to see if Crewmates; not required in 50&50
			2)	Test gift for $2,000 or greater, set TY_Letter_No = ‘MD’
			3)	Test for "in honor of", set TY_Letter_No = ‘HON’
			4)	Test for "in memory of", set TY_Letter_No = ‘MEM’
			5)	Test for Shipmates, set TY_Letter_No = ‘PSM’
			6)	Test for Gift Catalog; not required in 50&50
			7)	Test for Haiti Disaster Relief gifts; not required in 50&50
			8)	All else are Standard gifts, set TY_Letter_No = ‘ST’
		*/		
		if( $donation->recurring & $donation->parent_id == '' ){
			return 'SEFT';
		}
		elseif( $donation->amount_in_cents > 200000 ){
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
		/* utm_source=Mercy_Ships, utm_medium=email, utm_campaign=email20120426, source=USAE1204A, subsource=GEN */		
		$conversions = array(
			'gl'               => 'gl',
			'campaign'         => 'campaign',	
			'sol'              => 'solicit_code',
			'source'           => 'solicit_code',
			'solicit_code'     => 'solicit_code',
			'sub_sol'          => 'sub_solicit_code',
			'sub_solicit_code' => 'sub_solicit_code'
		);
		$tracking_codes = array();
		$split_tracking_codes = explode('&', urldecode($donation->dump));
		foreach($split_tracking_codes as $postvar){
			$code = explode('=', $postvar);
			if( isset($conversions[strtolower($code[0])]) ){
				$tracking_codes[$conversions[strtolower($code[0])]] = (isset($code[1]) ? $this->clean_string($code[1]) : '');
			}
			else{
				$tracking_codes[$this->clean_string($code[0])] = (isset($code[1]) ? $this->clean_string($code[1]) : '');
			}
		}
		return $tracking_codes;
	}	

	function parse_honorarium($donation, $field){
		$on_behalf_of_split = explode(' ', $donation->on_behalf_of, 2);
		switch ( $field ){
			case 'memory_honor':
				return ($donation->on_behalf_of == '' ? '' : 'H');
			case 'gfname':
				return (isset($on_behalf_of_split[0]) ? $on_behalf_of_split[0] : '');
			case 'glname':
				return (isset($on_behalf_of_split[1]) ? $on_behalf_of_split[1] : '');
		}
		return '';
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
				$code = strtoupper($this->clean_string($country));
		}
		
		return $code;
	}

	function get_org_rec($donation){
		// 's/be 'Y' if donor_type = 'O'
		if( $this->get_donor_type($donation) == 'O'){
			$org_rec = 'Y';
		}
		else{
			$org_rec = 'N';
		}		
		return $org_rec;
	}

	function get_donor_type($donation){
		// 'I' for individual, 'F' for family e.g. first name contains '&' or 'and', 'O' for organization (blank first name)
		if( $donation->first_name == '' ){
			$donor_type = 'O';
		}
		elseif( stristr($donation->first_name, " and " ) || stristr(urldecode($donation->first_name), "&" ) ){
			$donor_type = 'F';
		}
		else{
			$donor_type = 'I';
		}		
		return $donor_type;
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
	
	function convert_date_for_dpo($date=null){
		if( !$date ){
			$date = date("Y-m-d H:i:s"); 
		}
		$split_date1 = explode(' ', $date);
		$split_date2 = explode('-', $split_date1[0]);
		return $split_date2[1] . "/" . $split_date2[2] . "/" . $split_date2[0];
	}

	function clean_string($string, $remove_punctuation=false){
		$string = str_replace('"', "", $string);
		$string = str_replace("'", "", $string);
		if($remove_punctuation){
			$string = str_replace(".", " ", $string);
			$string = str_replace(",", " ", $string);
		}
		$string = ucwords($string);
		$string = str_replace("#", "Number ", $string);
		$string = htmlentities($string);
		return sanitize_text_field($string);
	}

	function convert_card_type($type){
		/* vs, mc, ax, ds */
		switch ( strtolower($type) ){
			case 'visa':
				$code = 'vs';
				break;
			case 'mastercard':
				$code = 'mc';
				break;
			case 'amex':
				$code = 'ax';
				break;
			case 'discover':
				$code = 'ds';
				break;
			default: 
				$code = strtolower($type);
		}
		
		return $code;
	}

	
}


