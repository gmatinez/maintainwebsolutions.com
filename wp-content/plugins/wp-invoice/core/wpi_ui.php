<?php
/**
  Handles functions that are related to the user interface
 */

class WPI_UI {


  /**
   * Sets up plugin pages and loads their scripts
   *
   * @since 3.0
   *
   */
  function admin_menu() {
    global $wpi_settings, $submenu, $wp_version;

    //unset($submenu['edit.php?post_type=wpi_object'][10]);

    /* Get capability required for this plugin's menu to be displayed to the user */
    $capability = self::get_capability_by_level($wpi_settings['user_level']);
    
    $wpi_settings['pages']['main'] = add_object_page( __('Invoice', WPI),  'Invoice', $capability, 'wpi_main', array('WPI_UI', 'page_loader'), WPI_URL . "/core/css/images/wp_invoice.png");
    $wpi_settings['pages']['main'] = add_submenu_page('wpi_main', __('View All', WPI), __('View All', WPI), $capability, 'wpi_main',array('WPI_UI', 'page_loader'));
    $wpi_settings['pages']['edit'] = add_submenu_page('wpi_main', __('Add New', WPI), __('Add New', WPI), $capability, 'wpi_page_manage_invoice',array('WPI_UI', 'page_loader'));
    $wpi_settings['pages']['reports'] = add_submenu_page('wpi_main', __('Reports', WPI), __('Reports', WPI), $capability, 'wpi_page_reports',array('WPI_UI', 'page_loader'));

    $wpi_settings['pages'] = apply_filters('wpi_pages', $wpi_settings['pages']);

    $wpi_settings['pages']['settings'] = add_submenu_page('wpi_main', __('Settings', WPI), __('Settings', WPI), $capability, 'wpi_page_settings', array('WPI_UI', 'page_loader'));
    
    /* Update screens information */
    WPI_Settings::setOption('pages', $wpi_settings['pages']);
    
    // Add Actions
    add_action('load-' . $wpi_settings['pages']['main'], array( 'WPI_UI', 'pre_load_main_page' ));
    add_action('load-' . $wpi_settings['pages']['edit'], array( 'WPI_UI', 'pre_load_edit_page' ));

    //* Load common actions on all WPI pages */
    foreach($wpi_settings['pages'] as $page_slug) {
      add_action('load-' . $page_slug, array( 'WPI_UI', 'common_pre_header'));
      //** WP 3.3 fix. - korotkov@ud */
      if ( version_compare($wp_version, '3.3', '>=') ) {
        add_action("load-$page_slug", array( 'WPI_UI', 'contextual_help' ));
      }
    }

    // Add Filters
    add_filter('wpi_page_loader_path', array('WPI_UI', "wpi_display_user_selection"), 0,3);
    add_filter('wpi_pre_header_invoice_page_wpi_page_manage_invoice', array('WPI_UI', "page_manage_invoice_preprocess"));
    
  }
  
  /**
   * Get capability required for this plugin's menu to be displayed to the user.
   * It's used for setting this plugin's menu Capability.
   *
   * For more capability details: http://codex.wordpress.org/Roles_and_Capabilities
   *
   * @param int/string $level. Role's level number
   * @retun string. Unique User Level's capability
   * @since 3.0
   * @author Maxim Peshkov
   */
  function get_capability_by_level($level) {
    $capability = '';
    switch ($level) {
      /* Contributor */
      case '0':
        $capability = 'edit_posts';
        break;
      /* Author */
      case '2':
        $capability = 'publish_posts';
        break;
      /* Editor */
      case '5':
        $capability = 'edit_pages';
        break;
      /* Administrator */
      case '8':
      default:
        $capability = 'manage_options';
        break;
    }
    return $capability;
  }

  /**
   * Displays a dropdown of predefined items.
   *
   * @since 3.0
   */
    function get_predefined_item_dropdown($args = '') {
     global $wpi_settings;

     if(empty($wpi_settings['predefined_services'])) {
      return;
     }


    //** Extract passed args and load defaults */
    extract(wp_parse_args($args,  array(
      'input_name' => 'wpi[itemized_item][]',
      'input_class' => 'wpi_itemized_item',
      'input_id' => 'wpi_itemized_item',
      'input_style' => ''
    )), EXTR_SKIP);


    $return[] = "<select name='{$input_name}'  class='{$input_class}'  id='{$input_id}' style='{$input_style}' >";
    $return[] = '<option value=""></option>';

    foreach($wpi_settings['predefined_services'] as $itemized_item) {


      if(empty($itemized_item['name'])) {
        $empty_rows[] = true;
        continue;
      }

      $return[] = "<option value='". esc_attr($itemized_item['name']) ."' tax='{$itemized_item['tax']}' price='{$itemized_item['price']}'>{$itemized_item['name']}</option>";
    }
    $return[] = '</select>';


    if(count($empty_rows) == count($wpi_settings['predefined_services'])) {
      return false;
    }

     return implode('', $return);
    }

