<?php

/*
Plugin Name:  Dntly 2 DPO
Plugin URI:   http://www.donately.com
Description:  API Integration with the Donately donation platform - copy Donately donations into Donor Perfect for CRM management functions
Version:      0.7.0
Author:       5ifty&5ifty
Author URI:   https://www.fiftyandfifty.org/
Contributors: shanaver

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
Neither the name of Alex Moss or pleer nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

define('DNTLYDPO_VERSION', '0.7.0');
define('DNTLYPO_OVERLAP_MAX', 5);

define('DNTLYDPO_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('DNTLYDPO_PLUGIN_PATH', __DIR__ );
define('DNTLYDPO_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once( DNTLY_PLUGIN_PATH . '/lib/dntly.class.php');
require_once( DNTLYDPO_PLUGIN_PATH . '/lib/dntly_to_dpo.class.php');

// admin styles & scripts
function dntlydpo_admin_scripts_styles(){
	wp_register_script( 'dntlydpo-scripts', DNTLYDPO_PLUGIN_URL . '/lib/dntlydpo.js' );
	wp_register_style( 'dntly-style', DNTLY_PLUGIN_URL . 'lib/dntlydpo.css' );
	
	wp_enqueue_script( 'dntlydpo-scripts' );
	wp_enqueue_style( 'dntly-style' );
}
add_action('admin_init', 'dntlydpo_admin_scripts_styles');

function dntlydpo_add_menu_page(){
	function dntlydpo_menu_page(){
		$options_page_url = __DIR__ . '/dntly_dpo-options.php';
		if(file_exists($options_page_url))
		include_once($options_page_url);
	};
	add_submenu_page( 'options-general.php', 'Donately 2 DPO', 'Donately 2 DPO', 'switch_themes', 'dntly-to-dpo', 'dntlydpo_menu_page' );	
};
add_action( 'admin_menu', 'dntlydpo_add_menu_page' );


// Add settings link on plugin page
function dntlydpo_plugin_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=dntly-to-dpo">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
add_filter("plugin_action_links_" . DNTLYDPO_PLUGIN_BASENAME, 'dntlydpo_plugin_settings_link' );

// basic obfuscating of password
function encode_password($sData){
	$sBase64 = base64_encode($sData);
	return "^^" . substr(strtr($sBase64, '+/', '-_'), 0);
}
function decode_password($sData){
	if( substr($sData, 0, 2) == "^^" ){
		$sBase64 = strtr(substr($sData, 2), '-_', '+/');
		$password = base64_decode($sBase64.'==');
	}
	else{
		$password = $sData;
	}
	return $password;
}

function dntlydpo_activate(){
	dntly_transaction_logging("Donately 2 DPO Plugin - Activated");
}
register_activation_hook(__FILE__,'dntlydpo_activate');

function dntlydpo_deactivate(){
	dntly_transaction_logging('Donately 2 DPO Plugin - *Deactivated*');
	dntlydpo_deactivate_cron_syncing();
}
register_deactivation_hook(__FILE__,'dntlydpo_deactivate');



/* 
	Cron Functions
*/

// function for syncing everything
function dntlydpo_sync_everything() {
	dntlydpo_sync_donations_schedule();
}
add_action('dntlydpo_donation_syncing_cron', 'dntlydpo_sync_everything');

// function for adding the syncing everything cron
function dntlydpo_activate_cron_syncing() {
	if( !wp_get_schedule('dntlydpo_donation_syncing_cron') ){
		dntly_transaction_logging('Donately 2 DPO Plugin - start hourly scheduler');
		wp_schedule_event(time(), 'hourly', 'dntlydpo_donation_syncing_cron');
	}
}

// function for removing the syncing everything cron
function dntlydpo_deactivate_cron_syncing() {
	if( wp_get_schedule('dntlydpo_donation_syncing_cron') ){
		dntly_transaction_logging('Donately 2 DPO Plugin - stop hourly scheduler');
		wp_clear_scheduled_hook('dntlydpo_donation_syncing_cron');
	}
}


/* 
		Donor Perfect (DPO) integration functions
*/

function dntlydpo_sync_donations_schedule(){
	$dntly_dpo = new DNTLY_TO_DPO;
	$dntly_dpo->process_donately_donations(10);
}


function dntlydpo_sync_donations(){
	$dntly_dpo = new DNTLY_TO_DPO;
	$dntly_dpo->process_donately_donations(2);
}
add_action( 'wp_ajax_dntlydpo_sync_donations', 'dntlydpo_sync_donations' );

