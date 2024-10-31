<?php
/*
 * Plugin Name:   SEO Post Link
 * Version:       1.0.3
 * Plugin URI:    http://www.maxblogpress.com/plugins/spl/
 * Description:   Automatically make your post link short and SEO friendly by removing unnecessary words from your post slug. Adjust your settings <a href="options-general.php?page=seo-post-link/seo-post-link.php">here</a>.
 * Author:        MaxBlogPress
 * Author URI:    http://www.maxblogpress.com
 */
 
define('SPL_NAME', 'SEO Post Link');	// Name of the Plugin
define('SPL_VERSION', '1.0.3');			// Current version of the Plugin

/**
 * ShortPostLinks - SEO Post Link Class
 * Holds all the necessary functions and variables
 */
class ShortPostLinks 
{
    /**
     * SEO Post Link plugin path
     * @var string
     */
	var $spl_path = '';
	
    /**
     * SEO Post Link options. Holds various settings for short post links.
     * @var string
     */
	var $spl_options = '';
	
    /**
     * SEO Post Link omitted words file name.
     * @var string
     */
	var $spl_omitwords_file = 'omitted-words.txt';
	
	/**
     * Holds the default settings values
	 * These values will be set while activating the plugin
     * @var array
     */
	var	$default_settings = array(
					'spl_max_length' => 30, 'spl_min_word_length' => 4
					);

	/**
	 * Constructor. Adds SEO Post Link plugin's actions/filters.
	 * @access public
	 */
	function ShortPostLinks() { 
		$this->spl_path 	= preg_replace('/^.*wp-content[\\\\\/]plugins[\\\\\/]/', '', __FILE__);
		$this->spl_path     = str_replace('\\','/',$this->spl_path);
		$this->site_url     = get_bloginfo('wpurl');
		$this->site_url     = (strpos($this->site_url,'http://') === false)  ? get_bloginfo('siteurl') : $this->site_url;
		$this->spl_fullpath = $this->site_url.'/wp-content/plugins/'.substr($this->spl_path,0,strrpos($this->spl_path,'/')).'/';
		$this->spl_abspath = str_replace("\\","/",ABSPATH); 
		$this->img_how      = '<img src="'.$this->spl_fullpath.'images/how.gif" border="0" align="absmiddle">';
		$this->img_comment  = '<img src="'.$this->spl_fullpath.'images/comment.gif" border="0" align="absmiddle">';
	    
		add_action('activate_'.$this->spl_path, array(&$this, 'splActivate'));
		add_action('admin_menu', array(&$this, 'splAddMenu'));
		$this->spl_activate = get_option('spl_activate');
		if ( $this->spl_activate == 2 ) {
			add_filter('name_save_pre', array(&$this, 'splTrimSlug')); 
		}
		if( !$this->spl_options = get_option('spl_options') ) {
			$this->spl_options  = $this->default_settings;
		}
		if( !$this->spl_omitted_words = get_option('spl_omitted_words') ) {
			$this->spl_omitted_words  = array();
		}
	}
	
	/**
	 * Called when plugin is activated. Adds option value to the options table.
	 * @access public
	 */
	function splActivate() {
		$omitted_words = file($this->spl_fullpath.$this->spl_omitwords_file, FILE_IGNORE_NEW_LINES);
		foreach ( (array) $omitted_words as $key => $word ) {
			$omitted_words[$key] = trim(strtolower($word));
		}
		add_option('spl_options', $this->default_settings, 'SEO Post Link maximum slug length', 'no');
		add_option('spl_omitted_words', $omitted_words, 'SEO Post Link omitted word list', 'no');
		return true;
	}
	