    /**
   * Displays a field for user selection, includes user array in json format, and the jQuery autocomplete() function.
   *
   *
   * @since 3.0
   */
    function draw_user_auto_complete_field($args = '') {
      global $wpi_settings, $wpdb, $wp_scripts;

      //** Check if autocomplete scrip is loaded, and load it inline */
      if(!wp_script_is('jquery.autocomplete')) { ?>
        <script type='text/javascript' src='<?php echo WPI_URL . "/core/js/jquery.autocomplete.pack.js"; ?>'></script>
      <?php }

      //** Extract passed args and load defaults */
      extract(wp_parse_args($args,  array(
        'input_name' => 'wpi[new_invoice][user_email]',
        'input_class' => 'nput_field',
        'input_id' => 'wp_invoice_userlookup',
        'input_style' => ''
      )), EXTR_SKIP);

      //** Get array of users */
      $user_array = WPI_Functions::build_user_array();

      //** Create string of users to use for autocompletion script */
      $user_array_js_string = '';
      foreach ($user_array as $key => $user) {
        if (empty($user['user_email'])) break;
        $user_array_js_string .= "{name:'{$user['display_name']}',email:'{$user['user_email']}',ID:'{$user['ID']}'}";
        $user_array_js_string .= ( $key != end(array_keys($user_array)) ? ", " : "");
      } ?>
<script type="text/javascript">
var wp_invoice_users = [<?php echo $user_array_js_string; ?>];
jQuery(document).ready(function() {
jQuery("#<?php echo $input_id; ?>").focus();
jQuery("#<?php echo $input_id; ?>").autocomplete(wp_invoice_users, {
  minChars: 0,
  width: 500,
  scrollHeight: 500,
  matchContains: true,
  autoFill: false,
  formatItem: function(row, i, max) {
    return row.name +  " (" + row.email + ")";
  },
  formatMatch: function(row, i, max) {
    return row.name + " " + row.email;
  },
  formatResult: function(row) {
    return row.email;
  }
});
});
</script>
<input name="<?php echo $input_name; ?>" class="<?php echo $input_class; ?>" id="<?php echo $input_id; ?>"  style="<?php echo $input_style; ?>" />
<?php

    }



/**
   * Common pre-header loader function for all WPI pages added in admin_menu()
   *
   * All back-end pages call this function, which then determines that UI to load below the headers.
   *
   * @since 3.0
   */
   function common_pre_header() {
      global  $current_screen;

      $browser = WPI_Functions::browser();
      $screen_id = $current_screen->id;

      if(!$screen_id){
        return;
      }

      //* Load Global Script and CSS Files */
      if ( file_exists( WPI_Path . '/core/css/jquery-ui-1.7.1.custom.css') ) {
        wp_register_style('wpi-custom-jquery-ui', WPI_URL . '/core/css/jquery-ui-1.7.1.custom.css');
      }

      if ( file_exists( WPI_Path . '/core/css/wpi-admin.css') ) {
        wp_register_style('wpi-admin-css', WPI_URL . '/core/css/wpi-admin.css', array(), WP_INVOICE_VERSION_NUM);
      }

      //* Load Page Conditional Script and CSS Files if they exist*/
      if ( file_exists( WPI_Path . "/core/css/{$screen_id}.css") ) {
          wp_register_style('wpi-this-page-css', WPI_URL . "/core/css/{$screen_id}.css", array('wpi-admin-css'), WP_INVOICE_VERSION_NUM);
      }

      //* Load IE 7 fix styles */
      if ( file_exists( WPI_Path . "/core/css/ie7.css") && $browser['name'] == 'ie' && $browser['version'] == 7 ) {
          wp_register_style('wpi-ie7', WPI_URL . "/core/css/ie7.css", array('wpi-admin-css'), WP_INVOICE_VERSION_NUM);
      }

      //* Load Page Conditional Script and CSS Files if they exist*/
      if ( file_exists( WPI_Path . "/core/js/{$screen_id}.js") ) {
          wp_register_script('wpi-this-page-js', WPI_URL . "/core/js/{$screen_id}.js", array('wp-invoice-events'), WP_INVOICE_VERSION_NUM);
      }

      //* Load Conditional Metabox Files */
      if ( file_exists( WPI_Path . "/core/ui/metabox/{$screen_id}.php") ) {
       include_once WPI_Path . "/core/ui/metabox/{$screen_id}.php";
      }



   }




  /**
   * Used for loading back-end UI
   * All back-end pages call this function, which then determines that UI to load below the headers.
   * @since 3.0
  */
  function page_loader() {
    global $screen_layout_columns, $current_screen, $wpdb, $crm_messages, $user_ID, $this_invoice, $wpi_settings, $wpi;

    $screen_id = $current_screen->id;

    /**
     * If plugin just installed
     */
    if ( $wpi_settings['first_time_setup_ran'] == 'false' ) {
      $file_path = apply_filters('wpi_page_loader_path', WPI_Path . "/core/ui/first_time_setup.php", 'first_time_setup', WPI_Path . "/core/ui/");
    } else {
      /**
       * Check if 'web_invoice_page' exists
       * and show warning message if not.
       * and also check that the web_invoice_page is a real page
       */
      if ( empty( $wpi_settings['web_invoice_page'] ) ) {
        echo '<div class="error"><p>'. sprintf(__('Invoice page not selected. Visit <a href="%s">settings page</a> to configure.', WPI), 'admin.php?page=wpi_page_settings').'</p></div>';
      } else {
        if(!$wpdb->get_var("SELECT post_name FROM {$wpdb->posts} WHERE ID = {$wpi_settings['web_invoice_page'] }")) {
        echo '<div class="error"><p>'. sprintf(__('Selected invoice page does not exist. Visit <a href="%s">settings page</a> to configure.', WPI), 'admin.php?page=wpi_page_settings').'</p></div>';
        }
      }
      $file_path = apply_filters('wpi_page_loader_path', WPI_Path . "/core/ui/{$current_screen->base}.php", $current_screen->base, WPI_Path . "/core/ui/");
    }

    if(file_exists($file_path))
      include $file_path;
    else
      echo "<div class='wrap'><h2>".__('Error', WPI)."</h2><p>".__('Template not found:', WPI) . $file_path. "</p></div>";
  }

  /**
   * Hook.
   * Check Request before Manage Page will be loaded.
   *
   * @since 3.0
  */
  function pre_load_edit_page () {
    global $wpi_settings;

    if ( !empty($_REQUEST['wpi']) && !empty( $_REQUEST['wpi']['existing_invoice'] ) ) {
      $id = (int)$_REQUEST['wpi']['existing_invoice']['invoice_id'];
      if(!empty($id) && !empty($_REQUEST['action'])) {
        self::process_invoice_actions($_REQUEST['action'], $id);
      }
    }
  }

