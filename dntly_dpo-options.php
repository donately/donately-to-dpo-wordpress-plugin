<?php

include_once('dntly_to_dpo.php');

$dntly_options = get_option('dntly_options');
$user = isset($dntly_options['user']) ? $dntly_options['user'] : null;
$token = isset($dntly_options['token']) ? $dntly_options['token'] : null;
$account = isset($dntly_options['account']) ? $dntly_options['account'] : null;

$dpo_options_post = isset($_POST['dpo_options']) ? $_POST['dpo_options'] : false;
if($dpo_options_post){
	$dpo_options_post['password'] = decode_password($dpo_options_post['password']);
	update_option('dpo_options', $dpo_options_post);
	dntly_transaction_logging('Updated DPO API Settings: user=' . $dpo_options_post['user'] . ' & pass=' . ($dpo_options_post['password']?encode_password($dpo_options_post['password']):'MISSING!'));
}
$dpo_options = get_option('dpo_options');
$dpo_user = isset($dpo_options['user']) ? $dpo_options['user'] : null;
$dpo_password = isset($dpo_options['user']) ? $dpo_options['user'] : null;
?>

<div class="wrap">
	<div class="icon32" id="icon-profile"><br></div><h2>Donately to DPO Sync</h2>
	<div id="dntly-form-wrapper">
	
		<?php if(!is_plugin_active('dntly/dntly.php')): ?>
			<div class="updated" id="message"><p><strong>Alert!</strong> You must install & activate the Donately Plugin for Auto-syncing with DPO to work!</p></div>
		<?php endif; ?>	
	
		<?php if(!$token): ?>	
			<div class="updated" id="message"><p><strong>Alert!</strong> You must have an Auth Token from Donately for Auto-syncing with DPO to work!</p><p><a href="/wp-admin/options-general.php?page=dntly">Enter your Auth token here</a></p></div>
		<?php else: ?>	
						
		<form action="" id="dpo-form" method="post">
			<table class="form-table">
				<tbody>
				<tr>
					<th><label for="category_base">Donor Perfect Admin User</label></th>
					<td>
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
					<td>
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
					<td>
					<?php if($dpo_user & $dpo_password): ?>
						<input type="button" value="Change DPO User/Pass" id="dpo-reset-user" class="button-secondary" />
					<?php else: ?>
						<input type="submit" value="Set DPO User/Pass" id="dpo-get-user" class="button-secondary" />
					<?php endif; ?>
					</td>
				</tr>	
			</table>
		</form>
		
		<div style="margin:50px 0">
			<form action="" method="post">
				<input type="button" value="Sync Donations" id="dntly-sync-donations" class="button-primary"/> 	
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