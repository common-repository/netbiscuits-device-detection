<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) )  {
    exit;
};


add_option( 'nb_dd_account', '', '', 'yes' );
add_option( 'nb_dd_token', '', '', 'yes' );
add_option( 'nb_dd_profile', '', '', 'yes' );
add_option( 'nb_dd_debug', 0, '', 'yes' );

add_action( 'admin_menu', 'nb_dd_configuration' );



function nb_dd_configuration() {
    // set-up localitzation
    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain( 'nb_device_detection', false, $plugin_dir );

    // init admin page
    add_options_page( 'Netbiscuits Device Detection', 'NB DD', 'manage_options', 'netbiscuits-device-detection', 'nb_dd_options' );
}

function nb_dd_options() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    #echo "<pre>";
    #print_r($_POST);
    #echo "</pre>";

    $hidden_field_name = 'nb_dd_submit_hidden';

    if ($_REQUEST["clear_cache"]) {
    	$_POST[ $hidden_field_name ] = "N";
    	global $wpdb;
    	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE ('_transient_nb_dd_%')" );
    	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE ('_transient_timeout_nb_dd_%')" );
    	/*?> <div class="updated"><p><strong><?php _e('DD Cache purged', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_label2 = 'Account Name';
    $opt_name2 = 'nb_dd_account';
    $data_field_name2 = 'nb_dd_account';

    $opt_val2 = get_option( $opt_name2 );

    $updated = false;
    if ( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        $opt_val2 = $_POST[ $data_field_name2 ];
        update_option( $opt_name2, $opt_val2 );
        /*?> <div class="updated"><p><strong><?php _e($opt_label2 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_label1 = 'Token';
    $opt_name1 = 'nb_dd_token';
    $data_field_name1 = 'nb_dd_token';

    $opt_val1 = get_option( $opt_name1 );

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        $opt_val1 = $_POST[ $data_field_name1 ];
        update_option( $opt_name1, $opt_val1 );
        /*?> <div class="updated"><p><strong><?php _e($opt_label1 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_label3 = 'Profile Name';
    $opt_name3 = 'nb_dd_profile';
    $data_field_name3 = 'nb_dd_profile';

    $opt_val3 = get_option( $opt_name3 );

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        $opt_val3 = $_POST[ $data_field_name3 ];
        update_option( $opt_name3, $opt_val3 );
        /*?> <div class="updated"><p><strong><?php _e($opt_label3 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_label4 = 'Show Debug <i title="Visibly output DD code into pages; useful for debugging">?</i>';
    $opt_name4 = 'nb_dd_debug';
    $data_field_name4 = 'nb_dd_debug';

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        if (isset($_POST[ $data_field_name4 ]))
			update_option( $opt_name4, 1 );
        else
        	update_option( $opt_name4, 0 );
        /*?> <div class="updated"><p><strong><?php _e($opt_label4 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_val4 = get_option( $opt_name4 );

    $opt_label5 = 'Cache Expiration Time';
    $opt_name5 = 'nb_dd_cache_exprire_time';
    $data_field_name5 = 'nb_dd_cache_expriretime';

    $opt_val5 = get_option( $opt_name5 );

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
    	$opt_val5 = $_POST[ $data_field_name5 ];
    	update_option( $opt_name5, $opt_val5 );
    	/*?> <div class="updated"><p><strong><?php _e($opt_label4 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_label6 = 'Cache Active';
    $opt_name6 = 'nb_dd_cache_active';
    $data_field_name6 = 'nb_dd_cache';

    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
    	if (isset($_POST[ $data_field_name6 ]))
    		update_option( $opt_name6, 1 );
    	else
    		update_option( $opt_name6, 0 );
    	/*?> <div class="updated"><p><strong><?php _e($opt_label6 . ' saved!', 'nb_device_detection' ) ?></strong></p></div><?php*/
        $updated = true;
    }

    $opt_val6 = get_option( $opt_name6 );

    $msg = '';
    if ($updated) {
        $msg = '<div class="updated"><p>The settings have been saved.</p></div>';
    }


	function inputfieldrow($options) {
		printf('<tr><th>%s</th><td><input type="text" name="%s" value="%s" size="50"/></td></tr>',
			$options["label"], $options["name"], $options["value"]);
	}

	function checkboxfieldrow($options) {
		printf('<tr><th>%s</th><td><input type="checkbox" name="%s" value="1"%s/></td></tr>',
		$options["label"], $options["name"], ($options["value"] > 0 ? ' checked="checked"' : ''));
	}
?>
<div class="wrap">
    <style>
        #nb_device_detection td input {
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
            padding: .5em;
            font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
            color: #68615e;
            font-size: 13px;
            line-height: 1.4;
            background: #f1efee;
            border: 1px solid #cccccc;
            border-radius: 4px;
        }
        #nb_device_detection i {
            width: 1.3em;
            height: 1.3em;
            display: inline-block;
            text-align: center;
            border: 1px solid #006799;
            border-radius: 50%;
            font-style: normal;
            background: #008ec2;
            color: #fff;
            cursor: help;
        }
    </style>
	<div class="metabox-holder">
        <?php echo $msg; ?>
		<form id="nb_device_detection" name="nb_device_detection" method="post" action="">
			<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
			<div id="general" class="postbox">
				<h3 class="hndle">
					<p><?php echo __( 'Netbiscuits Device Detection Settings', 'nb_device_detection' )?></p>
				</h3>
				<div class="inside">
                    <p>
                        <?php echo __('To add Netbiscuits Device Detection to this website,
                        please <a href="https://my.netbiscuits.com/account-mgmt/cloudclient#wp_config" title="This link will open your Netbiscuits Detection Code page, in a new window" target="_blank">copy the credentials for your account</a>
                        and paste them below, then click the <strong>Save Changes</strong> button.', 'nb_device_detection' ); ?>
                    </p>
					<table class="form-table">
    					<tbody>
                        <?php inputfieldrow(array (
                                "label" => __($opt_label2, 'nb_device_detection' ),
                                "name"  => $data_field_name2,
                                "value" => $opt_val2
                            ));
                        ?>
    					<?php inputfieldrow(array (
    							"label" => __($opt_label1, 'nb_device_detection' ),
    							"name"  => $data_field_name1,
    							"value" => $opt_val1
    						));
    					?>
    					<?php inputfieldrow(array (
    							"label" => __($opt_label3, 'nb_device_detection' ),
    							"name"  => $data_field_name3,
    							"value" => $opt_val3
    						));
    					?>
    					<!-- <?php checkboxfieldrow(array (
    							"label" => __($opt_label6, 'nb_device_detection' ),
    							"name"  => $data_field_name6,
    							"value" => $opt_val6
    						));
    					?>
    					<?php inputfieldrow(array (
    							"label" => __($opt_label5, 'nb_device_detection' ),
    							"name"  => $data_field_name5,
    							"value" => $opt_val5
    						));
    					?> -->
    					<?php checkboxfieldrow(array (
    							"label" => __($opt_label4, 'nb_device_detection' ),
    							"name"  => $data_field_name4,
    							"value" => $opt_val4
    						));
    					?>
    					</tbody>
					</table>
					<p class="submit">
						<input type="submit" name="submit_changes" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
						<!-- <input type="submit" name="clear_cache" class="button-primary" value="<?php esc_attr_e('Clear Cache') ?>" /> -->
					</p>
				</div>
			</div>
		</form>
	</div>
</div>

<?php

}

register_activation_hook(__FILE__, array('DD', 'activate'));
register_deactivation_hook(__FILE__, array('DD', 'deactivate'));