  /**
   * Hook.
   * Check Request before Main (Overview) Page will be loaded.
   *
   * @since 3.0
  */
  function pre_load_main_page () {
    global $wpi_settings, $wpdb;

    
    /* Set default overview post status as 'active' */
    /* @TODO: This functionality is depriciated. Should be removed. Maxim Peshkov
    if(empty($_REQUEST['post_status'])) {

      //* Determine if invoices with 'active' statuses exist /
      $ids = $wpdb->get_col("
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_type = 'wpi_object'
        AND post_status = 'active'
      ");

      if(!empty($ids)) {
        //* Get Referer /
        $sendback = wp_get_referer();
        //* Determine if reffer is not main page, we set it ( anyway, will do redirect to main page ) /
        if(!strpos($sendback, $wpi_settings['links']['overview_page'])){
          $sendback = $wpi_settings['links']['overview_page'];
        }
        wp_redirect( add_query_arg( array('post_status' => 'active'), $sendback ) );
        die();
      }
    }
    */
    
    /* Process Bulk Actions */
    if(!empty($_REQUEST['post']) && !empty($_REQUEST['action'])) {
      self::process_invoice_actions($_REQUEST['action'], $_REQUEST['post']);
    } else if (!empty($_REQUEST['delete_all']) && $_REQUEST['post_status'] == 'trash') {
      /* Get all trashed invoices */
      $ids = $wpdb->get_col("
        SELECT `ID`
        FROM `{$wpdb->posts}`
        WHERE `post_type` = 'wpi_object'
        AND `post_status` = 'trash'
      ");

      /* Determine if trashed invoices exist we remove them */
      if(!empty($ids)) {
        self::process_invoice_actions('delete', $ids);
      }
    }

    /* Action Messages */
    if(!empty($_REQUEST['invoice_id'])) {
      $invoice_ids = str_replace(',', ', ', $_REQUEST['invoice_id']);
      // Add Messages
      if(isset($_REQUEST['trashed'])) {
        WPI_Functions::add_message(sprintf(__('"Invoice(s) %s trashed."', WPI), $invoice_ids));
      } elseif(isset($_REQUEST['untrashed'])) {
        WPI_Functions::add_message(sprintf(__('"Invoice(s) %s untrashed."', WPI), $invoice_ids));
      } elseif(isset($_REQUEST['deleted'])) {
        WPI_Functions::add_message(sprintf(__('"Invoice(s) %s deleted."', WPI), $invoice_ids));
      } elseif(isset($_REQUEST['unarchived'])) {
        WPI_Functions::add_message(sprintf(__('"Invoice(s) %s unarchived."', WPI), $invoice_ids));
      } elseif(isset($_REQUEST['archived'])) {
        WPI_Functions::add_message(sprintf(__('"Invoice(s) %s archived."', WPI), $invoice_ids));
      }
    }
  }

  /**
   * Process actions from Main Page (List of invoices)
   *
   * @since 3.0
   */
  function process_invoice_actions($action, $ids) {
    global $wpi_settings;

    // Set status
    switch($action) {
      case 'trash':
        $status = 'trashed';
        break;
      case 'delete':
        $status = 'deleted';
        break;
      case 'untrash':
        $status = 'untrashed';
        break;
      case 'unarchive':
        $status = 'un-archived';
        break;
      case 'archive':
        $status = 'archived';
        break;
    }

    if(!is_array($ids)) {
      $ids = explode(',', $ids);
    }

    // Process action
    $invoice_ids = array();
    foreach ((array)$ids as $ID) {
      // Perfom action
      $this_invoice = new WPI_Invoice();
      $this_invoice->load_invoice("id={$ID}");
      $invoice_id = $this_invoice->data['invoice_id'];
      switch($action) {
        case 'trash':
          if($this_invoice->trash()) {
            $invoice_ids[] = $invoice_id;
          }
          break;
        case 'delete':
          if($this_invoice->delete()) {
            $invoice_ids[] = $invoice_id;
          }
          break;
        case 'untrash':
          if($this_invoice->untrash()) {
            $invoice_ids[] = $invoice_id;
          }
          break;
        case 'unarchive':
          if($this_invoice->unarchive()) {
            $invoice_ids[] = $invoice_id;
          }
          break;
        case 'archive':
          if($this_invoice->archive()) {
            $invoice_ids[] = $invoice_id;
          }
          break;
      }
    }
    if(!empty($status) && $status) {
      // Get Referer and clean it up
      $sendback = wp_get_referer();
      $sendback = remove_query_arg( array('trashed', 'untrashed', 'deleted', 'invoice_id, unarchived, archived'), $sendback );
      // Determine if reffer is not main page, we set it ( anyway, will do redirect to main page )
      if(!strpos($sendback, $wpi_settings['links']['overview_page'])){
        $sendback = $wpi_settings['links']['overview_page'];
      }
      wp_redirect( add_query_arg( array($status => 1, 'invoice_id' => implode(',',$invoice_ids)), $sendback ) );
      die();
    }
  }

  /**
   * Can enqueue scripts on specific pages, and print content into head
   *
   * @uses $current_screen global variable
   * @since 3.0
   *
   */
  function admin_enqueue_scripts() {
    global $current_screen, $wp_properties;

    /** Include on all pages */

    /** Includes page-specific JS if it exists */
    wp_enqueue_script('wpi-this-page-js');

    /** Load scripts on specific pages */

    switch($current_screen->id)  {

      /** Reports page */
      case 'invoice_page_wpi_page_reports':
        wp_enqueue_script('jsapi');
        wp_enqueue_script('wp-invoice-events');
        wp_enqueue_script('wp-invoice-functions');
      break;

      case 'invoice_page_wpi_page_settings':
      case 'toplevel_page_wpi_main':
        wp_enqueue_script('jquery.ui.custom.wp-invoice');
        wp_enqueue_script('wp-invoice-functions');
        wp_enqueue_script('jquery.cookie');
        wp_enqueue_script('jquery.autocomplete');
        wp_enqueue_script('wp-invoice-events');
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery.formatCurrency');
        wp_enqueue_script('jquery-data-tables');
        wp_enqueue_style('wpi-jquery-data-tables');
      break;


      case 'invoice_page_wpi_page_manage_invoice':
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery.ui.custom.wp-invoice');
        wp_enqueue_script('wp-invoice-functions');
        wp_enqueue_script('wp-invoice-events');
        wp_enqueue_script('jquery.autocomplete');
        wp_enqueue_script('jquery.formatCurrency');
        wp_enqueue_script('jquery.delegate');
        wp_enqueue_script('jquery.field');
        wp_enqueue_script('jquery.bind');
        wp_enqueue_script('jquery.form');
        wp_enqueue_script('jquery.cookie');

        /** Add scripts and styles for Tiny MCE Editor (default WP Editor) */
        wp_enqueue_script(array('editor', 'thickbox', 'media-upload'));
        wp_enqueue_style('thickbox');
        
        do_action('wpi_ui_admin_scripts_invoice_editor');
        
      break;
    }
    
    
    
  }

  /**
   * Add or remove taxonomy columns
   * @since 3.0
   */
  function overview_columns($columns) {

    $overview_columns = apply_filters('wpi_overview_columns',  array(
      'cb' => '',
      'post_title' => __('Title', WPI),
      'total' => __('Total Collected', WPI),
      'user_email' => __('Recipient', WPI),
      'post_modified' => __('Date', WPI),
      'post_status' => __('Status', WPI),
      'type' => __('Type', WPI),
      'invoice_id' => __('Invoice ID', WPI)
    ));

    /* We need to grab the columns from the class itself, so we instantiate a new temp object */
    foreach($overview_columns as $column => $title) {
      $columns[$column] = $title;
    }

    return $columns;
  }

  /**
   * Displays users selection screen when viewing the edit invoice page, and no invoice ID is passed
   *
   * @todo Better check to see if import has already been done
   * @since 3.0
   */
  function wpi_display_user_selection($file_path, $screen, $path) {
    global $wpdb;

    if($screen != 'invoice_page_wpi_page_manage_invoice')
      return $file_path;

    if(empty($_REQUEST['wpi']))
      return $path . '/user_selection_form.php';

    return $file_path;
  }

    /**
     * Main invoice page.  Displayes either the first_time_setup, or a list of invoices
     *
     * DOTO: Seems deprecated. - Anton Korotkov
     *
     */
    /*function page_overview() {
        global $wpi_settings;
        WPI_Functions::check_tables();
        // determine if user has compelted setup
        if ($wpi_settings['first_time_setup_ran'] == 'false') {
            include($wpi_settings['admin']['ui_path'] . '/first_time_setup.php');
        } else {
            include($wpi_settings['admin']['ui_path'] . '/overview.php');
        }
    }*/

    /**
      Page for adding/editing invoices.  When first opened, displays the user selection form
      Also checks that all proper tables and settings are stup.
     */
    function page_manage_invoice() {
        global $wpi_settings;
        WPI_Functions::check_tables();
        WPI_Functions::check_settings();
        if (isset($_REQUEST['wpi']['new_invoice']) || isset($_REQUEST['wpi']['existing_invoice'])) {
            include($wpi_settings['admin']['ui_path'] . '/manage_invoice.php');
        } else {
            include($wpi_settings['admin']['ui_path'] . '/blocks/postbox_user_selection_form.php');
        }
    }

  /**
   * Does our preprocessing for the manage invoice page, adds our meta boxes, and checks invoice data
   * @since 3.0
  */
  function page_manage_invoice_preprocess($screen_id){
    global $wpi_settings, $this_invoice, $wpdb;
    
    //add_screen_option( 'screen_option', array('label' => "Default Screen Option", 'default' => 7, 'option' => 'screen_option') );
    //add_contextual_help($screen_id, 'test');

    // Check if invoice_id already exists
    if ( !empty( $_REQUEST['wpi'] ) ) {
      if ( !empty( $_REQUEST['wpi']['new_invoice'] ) ) {
        if( wpi_check_invoice($_REQUEST['wpi']['new_invoice']['invoice_id']) )
        {
          $invoice_id_exists = true;
        }
      }
      if ( !empty( $_REQUEST['wpi']['existing_invoice'] ) ) {
        if( wpi_check_invoice($_REQUEST['wpi']['existing_invoice']['invoice_id']) )
        {
          $invoice_id_exists = true;
        }
      }

    }

    // Select status of invoice from DB
    $status = $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = '{$_REQUEST['wpi']['existing_invoice']['invoice_id']}' AND meta_key = 'status'");
    
    // New Invoice
    if(isset($_REQUEST['wpi']['new_invoice']) && empty($invoice_id_exists)) {
      $this_invoice = new WPI_Invoice();
      $this_invoice->create_new_invoice("invoice_id={$_REQUEST['wpi']['new_invoice']['invoice_id']}");

      // If we are copying from a template
      if(!empty($_REQUEST['wpi']['new_invoice']['template_copy'])) {
        $this_invoice->load_template("id={$_REQUEST['wpi']['new_invoice']['template_copy']}");
      }

      // Set user and determine type
      $this_invoice->load_user("email={$_REQUEST['wpi']['new_invoice']['user_email']}");
 
      // Add custom data if user doesn't exist.
      if(empty($this_invoice->data['user_data'])) {
        $this_invoice->data['user_data'] = array('user_email' => $_REQUEST['wpi']['new_invoice']['user_email']);
      }

      $new_invoice = true;

      // Enter in GET values
      if(isset($_GET['prefill']['subject'])) {
        $this_invoice->data['subject'] = $_GET['prefill']['subject'];
      }

      if(!empty($_GET['prefill']['is_quote']) && $_GET['prefill']['is_quote'] == 'true') {
        $this_invoice->data['is_quote'] = true;
        $this_invoice->data['status'] = "quote";
      }
    } else if(!empty($invoice_id_exists)) {
      // Existing Invoice
      $this_invoice = new WPI_Invoice();

      if(isset($_REQUEST['wpi']['existing_invoice']['invoice_id'])) {
        $ID = $_REQUEST['wpi']['existing_invoice']['invoice_id'];
      } else if (isset($_REQUEST['wpi']['new_invoice']['invoice_id'])) {
        $ID = $_REQUEST['wpi']['new_invoice']['invoice_id'];
      }

      $this_invoice->load_invoice("id={$ID}");

    }
    

    add_meta_box('postbox_payment_methods', __('Payment Settings',WPI), 'postbox_payment_methods', $screen_id, 'normal', 'high');
    
    //  add_meta_box('postbox_settings',  __('Settings',WPI), 'postbox_settings', 'admin_page_wpi_invoice_edit', 'side', 'low');
    if ( $this_invoice->data['type'] == 'single_payment' ) {
      add_meta_box('postbox_overview', __('Overview',WPI), 'postbox_overview', $screen_id, 'side', 'high');
    } else {
      add_meta_box('postbox_publish', __('Publish',WPI), 'postbox_publish', $screen_id, 'side', 'high');
    }
    //add_meta_box('recurring_billing_box', __('Publish',WPI), 'recurring_billing_box', 'admin_page_wpi_invoice_edit', 'middle', 'low');
    add_meta_box('postbox_user_existing', __('User Information',WPI), 'postbox_user_existing', $screen_id, 'side', 'low');
  }

    /**
      Settings page
     */
    function page_settings() {
        global $wpdb, $wpi_settings;
        WPI_Functions::check_tables();
        include($wpi_settings['admin']['ui_path'] . '/settings_page.php');
    }

    // Displays messages. Can be outputted anywhere, WP JavaScript automatically moves it to the top of the page
    function show_message($content, $type="updated fade") {
      if ($content)
        echo "<div id=\"message\" class='$type' ><p>" . $content . "</p></div>";
    }

    // Displays error messages. Can be outputted anyways, WP JavaScript automatically moves it to the top of the page
    function error_message($message, $return = false) {
      $content = "<div id=\"message\" class='error' ><p>$message</p></div>";
      if ($message != "") {
        if ($return)
          return $content;
        echo $content;
      }
    }

    // Displays the extra profile input fields (such as billing address) in the WP User
    // Called by 'edit_user_profile' and 'show_user_profile'
    function display_user_profile_fields() {
      global $wpdb, $user_id, $wpi_settings;
      $profileuser = get_user_to_edit($user_id);

      include($wpi_settings['admin']['ui_path'] . '/profile_page_content.php');
    }


  /**
     *  Mostly for printing out pre-loaded styles.
     *
     * @since 3.0
     */
    function admin_print_styles() {
      global $wpi_settings, $current_screen;

      wp_enqueue_style( 'wpi-custom-jquery-ui');
      wp_enqueue_style( 'wpi-admin-css');

      //** Prints styles specific for this page */
      wp_enqueue_style('wpi-this-page-css');
      wp_enqueue_style('wpi-ie7');

    }


  /**
   * Legacy contextual help function for handling contextual help for different pages.
   *
   *
   * @since 3.0
   *
   */
    function contextual_help_old() {
        global $wpi_settings, $page_hook;

        switch ($page_hook) {

          // Invoice editing page
          case $wpi_settings['pages']['edit']:

          $return = "<h5>".__('Creating New Invoice', WPI)."</h5>";
          $return .= '<div class="metabox-prefs">';
          $return .= __("Begin typing the recipient's email into the input box, or double-click to view list of possible options.  For new prospects, type in a new email address.", WPI);
          $return .= '</div>';
          return $return;

        break;

        case $wpi_settings['pages']['main']:

          $help[] = "<h5>".__('Support', WPI)."</h5>";
          $help[] = "<p>".__('Please visit <a href="http://usabilitydynamics.com/products/wp-invoice/forum/">WP-Invoice Support Forum</a> to ask questions regarding the plugin.', WPI)."</p>";
          $help[] = "<p>".__('To suggest ideas please visit the <a href="http://feedback.twincitiestech.com/forums/9692-wp-invoice">WP-Invoice Feedback site</a>.', WPI)."</p>";

          $return = implode('', $help);
        break;

        case $wpi_settings['pages']['settings']:

          $help[] = "<h5>".__('Main & Business Process', WPI)."</h5>";
          $help[] = "<p>".__('<b>Business Address</b> - This will display on the invoice page when printed for clients\' records.', WPI)."</p>";

          $help[] = "<h5>".__('E-Mail Templates', WPI)."</h5>";
          $help[] = "<p>".__('You can create as many e-mailed templates as needed, they can later be used to quickly create invoice notifications and reminders, and being sent directly from an invoice page. The following variables can be used within the Subject or the Content of the e-mail templates:', WPI)."</p>";

          $email_vars['invoice_id'] = __('Invoice ID', WPI);
          $email_vars['link'] = __('URL of invoice', WPI);
          $email_vars['recipient'] = __('Name or business name of receipient', WPI);
          $email_vars['amount'] = __('Due BalanceID', WPI);
          $email_vars['subject'] = __('Invoice title', WPI);
          $email_vars['description'] = __('Description of Invoice', WPI);
          $email_vars['business_name'] = __('Business Name', WPI);
          $email_vars['business_email'] = __('Business Email Address', WPI);
          $email_vars['creator_name'] = __('Name of user who has created invoice', WPI);
          $email_vars['creator_email'] = __('Email of user who has created invoice', WPI);
          $email_vars['due_date'] = __('Invoice due date (if presented)', WPI);

          $email_vars = apply_filters('wpi_email_template_vars', $email_vars);

          $help[] = "<p>".__('You can create as many e-mailed templates as needed, they can later be used to quickly create invoice notifications and reminders, and being sent directly from an invoice page..', WPI)."</p>";

          if(is_array($email_vars)) {
            $help[] = '<ul>';
            foreach($email_vars as $var => $title) {
              $help[] =  '<li><b>%' . $var . '%</b> - ' . $title . '</li>';
            }
            $help[] = '</ul>';
          }
          
          $help = apply_filters( 'wpi_contextual_help', $help );

          $return = implode('', $help);

        break;

        default: break;

      }

      return $return;

    }


  /**
   * Main function for handling contextual help for different pages.
   *
   * @todo Find a better way of including advanced "Screen Options" configuration for invoice edit pages
   *
   * @since 3.0
   *
   */
    function contextual_help() {


        global $wpi_settings, $page_hook;
        
        $screen = get_current_screen();

        switch ($page_hook) {

          //** Invoice editing page */
          case $wpi_settings['pages']['edit']:

          $return = "<p>".__("Begin typing the recipient's email into the input box, or double-click to view list of possible options.", WPI)."</p>";
          $return .= "<p>".__("For new prospects, type in a new email address.", WPI)."</p>";
          
          $screen->add_help_tab( array(
            'id'	=> $page_hook.'_my_help_tab',
            'title'	=> __('Creating New Invoice', 'wpi'),
            'content'	=> $return,
          ) );

        break;

        case $wpi_settings['pages']['main']:

          $help[] = "<p>".__('Please visit <a href="http://usabilitydynamics.com/products/wp-invoice/forum/">WP-Invoice Support Forum</a> to ask questions regarding the plugin.', WPI)."</p>";
          $help[] = "<p>".__('To suggest ideas please visit the <a href="http://feedback.twincitiestech.com/forums/9692-wp-invoice">WP-Invoice Feedback site</a>.', WPI)."</p>";

          $return = implode('', $help);
          
          $screen->add_help_tab( array(
            'id'	=> $page_hook.'_my_help_tab',
            'title'	=> __('Support', WPI),
            'content'	=> $return,
          ) );
          
        break;

        case $wpi_settings['pages']['settings']:

          $help = array();
          $help[] = "<p>".__('<b>Business Address</b> - This will display on the invoice page when printed for clients\' records.', WPI)."</p>";
      
          $screen->add_help_tab( array(
            'id'	=> $page_hook.'_my_help_tab_business_process',
            'title'	=> __('Main & Business Process', WPI),
            'content'	=> implode('', $help)
          ) );

          $help = array();
          $help[] = "<p>".__('You can create as many e-mailed templates as needed, they can later be used to quickly create invoice notifications and reminders, and being sent directly from an invoice page. The following variables can be used within the Subject or the Content of the e-mail templates:', WPI)."</p>";

          $email_vars['invoice_id'] = __('Invoice ID', WPI);
          $email_vars['link'] = __('URL of invoice', WPI);
          $email_vars['recipient'] = __('Name or business name of receipient', WPI);
          $email_vars['amount'] = __('Due BalanceID', WPI);
          $email_vars['subject'] = __('Invoice title', WPI);
          $email_vars['description'] = __('Description of Invoice', WPI);
          $email_vars['business_name'] = __('Business Name', WPI);
          $email_vars['business_email'] = __('Business Email Address', WPI);
          $email_vars['creator_name'] = __('Name of user who has created invoice', WPI);
          $email_vars['creator_email'] = __('Email of user who has created invoice', WPI);
          $email_vars['due_date'] = __('Invoice due date (if presented)', WPI);

          $email_vars = apply_filters('wpi_email_template_vars', $email_vars);

          $help[] = "<p>".__('You can create as many e-mailed templates as needed, they can later be used to quickly create invoice notifications and reminders, and being sent directly from an invoice page..', WPI)."</p>";

          if(is_array($email_vars)) {
            $help[] = '<ul>';
            foreach($email_vars as $var => $title) {
              $help[] =  '<li><b>%' . $var . '%</b> - ' . $title . '</li>';
            }
            $help[] = '</ul>';
          }
          
          $screen->add_help_tab( array(
            'id'	=> $page_hook.'_my_help_tab_email_templates',
            'title'	=> __('E-Mail Templates', WPI),
            'content'	=> implode('', $help),
          ) );
          
          do_action("wpi_contextual_help_{$wpi_settings['pages']['settings']}");

        break;

        default: break;

      }

      return $return;

    }


  /**
   * Can overwite page title (heading)
   */
  function wp_title($title, $sep, $seplocation) {
    global $invoice_id, $wpdb;

    $post_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'invoice_id' AND meta_value = '{$invoice_id}'");
    if(empty($post_id)) {
      return $title;
    }
    $post_title = $wpdb->get_var("SELECT post_title FROM {$wpdb->posts} WHERE ID = '{$post_id}'");
    if(empty($post_title)) {
      return $title;
    }
    return $post_title.' '.$sep.' ';
  }

  /**
   * Can overwite page title (heading)
   */
  function the_title($title = '', $post_id = '') {
    global $wpi_settings, $invoice_id, $wpdb;
    if ($post_id == $wpi_settings['web_invoice_page']) {
      if ($wpi_settings['hide_page_title'] == 'true') {
        return;
      }
      $post_id = $wpdb->get_var("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'invoice_id' AND meta_value = '{$invoice_id}'");
      if(empty($post_id)) {
        return $title;
      }
      $post_title = $wpdb->get_var("SELECT post_title FROM {$wpdb->posts} WHERE ID = '{$post_id}'");
      if(empty($post_title)) {
        return $title;
      }
      return $post_title;
    }
    return $title;
  }

  /**
     * Renders invoice in the content.
     *
     *  Invoice object already loaded into $wpi_invoice_object at template_redirect()
     *
     */
    function the_content($content) {
        global $post, $invoice, $invoice_id, $wpi_settings, $wpi_invoice_object;

        $invoice = $wpi_invoice_object->data;

        // Mark invoice as viewed if not by admin
        if (!current_user_can('manage_options')) {
          
          // Prevent duplicating of 'viewed' item.
          // 1 time per $hours
          $hours = 12;
          
          $viewed_today_from_cur_ip = false;
          
          foreach ( $invoice['log'] as $key => $value ) {
            if ( $value['user_id'] == '0' ) {
              if ( strstr( strtolower( $value['text'] ), "viewed by {$_SERVER['REMOTE_ADDR']}" ) ) {
                $time_dif = time() - $value['time'];
                if ( $time_dif < $hours*60*60 ) {
                  $viewed_today_from_cur_ip = true;
                }
              }
            }
          }
          
          if ( !$viewed_today_from_cur_ip ) {
            $wpi_invoice_object->add_entry("note=Viewed by {$_SERVER['REMOTE_ADDR']}");
          }
        }

        //WPI_Functions::qc($invoice);
        // Include our template functions
        include_once('wpi_template_functions.php');

        ob_start();
        
        if($invoice['post_status'] == 'paid') {
          
          if (WPI_Functions::wpi_use_custom_template('receipt_page.php')) {
              include($wpi_settings['frontend_template_path'] . 'receipt_page.php');
          } else {
              include($wpi_settings['default_template_path'] . 'receipt_page.php');
          }          
          
        } else {
          
          if (WPI_Functions::wpi_use_custom_template('invoice_page.php')) {
              include($wpi_settings['frontend_template_path'] . 'invoice_page.php');
          } else {
              include($wpi_settings['default_template_path'] . 'invoice_page.php');
          }
          
        }

        $result .= ob_get_contents();
        ob_end_clean();

        switch ($wpi_settings['where_to_display']) {
            case 'overwrite':
                return $result;
                break;
            case 'below_content':
                return $content . $result;
                break;
            case 'above_content':
                return $result . $content;
                break;
            default:
                return $content;
                break;
        }
    }

    function the_content_shortcode() {
      global $post, $invoice, $invoice_id, $wpi_settings, $wpi_invoice_object;

      $invoice = $wpi_invoice_object->data;

      include_once('wpi_template_functions.php');

      ob_start();
      if (WPI_Functions::wpi_use_custom_template('invoice_page.php')) {
          include($wpi_settings['frontend_template_path'] . 'invoice_page.php');
      } else {
          include($wpi_settings['default_template_path'] . 'invoice_page.php');
      }

      $result .= ob_get_contents();
      ob_end_clean();
      return $result;
    }

  /**
   * Validation is already passed, this is the wp_head filter
   * It needs a lot of work
   *
   * @TODO: Does it need at all? Old functionality? Should be revised. Maxim Peshkov.
   */
  function frontend_header() {
    global $wpi_settings;
    ?>
    <script type="text/javascript">
      var site_url = '<?php echo WPI_Functions::current_page(); ?>';
      <?php /* var ajax_image = '<?php echo $frontend_path; ?>/core/images/processing-ajax.gif'; */ ?>
    </script>
    <meta name="robots" content="noindex, nofollow" />
    <?php
  }

    /**
      Shorthand function for drawing input fields
     */
    function input($args = '') {
        $defaults = array('id' => '', 'class_from_name' => '', 'title' => '', 'class'=>'', 'name' => '', 'group' => '', 'special' => '', 'value' => '', 'type' => '', 'hidden' => false, 'style' => false, 'readonly' => false, 'label' => false);
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        // if [ character is present, we do not use the name in class and id field

        $return = '';
        if (!strpos("$name", '[')) {
            $id = $name;
            $class_from_name = $name;
        }
        if ($label)
            $return .= "<label for='$id'>";
        $return .= "<input " . ($type ? "type=\"$type\" " : '') . " " . ($style ? "style=\"$style\" " : '') . " id=\"$id\" class=\"" . ($type ? "" : "input_field") . " $class_from_name $class " . ($hidden ? " hidden " : '') . "" . ($group ? "group_$group" : '') . " \"    name=\"" . ($group ? $group . "[" . $name . "]" : $name) . "\"  value=\"" . stripslashes($value) . "\"  title=\"$title\" $special " . ($type == 'forget' ? " autocomplete='off'" : '') . " " . ($readonly ? " readonly=\"readonly\" " : "") . " />";
        if ($label)
            $return .= "$label </label>";
        return $return;
    }

    /**
      Shorthand function for drawing checkbox fields
     */
    function checkbox($args = '', $checked = false) {
        $defaults = array('name' => '', 'id' => false, 'class' => false, 'group' => '', 'special' => '', 'value' => '', 'label' => false, 'maxlength' => false);
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);

        $return = '';
        // Get rid of all brackets
        if (strpos("$name", '[') || strpos("$name", ']')) {
            $replace_variables = array('][', ']', '[');
            $class_from_name = $name;
            $class_from_name = "wpi_" . str_replace($replace_variables, '_', $class_from_name);
        } else {
            $class_from_name = "wpi_" . $name;
        }
        // Setup Group
        $group_string = '';
        if ($group) {
            if (strpos($group, '|')) {
                $group_array = explode("|", $group);
                $count = 0;
                foreach ($group_array as $group_member) {
                    $count++;
                    if ($count == 1) {
                        $group_string .= "$group_member";
                    } else {
                        $group_string .= "[$group_member]";
                    }
                }
            } else {
                $group_string = "$group";
            }
        }
        // Use $checked to determine if we should check the box
        $checked = strtolower($checked);
        if ($checked == 'yes' ||
            $checked == 'on' ||
            $checked == 'true' ||
            ($checked == true && $checked != 'false' && $checked != '0'))
        {
          $checked = true;
        } else {
          $checked = false;
        }
        $id = ($id ? $id : $class_from_name);
        $insert_id = ($id ? " id='$id' " : " id='$class_from_name' ");
        $insert_name = ($group_string ? " name='" . $group_string . "[$name]' " : " name='$name' ");
        $insert_checked = ($checked ? " checked='checked' " : " ");
        $insert_value = " value=\"$value\" ";
        $insert_class = " class='$class_from_name $class wpi_checkbox' ";
        $insert_maxlength = ($maxlength ? " maxlength='$maxlength' " : " ");
        // Determine oppositve value
        switch ($value) {
            case 'yes':
                $opposite_value = 'no';
                break;
            case 'true':
                $opposite_value = 'false';
                break;
        }
        // Print label if one is set
        if ($label)
            $return .= "<label for='$id'>";
        // Print hidden checkbox
        $return .= "<input type='hidden' value='$opposite_value' $insert_name />";
        // Print checkbox
        $return .= "<input type='checkbox' $insert_name $insert_id $insert_class $insert_checked $insert_maxlength  $insert_value $special />";
        if ($label)
            $return .= " $label</label>";
        return $return;
    }