	/**
	 * Creates a clean and short post slug
	 * @param string $slug
	 * @access public
	 */
	function splTrimSlug($slug) {
		global $wpdb;
		
		// If slug already exists or is manually entered 
		if ( !empty($slug) ) {
			return $slug;
		}
		
		$spl_title = trim($_POST['post_title']);
		if ( (strlen($spl_title) > $this->spl_options['spl_max_length']) || $this->spl_options['spl_force_removal'] ) {
			$words = explode(' ',$spl_title);
			$spl_title_new = '';
			for ( $i = 0; $i < count($words); $i++ ) {
				// Omit the word if found in omitted word list
				if( !in_array(trim(strtolower($words[$i])), $this->spl_omitted_words) ) {
					// Omit short words
					if ( $this->spl_options['spl_remove_short_word'] && (strlen(trim($words[$i])) <= $this->spl_options['spl_min_word_length']) ) {
						continue;
					}
					$spl_title_new = $spl_title_new.' '.$words[$i];
					// Exit if title length exceeds spl_max_length
					if ( strlen($spl_title_new) >= $this->spl_options['spl_max_length'] ) {
						break;
					}
				}
			}
			return sanitize_title(trim($spl_title_new));
		} else {
			return '';
		}
	}
	
	/**
	 * Adds "SEO Post Link" link to admin Options menu
	 * @access public 
	 */
	function splAddMenu() {
		add_options_page('SEO Post Link', 'SEO Post Link', 'manage_options', $this->spl_path, array(&$this, 'splOptionsPg'));
	}
	
	/**
	 * Page Header
	 */
	function splHeader() {
		if ( !isset($_GET['dnl']) ) {	
			$spl_version_chk = $this->splRecheckData();
			if ( ($spl_version_chk == '') || strtotime(date('Y-m-d H:i:s')) > (strtotime($spl_version_chk['last_checked_on']) + $spl_version_chk['recheck_interval']*60*60) ) {
				$update_arr = $this->splExtractUpdateData();
				if ( count($update_arr) > 0 ) {
					$latest_version   = $update_arr[0];
					$recheck_interval = $update_arr[1];
					$download_url     = $update_arr[2];
					$msg_in_plugin    = $update_arr[3];
					$msg_in_plugin    = $update_arr[4];
					$upgrade_url      = $update_arr[5];
					if( SPL_VERSION < $latest_version ) {
						$spl_version_check = array('recheck_interval' => $recheck_interval, 'last_checked_on' => date('Y-m-d H:i:s'));
						$this->splRecheckData($spl_version_check);
						$msg_in_plugin = str_replace("%latest-version%", $latest_version, $msg_in_plugin);
						$msg_in_plugin = str_replace("%plugin-name%", SPL_NAME, $msg_in_plugin);
						$msg_in_plugin = str_replace("%upgrade-url%", $upgrade_url, $msg_in_plugin);
						$msg_in_plugin = '<div style="border-bottom:1px solid #CCCCCC;background-color:#FFFEEB;padding:6px;font-size:11px;text-align:center">'.$msg_in_plugin.'</div>';
					} else {
						$msg_in_plugin = '';
					}
				}
			}
		}
		echo '<h2>'.SPL_NAME.' '.SPL_VERSION.'</h2>';
		if ( trim($msg_in_plugin) != '' && !isset($_GET['dnl']) ) echo $msg_in_plugin;
		echo '<br /><strong>'.$this->img_how.' <a href="http://www.maxblogpress.com/plugins/spl/spl-use/" target="_blank">How to use it</a>&nbsp;&nbsp;&nbsp;'; 
        echo $this->img_comment.' <a href="http://www.maxblogpress.com/plugins/spl/spl-comments/" target="_blank">Comments and Suggestions</a></strong><br /><br />';
	}
	
	/**
	 * Page Footer
	 */
	function splFooter() {
		echo '<p style="text-align:center;margin-top:3em;"><strong>'.SPL_NAME.' '.SPL_VERSION.' by <a href="http://www.maxblogpress.com/" target="_blank" >MaxBlogPress</a></strong></p>';
	}
	
