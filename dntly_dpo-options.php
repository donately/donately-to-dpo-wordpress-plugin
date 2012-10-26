<?php

	include_once('dntly_to_dpo.php');

	$dntly_options        = get_option('dntly_options');
	$user                 = isset($dntly_options['user']) ? $dntly_options['user'] : null;
	$token                = isset($dntly_options['token']) ? $dntly_options['token'] : null;
	$account              = isset($dntly_options['account']) ? $dntly_options['account'] : null;

	$dpo_options_post     = isset($_POST['dpo_options']) ? $_POST['dpo_options'] : false;
	if($dpo_options_post){
		$dpo_options_post['password'] = decode_password($dpo_options_post['password']);
		update_option('dpo_options', $dpo_options_post);
		dntly_transaction_logging('Updated DPO API Settings: user=' . $dpo_options_post['user'] . ' & pass=' . ($dpo_options_post['password']?encode_password($dpo_options_post['password']):'MISSING!') . ' & syncing=' . $dpo_options_post['syncing']);
	}
	$dpo_options          = get_option('dpo_options');
	$dpo_user             = isset($dpo_options['user']) ? $dpo_options['user'] : null;
	$dpo_password         = isset($dpo_options['password']) ? $dpo_options['password'] : null;
	$dpo_syncing          = isset($dpo_options['syncing']) ? $dpo_options['syncing'] : 'manual';
	$dpo_console_timer    = isset($dpo_options['console_timer']) ? $dpo_options['console_timer'] : "0";
	$dpo_console_debugger = isset($dpo_options['console_debugger']) ? $dpo_options['console_debugger'] : "0";
	$dpo_console_calls    = isset($dpo_options['console_calls']) ? $dpo_options['console_calls'] : "0";
	$log_responses        = isset($dpo_options['log_responses']) ? $dpo_options['log_responses'] : "0";

	if($token && $dpo_syncing == 'cron'){
		dntlydpo_activate_cron_syncing();
	}
	else{
		dntlydpo_deactivate_cron_syncing();
	}

?>

<div class="wrap">
	<div class="icon32" id="icon-profile"><br></div><h2>Donately to DPO Sync</h2>
	<div id="dntly-options-form">
	
		<?php if(!is_plugin_active('dntly/dntly.php')): ?>
			<div class="updated" id="message"><p><strong>Alert!</strong> You must install & activate the Donately Plugin for Auto-syncing with DPO to work!</p></div>
		<?php endif; ?>	
	
		<?php if(!$token): ?>	
			<div class="updated" id="message"><p><strong>Alert!</strong> You must have an Auth Token from Donately for Auto-syncing with DPO to work!</p><p><a href="/wp-admin/options-general.php?page=dntly">Enter your Auth token here</a></p></div>
		<?php else: ?>	

			<?php if($dntly_options['environment'] != 'production'): ?>	
				<div class="updated" id="message"><p><strong>Note:</strong> Donately is not in Production mode, it's in <?php print ucwords($dntly_options['environment']); ?></p></div>
			<?php endif; ?>	
						
		<form action="" id="dpo-form" method="post">
			<table class="dntly-table">
				<tbody>
				<tr>
					<th><label for="category_base">Donor Perfect Admin User</label></th>
					<td class="col1"></td>
					<td class="col2">
						<?php if($dpo_user & $dpo_password): ?>
							<input type="text" class="regular-text code disabled" value="<?php echo $dpo_user; ?>" disabled="disabled">
							<input type="hidden" value="<?php echo $dpo_user; ?>" id="dpo-user-name" name="dpo_options[user]">
						<?php else: ?>
							<input type="text" class="regular-text code" value="<?php echo $dpo_user; ?>" id="dpo-user-name" name="dpo_options[user]">
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="tag_base">Donor Perfect Admin Password</label></th>
					<td class="col1"></td>
					<td class="col2">
						<?php if($dpo_user & $dpo_password): ?>						
							<input type="text" class="regular-text code disabled" value="***" disabled="disabled">
							<input type="hidden" value="<?php echo encode_password($dpo_password); ?>" id="dpo-user-password" name="dpo_options[password]">
						<?php else: ?>
							<input type="text" class="regular-text code" id="dpo-user-password" name="dpo_options[password]">
						<?php endif; ?>
					<td>
				</tr>
				<tr>
					<th>&nbsp;</th>
					<td class="col1"></td>
					<td class="col2">
					<?php if($dpo_user & $dpo_password): ?>
						<input type="button" value="Change DPO User/Pass" id="dpo-reset-user" class="button-secondary" />
					<?php else: ?>
						<input type="submit" value="Set DPO User/Pass" id="dpo-get-user" class="button-secondary" />
					<?php endif; ?>
					</td>
				</tr>	
				<tr>
					<th><label for="category_base">Scheduling</label></th>
					<td class="col1"></td>
					<td class="col2">
						<select name="dpo_options[syncing]" id="dntly-account">
							<option value="manual" <?php selected( $dpo_syncing, 'manual' ); ?>>Manual Syncing</option>    
							<option value="cron" <?php selected( $dpo_syncing, 'cron' ); ?>>Automated Syncing (60 mins)</option>
						</select> <br />
					</td>
				</tr>
				<tr>
					<th><label for="category_base">Options</label></th>
					<td class="col1"></td>
					<td class="col2">
						<input type=checkbox name="dpo_options[console_timer]"  value="1" <?php checked( "1", $dpo_console_timer); ?>> timestamps to console (debug)<br />
						<input type=checkbox name="dpo_options[console_debugger]"  value="1" <?php checked( "1", $dpo_console_debugger); ?>> errors to console (debug)<br />
						<input type=checkbox name="dpo_options[console_calls]"  value="1" <?php checked( "1", $dpo_console_calls); ?>> API calls to console (debug)<br />
						<input type=checkbox name="dpo_options[log_responses]"  value="1" <?php checked( "1", $log_responses); ?>> detailed DPO responses to log (debug)<br />
					</td>
				</tr>
				<tr>
					<th>&nbsp;</th>
					<td class="col1"></td>
					<td class="col2">
						<input type="submit" value="Update / Save" class="button-secondary"/>
					</td>
				</tr>					
			</table>
		</form> 
		
		<div style="margin:50px 0">
			<form action="" method="post">
				<table class="dntly-table">
					<tr>
						<th><label for="category_base">Manual Syncing</label></th>
						<td class="col1"></td>
						<td class="col2">
							<input type="button" value="Get New Donations" id="dntly-sync-donations" class="button-primary"/>	
						</td>
					</tr>
				</table>
			</form>
		</div>
		
		</div>
		<?php endif; ?>
		
		<div id="spinner"></div>
	
		<div id="dntly_table_logging_container">
			<div id="dntly_table_logging"></div>
		</div>		

	</div><!-- dntly-form-wrapper -->
</div>