    function textarea($args = '') {
        $defaults = array('title' => '', 'class' => '', 'name' => '', 'group' => '', 'special' => '', 'value' => '', 'type' => '');
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        return "<textarea id='$name' class='input_field $name $class " . ($group ? "group_$group" : '') . "'  name='" . ($group ? $group . "[" . $name . "]" : $name) . "' title='$title' $special >" . stripslashes($value) . "</textarea>";
    }

    function select($args = '') {
        $defaults = array('id' => '', 'class' => '', 'name' => '', 'group' => '', 'special' => '', 'values' => '', 'current_value' => '');
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        global $wpi_settings;
        // Get rid of all brackets
        if (strpos("$name", '[') || strpos("$name", ']')) {
            $replace_variables = array('][', ']', '[');
            $class_from_name = $name;
            $class_from_name = "wpi_" . str_replace($replace_variables, '_', $class_from_name);
        } else {
            $class_from_name = "wpi_" . $name;
        }
        // Overwrite class_from_name if class is set
        if ($class)
            $class_from_name = $class;
        $values_array = is_serialized($values) ? unserialize($values) : $values;
        if ($values == 'yon') {
            $values_array = array("yes" => __("Yes", WPI), "no" => __("No", WPI));
        }
        if ($values == 'us_states') {
            $values_array = array( '0' => '--'.__('Select').'--' );
            $values_array = array_merge( $values_array, $wpi_settings['states'] );
        }
        if ($values == 'countries') {
            $values_array = $wpi_settings['countries'];
        }
        if ($values == 'years') {
            // Create year array
            $current_year = intval(date('y'));
            $values_array = array();
            $counter = 0;
            while ($counter < 7) {
                $values_array[$current_year] = "20" . $current_year;
                $current_year++;
                $counter++;
            }
        }
        if ($values == 'months') {
            $values_array = array("" => "", "01" => __("Jan", WPI), "02" => __("Feb", WPI), "03" => __("Mar", WPI), "04" => __("Apr", WPI), "05" => __("May", WPI), "06" => __("Jun", WPI), "07" => __("Jul", WPI), "08" => __("Aug", WPI), "09" => __("Sep", WPI), "10" => __("Oct", WPI), "11" => __("Nov", WPI), "12" => __("Dec", WPI));
        }
        $output = "<select id='" . ($id ? $id : $class_from_name) . "' name='" . ($group ? $group . "[" . $name . "]" : $name) . "' class='$class_from_name " . ($group ? "group_$group" : '') . "'>";
        
        if ( !empty( $values_array ) ) {
          foreach ($values_array as $key => $value) {
              $output .= "<option value='$key'";
              if ($key == $current_value)
                  $output .= " selected";
              $output .= ">$value</option>";
          }
        } else {
          $output .= "<option>".__('Values are empty', WPI)."</option>";
        }
        $output .= "</select>";
        return $output;
    }
    