	/**
	 * "SEO Post Link" Options page
	 * @access public 
	 */
	function splOptionsPg() {
		global $wpdb;
		$msg = '';

		$form_1 = 'spl_reg_form_1';
		$form_2 = 'spl_reg_form_2';
		// Activate the plugin if email already on list
		if ( trim($_GET['mbp_onlist']) == 1 ) { 
			$this->spl_activate = 2;
			update_option('spl_activate', $this->spl_activate);
			$msg = 'Thank you for registering the plugin. It has been activated'; 
		} 
		// If registration form is successfully submitted
		if ( ((trim($_GET['submit']) != '' && trim($_GET['from']) != '') || trim($_GET['submit_again']) != '') && $this->spl_activate != 2 ) { 
			update_option('spl_name', $_GET['name']);
			update_option('spl_email', $_GET['from']);
			$this->spl_activate = 1;
			update_option('spl_activate', $this->spl_activate);
		}
		if ( intval($this->spl_activate) == 0 ) { // First step of plugin registration
			$this->splRegister_1($form_1);
		} else if ( intval($this->spl_activate) == 1 ) { // Second step of plugin registration
			$name  = get_option('spl_name');
			$email = get_option('spl_email');
			$this->splRegister_2($form_2,$name,$email);
		} else if ( intval($this->spl_activate) == 2 ) { // Options page
			if ( $_GET['action'] == 'upgrade' ) {
				$this->splUpgradePlugin();
				exit;
			}
			$spl_post_data = $_POST['spl'];
			if ( $spl_post_data['save_options'] ) {
				$this->spl_options['spl_max_length']    = intval($spl_post_data['spl_max_length']);
				$this->spl_options['spl_force_removal'] = $spl_post_data['spl_force_removal'];
				$the_omitted_words = explode("\n", trim($spl_post_data['spl_omitted_words']));
				foreach( (array) $the_omitted_words as $key => $word ) {
					$this->spl_omitted_words[$key] = trim(stripslashes($word));
				}
				update_option("spl_options", $this->spl_options);
				update_option("spl_omitted_words", $this->spl_omitted_words);
				$msg = "Options Saved.";
			}
			if ( $spl_post_data['save_adv_options'] ) {
				$this->spl_options['spl_remove_short_word'] = $spl_post_data['spl_remove_short_word'];
				$this->spl_options['spl_min_word_length']   = $spl_post_data['spl_min_word_length'];
				update_option("spl_options", $this->spl_options);
				$msg = "Options Saved.";
			}
			if ( trim($msg) !== '' ) {
				echo '<div id="message" class="updated fade"><p><strong>'.$msg.'</strong></p></div>';
			}
			$force_removal_chk = '';
			$remove_short_word_chk = '';
			if ( $this->spl_options['spl_force_removal'] == 1 ) {
				$force_removal_chk = ' checked ';
			}
			if ( $this->spl_options['spl_remove_short_word'] == 1 ) {
				$remove_short_word_chk = ' checked ';
			}
			?>
			<script type="text/javascript">
			<!--
			function splShowHide(Div,Img) {
				var divCtrl = document.getElementById(Div);
				var Img     = document.getElementById(Img);
				if(divCtrl.style=="" || divCtrl.style.display=="none") {
					divCtrl.style.display = "block";
					Img.src = '<?php echo $this->spl_fullpath?>images/minus.gif';
				}
				else if(divCtrl.style!="" || divCtrl.style.display=="block") {
					divCtrl.style.display = "none";
					Img.src = '<?php echo $this->spl_fullpath?>images/plus.gif';
				}
			}
			//-->
			</script>
			<div class="wrap">
			 <?php $this->splHeader(); ?>
			 <form name="spl_form" method="post">
			 <table width="640" cellpadding="4" cellspacing="2" border="0" style="border:1px solid #dfdfdf">
			  <tr class="alternate">
			   <td width="25%"><strong><?php _e('Max Slug Length', 'spl'); ?> :</strong></td>
			   <td><input type="text" name="spl[spl_max_length]" id="spl_max_length" value="<?php echo $this->spl_options['spl_max_length'];?>" style="width:32px" maxlength="7" /> characters</td>
			  </tr>
			  <tr>
			   <td colspan="2"><input type="checkbox" name="spl[spl_force_removal]" id="spl_force_removal" value="1" <?php echo $force_removal_chk;?> /> 
			   <?php _e('Force removal of unncessary words even if the default slug is shorter than "max slug length"', 'spl'); ?></td>
			  </tr>
			  <tr class="alternate">
			   <td><strong><?php _e('Unnecessary Words', 'spl'); ?> :</strong></td>
			   <td><textarea name="spl[spl_omitted_words]" id="spl_omitted_words" rows="12" cols="40" style="width:260px"><?php echo trim(implode("\n", $this->spl_omitted_words));?></textarea></td>
			  </tr>
			  <tr>
			   <td colspan="2"><input type="submit" name="spl[save_options]" value="<?php _e('Save Options', 'spl'); ?>" class="button" /></td>
			  </tr>
			 </table><br />
			 </form>
			 
			 <form name="spl_form_adv" method="post">
			 <h3><a name="spldv" href="#spldv" onclick="splShowHide('spl_adv_option','spl_adv_img');"><img src="<?php echo $this->spl_fullpath?>images/plus.gif" id="spl_adv_img" border="0" /><?php _e('More Options (Optional)', 'spl'); ?></a></h3>
			 <div id="spl_adv_option" style="display:none">
			 <table width="640" cellpadding="4" cellspacing="2" border="0" style="border:1px solid #dfdfdf">
			  <tr class="alternate">
			   <td><input type="checkbox" name="spl[spl_remove_short_word]" id="spl_remove_short_word" value="1" <?php echo $remove_short_word_chk;?> /> Remove short words of length upto 
			   <input type="text" name="spl[spl_min_word_length]" id="spl_min_word_length" value="<?php echo $this->spl_options['spl_min_word_length'];?>" style="width:25px" maxlength="7" /> characters</td>
			  </tr>
			  <tr>
			   <td><input type="submit" name="spl[save_adv_options]" value="<?php _e('Save', 'spl'); ?>" class="button" /></td>
			  </tr>
			 </table>
			 </div>
			 </form>
			<?php $this->splFooter(); ?>
			</div>
			<?php
		}
	}
	
