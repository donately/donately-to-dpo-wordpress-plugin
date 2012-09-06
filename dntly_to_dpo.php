<?php

/*
Plugin Name:  Dntly 2 DPO
Plugin URI:   http://www.donately.com
Description:  API Integration with the Donately donation platform - copy Donately donations into Donor Perfect for CRM management functions
Version:      0.1.0
Author:       5ifty&5ifty
Author URI:   https://www.fiftyandfifty.org/
Contributors: shanaver

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
Neither the name of Alex Moss or pleer nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

require_once( WP_PLUGIN_DIR . '/dntly/lib/dntly.class.php');
require_once('lib/dntly_to_dpo.class.php');

//define('DNTLYDPO_PLUGIN_URL', plugin_dir_url( __FILE__ ));
//define('DNTLYDPO_PLUGIN_BASENAME', plugin_basename(__FILE__));
// swap these out once we are not developing inside the project
define('DNTLYDPO_PLUGIN_URL', plugins_url() . '/dntly_to_dpo/');
define('DNTLYDPO_PLUGIN_BASENAME', 'dntly_to_dpo/dntly_to_dpo.php');

// admin styles & scripts
function dntlydpo_admin_scripts_styles(){
	wp_register_script( 'dntlydpo-scripts', DNTLYDPO_PLUGIN_URL . '/lib/dntlydpo.js' );
	wp_enqueue_script( 'dntlydpo-scripts' );
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
}
register_deactivation_hook(__FILE__,'dntlydpo_deactivate');

/* 

Donor Perfect (DPO) integration functions

*/


function dntly_sync_donations(){
	$dntly_dpo = new DNTLY_TO_DPO;
	$dntly_dpo->sync_donations();
}
add_action( 'wp_ajax_dntly_sync_donations', 'dntly_sync_donations' );