    /**
     * Add link to user profile in CRM for user data block
     * 
     * @param int $user_id 
     * @author korotkov@ud
     */
    function crm_user_panel( $user_id ) {
    
      if(!$user_id) {
        return;
      }
      
      
      // Determine if WP CRM is installed
      if ( class_exists( 'WP_CRM_Core' ) ) {
        
        echo '<div class="wpi_crm_link"><a  class="button" target="_blank" href="'.admin_url('admin.php?page=wp_crm_add_new&user_id='.$user_id).'">'.__('Go to user\'s profile', WPI).'</a></div>';
        
      } else {
        
        echo '<div class="wpi_crm_link"><a target="_blank" href="'.admin_url('plugin-install.php?tab=search&type=term&s=WP-CRM').'">'.__('Get WP-CRM plugin to enhance user management.', WPI).'</a></div>';
        
      }
      
    }
    
    /**
     * Add additional WPI attribute option for CRM
     * 
     * @global object $wp_crm
     * @param array $args 
     * @author korotkov@ud
     */
    function wp_crm_data_structure_attributes( $args ) {
      
      global $wp_crm;
      
      $default = array(
          'slug'     => '',
          'data'     => array(),
          'row_hash' => ''
      );
      
      extract( wp_parse_args( $args, $default ), EXTR_SKIP );
      
      if ( !empty( $slug ) && !empty( $data ) && !empty( $row_hash ) ) {
        
        ?>
          <li class="wp_crm_advanced_configuration">
            <input id="<?php echo $row_hash; ?>_no_edit_wpi" value='true' type="checkbox"  <?php checked($wp_crm['data_structure']['attributes'][$slug]['wp_invoice'], 'true'); ?> name="wp_crm[data_structure][attributes][<?php echo $slug; ?>][wp_invoice]" />
            <label for="<?php echo $row_hash; ?>_no_edit_wpi" ><?php _e('WP-Invoice custom field', WPI); ?></label>
          </li>
        <?php
        
      }
      
    }
    