	/**
	 * Gets recheck data fro displaying auto upgrade information
	 */
	function splRecheckData($data='') {
		if ( $data != '' ) {
			update_option('spl_version_check',$data);
		} else {
			$version_chk = get_option('spl_version_check');
			return $version_chk;
		}
	}
	
	/**
	 * Extracts plugin update data
	 */
	function splExtractUpdateData() {
		$arr = array();
		$version_chk_file = "http://www.maxblogpress.com/plugin-updates/seo-post-link.php?v=".SPL_VERSION;
		$content = wp_remote_fopen($version_chk_file);
		if ( $content ) {
			$content          = nl2br($content);
			$content_arr      = explode('<br />', $content);
			$latest_version   = trim(trim(strstr($content_arr[0],'~'),'~'));
			$recheck_interval = trim(trim(strstr($content_arr[1],'~'),'~'));
			$download_url     = trim(trim(strstr($content_arr[2],'~'),'~'));
			$msg_plugin_mgmt  = trim(trim(strstr($content_arr[3],'~'),'~'));
			$msg_in_plugin    = trim(trim(strstr($content_arr[4],'~'),'~'));
			$upgrade_url      = $this->site_url.'/wp-admin/options-general.php?page='.$this->spl_path.'&action=upgrade&dnl='.$download_url;
			$arr = array($latest_version, $recheck_interval, $download_url, $msg_plugin_mgmt, $msg_in_plugin, $upgrade_url);
		}
		return $arr;
	}
	
	/**
	 * Interface for upgrading plugin
	 */
	function splUpgradePlugin() {
		global $wp_version;
		$plugin = $this->spl_path;
		echo '<div class="wrap">';
		$this->splHeader();
		echo '<h3>Upgrade Plugin &raquo;</h3>';
		if ( $wp_version >= 2.5 ) {
			$res = $this->splDoPluginUpgrade($plugin);
		} else {
			echo '&raquo; Wordpress 2.5 or higher required for automatic upgrade.<br><br>';
		}
		if ( $res == false ) echo '&raquo; Plugin couldn\'t be upgraded.<br><br>';
		echo '<br><strong><a href="'.$this->site_url.'/wp-admin/plugins.php">Go back to plugins page</a> | <a href="'.$this->site_url.'/wp-admin/options-general.php?page='.$this->spl_path.'">'.SPL_NAME.' home page</a></strong>';
		$this->splFooter();
		echo '</div>';
		include('admin-footer.php');
	}
	
