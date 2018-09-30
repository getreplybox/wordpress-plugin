<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1><?php _e( 'ReplyBox', 'replybox' ); ?></h1>

    <form method="POST">
    	<table class="form-table">
    		<tbody>
    			<tr>
    				<th scope="row">
    					<label for="site_id"><?php _e( 'Site ID', 'replybox' ); ?></label>
    				</th>
    				<td>
    					<input type="text" name="site_id" id="site_id" value="" class="regular-text">
    				</td>
    			</tr>
    			<tr>
    				<th scope="row">
    					<label for="secure_token"><?php _e( 'Secure Token', 'replybox' ); ?></label>
    				</th>
    				<td>
    					<input type="text" name="secure_token" id="secure_token" value="" class="regular-text" readonly>
    					<p class="description">
    						Description of secure token.
    					</p>
    				</td>
    			</tr>
    		</tbody>
    	</table>

    	<p class="submit">
    		<button type="submit" class="button button-primary">
    			<?php _e( 'Save Changes', 'replybox' ); ?>
    		</button>
    	</p>
	</form>
</div>