    /**
     * Add contextual help data when WPI and CRM installed
     * 
     * @param type $data
     * @return array 
     * @author korotkov@ud
     */
    function wp_crm_contextual_help( $data ) {
      
      $data['content'][] = __('<h3>WP-Invoice</h3>', WPI);
      $data['content'][] = __('<p>Advanced option <b>WP-Invoice custom field</b> may be used for adding custom user data fields for payments forms.</p>', WPI);
      $data['content'][] = __('<p>Works for Authorize.net payment method only for now.</p>', WPI);
      
      return $data;
    }

}

function wp_invoice_printYearDropdown($sel='') {
    $localDate = getdate();
    $minYear = $localDate["year"];
    $maxYear = $minYear + 15;
    $output = "<option value=''>--</option>";
    for ($i = $minYear; $i < $maxYear; $i++) {
        $output .= "<option value='" . substr($i, 2, 2) . "'" . ($sel == (substr($i, 2, 2)) ? ' selected' : '') .
                ">" . $i . "</option>";
    }
    return($output);
}

function wp_invoice_printMonthDropdown($sel='') {
    $output = "<option value=''>--</option>";
    $output .= "<option " . ($sel == 1 ? ' selected' : '') . " value='01'>01 - ".__('Jan', WPI)."</option>";
    $output .= "<option " . ($sel == 2 ? ' selected' : '') . "  value='02'>02 - ".__('Feb', WPI)."</option>";
    $output .= "<option " . ($sel == 3 ? ' selected' : '') . "  value='03'>03 - ".__('Mar', WPI)."</option>";
    $output .= "<option " . ($sel == 4 ? ' selected' : '') . "  value='04'>04 - ".__('Apr', WPI)."</option>";
    $output .= "<option " . ($sel == 5 ? ' selected' : '') . "  value='05'>05 - ".__('May', WPI)."</option>";
    $output .= "<option " . ($sel == 6 ? ' selected' : '') . "  value='06'>06 - ".__('Jun', WPI)."</option>";
    $output .= "<option " . ($sel == 7 ? ' selected' : '') . "  value='07'>07 - ".__('Jul', WPI)."</option>";
    $output .= "<option " . ($sel == 8 ? ' selected' : '') . "  value='08'>08 - ".__('Aug', WPI)."</option>";
    $output .= "<option " . ($sel == 9 ? ' selected' : '') . "  value='09'>09 - ".__('Sep', WPI)."</option>";
    $output .= "<option " . ($sel == 10 ? ' selected' : '') . "  value='10'>10 - ".__('Oct', WPI)."</option>";
    $output .= "<option " . ($sel == 11 ? ' selected' : '') . "  value='11'>11 - ".__('Nov', WPI)."</option>";
    $output .= "<option " . ($sel == 12 ? ' selected' : '') . "  value='12'>12 - ".__('Dec', WPI)."</option>";
    return($output);
}

function wp_invoice_format_phone($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) == 7)
        return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone);
    elseif (strlen($phone) == 10)
        return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone);
    else
        return $phone;
}