	/**
	 * Carries out plugin upgrade
	 */
	function splDoPluginUpgrade($plugin) {
		set_time_limit(300);
		global $wp_filesystem;
		$debug = 0;
		$was_activated = is_plugin_active($plugin); // Check current status of the plugin to retain the same after the upgrade

		// Is a filesystem accessor setup?
		if ( ! $wp_filesystem || !is_object($wp_filesystem) ) {
			WP_Filesystem();
		}
		if ( ! is_object($wp_filesystem) ) {
			echo '&raquo; Could not access filesystem.<br /><br />';
			return false;
		}
		if ( $wp_filesystem->errors->get_error_code() ) {
			echo '&raquo; Filesystem error '.$wp_filesystem->errors.'<br /><br />';
			return false;
		}
		
		if ( $debug ) echo '> File System Okay.<br /><br />';
		
		// Get the URL to the zip file
		$package = $_GET['dnl'];
		if ( empty($package) ) {
			echo '&raquo; Upgrade package not available.<br /><br />';
			return false;
		}
		// Download the package
		$file = download_url($package);
		if ( is_wp_error($file) || $file == '' ) {
			echo '&raquo; Download failed. '.$file->get_error_message().'<br /><br />';
			return false;
		}
		$working_dir = $this->spl_abspath . 'wp-content/upgrade/' . basename($plugin, '.php');
		
		if ( $debug ) echo '> Working Directory = '.$working_dir.'<br /><br />';
		
		// Unzip package to working directory
		$result = $this->splUnzipFile($file, $working_dir);
		if ( is_wp_error($result) ) {
			unlink($file);
			$wp_filesystem->delete($working_dir, true);
			echo '&raquo; Couldn\'t unzip package to working directory. Make sure that "/wp-content/upgrade/" folder has write permission (CHMOD 755).<br /><br />';
			return $result;
		}
		
		if ( $debug ) echo '> Unzip package to working directory successful<br /><br />';
		
		// Once extracted, delete the package
		unlink($file);
		if ( is_plugin_active($plugin) ) {
			deactivate_plugins($plugin, true); //Deactivate the plugin silently, Prevent deactivation hooks from running.
		}
		
		// Remove the old version of the plugin
		$plugin_dir = dirname($this->spl_abspath . PLUGINDIR . "/$plugin");
		$plugin_dir = trailingslashit($plugin_dir);
		// If plugin is in its own directory, recursively delete the directory.
		if ( strpos($plugin, '/') && $plugin_dir != $base . PLUGINDIR . '/' ) {
			$deleted = $wp_filesystem->delete($plugin_dir, true);
		} else {

			$deleted = $wp_filesystem->delete($base . PLUGINDIR . "/$plugin");
		}
		if ( !$deleted ) {
			$wp_filesystem->delete($working_dir, true);
			echo '&raquo; Could not remove the old plugin. Make sure that "/wp-content/plugins/" folder has write permission (CHMOD 755).<br /><br />';
			return false;
		}
		
		if ( $debug ) echo '> Old version of the plugin removed successfully.<br /><br />';

		// Copy new version of plugin into place
		if ( !$this->splCopyDir($working_dir, $this->spl_abspath . PLUGINDIR) ) {
			echo '&raquo; Installation failed. Make sure that "/wp-content/plugins/" folder has write permission (CHMOD 755)<br /><br />';
			return false;
		}
		//Get a list of the directories in the working directory before we delete it, we need to know the new folder for the plugin
		$filelist = array_keys( $wp_filesystem->dirlist($working_dir) );
		// Remove working directory
		$wp_filesystem->delete($working_dir, true);
		// if there is no files in the working dir
		if( empty($filelist) ) {
			echo '&raquo; Installation failed.<br /><br />';
			return false; 
		}
		$folder = $filelist[0];
		$plugin = get_plugins('/' . $folder);      // Pass it with a leading slash, search out the plugins in the folder, 
		$pluginfiles = array_keys($plugin);        // Assume the requested plugin is the first in the list
		$result = $folder . '/' . $pluginfiles[0]; // without a leading slash as WP requires
		
		if ( $debug ) echo '> Copy new version of plugin into place successfully.<br /><br />';
		
		if ( is_wp_error($result) ) {
			echo '&raquo; '.$result.'<br><br>';
			return false;
		} else {
			//Result is the new plugin file relative to PLUGINDIR
			echo '&raquo; Plugin upgraded successfully<br><br>';	
			if( $result && $was_activated ){
				echo '&raquo; Attempting reactivation of the plugin...<br><br>';	
				echo '<iframe style="display:none" src="' . wp_nonce_url('update.php?action=activate-plugin&plugin=' . $result, 'activate-plugin_' . $result) .'"></iframe>';
				sleep(15);
				echo '&raquo; Plugin reactivated successfully.<br><br>';	
			}
			return true;
		}
	}
	
	/**
	 * Copies directory from given source to destinaktion
	 */
	function splCopyDir($from, $to) {
		global $wp_filesystem;
		$dirlist = $wp_filesystem->dirlist($from);
		$from = trailingslashit($from);
		$to = trailingslashit($to);
		foreach ( (array) $dirlist as $filename => $fileinfo ) {
			if ( 'f' == $fileinfo['type'] ) {
				if ( ! $wp_filesystem->copy($from . $filename, $to . $filename, true) ) return false;
				$wp_filesystem->chmod($to . $filename, 0644);
			} elseif ( 'd' == $fileinfo['type'] ) {
				if ( !$wp_filesystem->mkdir($to . $filename, 0755) ) return false;
				if ( !$this->splCopyDir($from . $filename, $to . $filename) ) return false;
			}
		}
		return true;
	}
	
	/**
	 * Unzips the file to given directory
	 */
	function splUnzipFile($file, $to) {
		global $wp_filesystem;
		if ( ! $wp_filesystem || !is_object($wp_filesystem) )
			return new WP_Error('fs_unavailable', __('Could not access filesystem.'));
		$fs =& $wp_filesystem;
		require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$archive = new PclZip($file);
		// Is the archive valid?
		if ( false == ($archive_files = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING)) )
			return new WP_Error('incompatible_archive', __('Incompatible archive'), $archive->errorInfo(true));
		if ( 0 == count($archive_files) )
			return new WP_Error('empty_archive', __('Empty archive'));
		$to = trailingslashit($to);
		$path = explode('/', $to);
		$tmppath = '';
		for ( $j = 0; $j < count($path) - 1; $j++ ) {
			$tmppath .= $path[$j] . '/';
			if ( ! $fs->is_dir($tmppath) )
				$fs->mkdir($tmppath, 0755);
		}
		foreach ($archive_files as $file) {
			$path = explode('/', $file['filename']);
			$tmppath = '';
			// Loop through each of the items and check that the folder exists.
			for ( $j = 0; $j < count($path) - 1; $j++ ) {
				$tmppath .= $path[$j] . '/';
				if ( ! $fs->is_dir($to . $tmppath) )
					if ( !$fs->mkdir($to . $tmppath, 0755) )
						return new WP_Error('mkdir_failed', __('Could not create directory'));
			}
			// We've made sure the folders are there, so let's extract the file now:
			if ( ! $file['folder'] )
				if ( !$fs->put_contents( $to . $file['filename'], $file['content']) )
					return new WP_Error('copy_failed', __('Could not copy file'));
				$fs->chmod($to . $file['filename'], 0755);
		}
		return true;
	}
	
	/**
	 * Plugin registration form
	 * @access public 
	 */
	function splRegistrationForm($form_name, $submit_btn_txt='Register', $name, $email, $hide=0, $submit_again='') {
		$wp_url = get_bloginfo('wpurl');
		$wp_url = (strpos($wp_url,'http://') === false) ? get_bloginfo('siteurl') : $wp_url;
		$thankyou_url = $wp_url.'/wp-admin/options-general.php?page='.$_GET['page'];
		$onlist_url   = $wp_url.'/wp-admin/options-general.php?page='.$_GET['page'].'&amp;mbp_onlist=1';
		if ( $hide == 1 ) $align_tbl = 'left';
		else $align_tbl = 'center';
		?>
		
		<?php if ( $submit_again != 1 ) { ?>
		<script><!--
		function trim(str){
			var n = str;
			while ( n.length>0 && n.charAt(0)==' ' ) 
				n = n.substring(1,n.length);
			while( n.length>0 && n.charAt(n.length-1)==' ' )	
				n = n.substring(0,n.length-1);
			return n;
		}
		function splValidateForm_0() {
			var name = document.<?php echo $form_name;?>.name;
			var email = document.<?php echo $form_name;?>.from;
			var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
			var err = ''
			if ( trim(name.value) == '' )
				err += '- Name Required\n';
			if ( reg.test(email.value) == false )
				err += '- Valid Email Required\n';
			if ( err != '' ) {
				alert(err);
				return false;
			}
			return true;
		}
		//-->
		</script>
		<?php } ?>
		<table align="<?php echo $align_tbl;?>">
		<form name="<?php echo $form_name;?>" method="post" action="http://www.aweber.com/scripts/addlead.pl" <?php if($submit_again!=1){;?>onsubmit="return splValidateForm_0()"<?php }?>>
		 <input type="hidden" name="unit" value="maxbp-activate">
		 <input type="hidden" name="redirect" value="<?php echo $thankyou_url;?>">
		 <input type="hidden" name="meta_redirect_onlist" value="<?php echo $onlist_url;?>">
		 <input type="hidden" name="meta_adtracking" value="spl-w-activate">
		 <input type="hidden" name="meta_message" value="1">
		 <input type="hidden" name="meta_required" value="from,name">
	 	 <input type="hidden" name="meta_forward_vars" value="1">	
		 <?php if ( $submit_again != '' ) { ?> 	
		 <input type="hidden" name="submit_again" value="1">
		 <?php } ?>		 
		 <?php if ( $hide == 1 ) { ?> 
		 <input type="hidden" name="name" value="<?php echo $name;?>">
		 <input type="hidden" name="from" value="<?php echo $email;?>">
		 <?php } else { ?>
		 <tr><td>Name: </td><td><input type="text" name="name" value="<?php echo $name;?>" size="25" maxlength="150" /></td></tr>
		 <tr><td>Email: </td><td><input type="text" name="from" value="<?php echo $email;?>" size="25" maxlength="150" /></td></tr>
		 <?php } ?>
		 <tr><td>&nbsp;</td><td><input type="submit" name="submit" value="<?php echo $submit_btn_txt;?>" class="button" /></td></tr>
		</form>
		</table>
		<?php
	}
	
	/**
	 * Register Plugin - Step 2
	 * @access public 
	 */
	function splRegister_2($form_name='frm2',$name,$email) {
		$msg = 'You have not clicked on the confirmation link yet. A confirmation email has been sent to you again. Please check your email and click on the confirmation link to activate the plugin.';
		if ( trim($_GET['submit_again']) != '' && $msg != '' ) {
			echo '<div id="message" class="updated fade"><p><strong>'.$msg.'</strong></p></div>';
		}
		?>
		<div class="wrap"><h2> <?php echo SPL_NAME.' '.SPL_VERSION; ?></h2>
		 <center>
		 <table width="640" cellpadding="5" cellspacing="1" bgcolor="#ffffff" style="border:1px solid #e9e9e9">
		  <tr><td align="center"><h3>Almost Done....</h3></td></tr>
		  <tr><td><h3>Step 1:</h3></td></tr>
		  <tr><td>A confirmation email has been sent to your email "<?php echo $email;?>". You must click on the link inside the email to activate the plugin.</td></tr>
		  <tr><td><strong>The confirmation email will look like:</strong><br /><img src="http://www.maxblogpress.com/images/activate-plugin-email.jpg" vspace="4" border="0" /></td></tr>
		  <tr><td>&nbsp;</td></tr>
		  <tr><td><h3>Step 2:</h3></td></tr>
		  <tr><td>Click on the button below to Verify and Activate the plugin.</td></tr>
		  <tr><td><?php $this->splRegistrationForm($form_name.'_0','Verify and Activate',$name,$email,$hide=1,$submit_again=1);?></td></tr>
		 </table>
		 <p>&nbsp;</p>
		 <table width="640" cellpadding="5" cellspacing="1" bgcolor="#ffffff" style="border:1px solid #e9e9e9">
           <tr><td><h3>Troubleshooting</h3></td></tr>
           <tr><td><strong>The confirmation email is not there in my inbox!</strong></td></tr>
           <tr><td>Dont panic! CHECK THE JUNK, spam or bulk folder of your email.</td></tr>
           <tr><td>&nbsp;</td></tr>
           <tr><td><strong>It's not there in the junk folder either.</strong></td></tr>
           <tr><td>Sometimes the confirmation email takes time to arrive. Please be patient. WAIT FOR 6 HOURS AT MOST. The confirmation email should be there by then.</td></tr>
           <tr><td>&nbsp;</td></tr>
           <tr><td><strong>6 hours and yet no sign of a confirmation email!</strong></td></tr>
           <tr><td>Please register again from below:</td></tr>
           <tr><td><?php $this->splRegistrationForm($form_name,'Register Again',$name,$email,$hide=0,$submit_again=2);?></td></tr>
           <tr><td><strong>Help! Still no confirmation email and I have already registered twice</strong></td></tr>
           <tr><td>Okay, please register again from the form above using a DIFFERENT EMAIL ADDRESS this time.</td></tr>
           <tr><td>&nbsp;</td></tr>
           <tr>
             <td><strong>Why am I receiving an error similar to the one shown below?</strong><br />
                 <img src="http://www.maxblogpress.com/images/no-verification-error.jpg" border="0" vspace="8" /><br />
               You get that kind of error when you click on &quot;Verify and Activate&quot; button or try to register again.<br />
               <br />
               This error means that you have already subscribed but have not yet clicked on the link inside confirmation email. In order to  avoid any spam complain we don't send repeated confirmation emails. If you have not recieved the confirmation email then you need to wait for 12 hours at least before requesting another confirmation email. </td>
           </tr>
           <tr><td>&nbsp;</td></tr>
           <tr><td><strong>But I've still got problems.</strong></td></tr>
           <tr><td>Stay calm. <strong><a href="http://www.maxblogpress.com/contact-us/" target="_blank">Contact us</a></strong> about it and we will get to you ASAP.</td></tr>
         </table>
		 </center>		
		<p style="text-align:center;margin-top:3em;"><strong><?php echo SPL_NAME.' '.SPL_VERSION; ?> by <a href="http://www.maxblogpress.com/" target="_blank" >MaxBlogPress</a></strong></p>
	    </div>
		<?php
	}

	/**
	 * Register Plugin - Step 1
	 * @access public 
	 */
	function splRegister_1($form_name='frm1') {
		global $userdata;
		$name  = trim($userdata->first_name.' '.$userdata->last_name);
		$email = trim($userdata->user_email);
		?>
		<div class="wrap"><h2> <?php echo SPL_NAME.' '.SPL_VERSION; ?></h2>
		 <center>
		 <table width="620" cellpadding="3" cellspacing="1" bgcolor="#ffffff" style="border:1px solid #e9e9e9">
		  <tr><td align="center"><h3>Please register the plugin to activate it. (Registration is free)</h3></td></tr>
		  <tr><td align="left">In addition you'll receive complimentary subscription to MaxBlogPress Newsletter which will give you many tips and tricks to attract lots of visitors to your blog.</td></tr>
		  <tr><td align="center"><strong>Fill the form below to register the plugin:</strong></td></tr>
		  <tr><td><?php $this->splRegistrationForm($form_name,'Register',$name,$email);?></td></tr>
		  <tr><td align="center"><font size="1">[ Your contact information will be handled with the strictest confidence <br />and will never be sold or shared with third parties ]</font></td></td></tr>
		 </table>
		 </center>
		<p style="text-align:center;margin-top:3em;"><strong><?php echo SPL_NAME.' '.SPL_VERSION; ?> by <a href="http://www.maxblogpress.com/" target="_blank" >MaxBlogPress</a></strong></p>
	    </div>
		<?php
	}
	
} // Eof Class

$ShortPostLinks = new ShortPostLinks();
?>