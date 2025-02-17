<?php
/**
 * DHL Tracking
 *
 * @package     Woo DHL Tracking Form
 * @author      Mattias Nording
 * @copyright   2018 Mnording
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: Woo DHL Tracking Form
 * Plugin URI:  https://github.com/mnording/woocommerce-woo-dhl-tracking-form
 * Description: Enabling fetching tracking info from DHL Freight.
 * Version:     1.2.1
 * Author:      Mnording
 * Author URI:  https://mnording.com
 * Text Domain: woo-dhl-tracking-form
 * Domain Path: /languages
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 */
require_once 'classes/DHLWebService.php';
require_once 'classes/DHLMetaBox.php';
require_once 'classes/DHLTrackingEmail.php';
class DHLTracking {
    public function __construct()
    {
        add_shortcode('woo-dhl-tracking-form', array($this,'render_form'));
        add_action( 'wp_ajax_get_dhl_tracking', array($this,'get_dhl_tracking') );
        add_action( 'wp_ajax_nopriv_get_dhl_tracking', array($this,'get_dhl_tracking') );
        add_action('wp_enqueue_scripts',array($this,'register_dhl_scripts'));
        add_action('admin_menu', array($this,'dhl_tracking_plugin_create_menu'));
        add_action( 'admin_init', array($this,'dhl_tracking_plugin_settings') );
        add_action( 'plugins_loaded', array($this,'dhl_tracking_plugin_textdomain') );
        $this->shouldlog= get_option("should_log");
        new DHLMetaBox();
        new DHLTrackingEmail($this->shouldlog);
    }
    function dhl_tracking_plugin_textdomain() {
        load_plugin_textdomain( 'woo-dhl-tracking-form', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
    }
    public function dhl_tracking_plugin_settings() {

        $args = array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => NULL,
        );
        register_setting( 'dhl_tracking_settings-group', 'private_api' ,$args);
        register_setting( 'dhl_tracking_settings-group', 'api_password'  ,$args);
        register_setting( 'dhl_tracking_settings-group', 'api_username'  ,$args);
        register_setting( 'dhl_tracking_settings-group', 'should_log'  ,$args);
        register_setting( 'dhl_tracking_settings-group', 'add_to_tracking_page'  ,$args);
        register_setting( 'dhl_tracking_settings-group', 'tracking_page' ,$args );
        register_setting( 'dhl_tracking_settings-group', 'orderid_fallback' ,$args );

    }
    public function dhl_tracking_plugin_create_menu() {
        add_options_page('Woo DHL Tracking Settings', 'Woo DHL Tracking', 'administrator','woo-dhl-tracking-form' ,array($this,'dhl_tracking_settings_page') );

    }

    public function dhl_tracking_settings_page() {
        ?>
        <div class="wrap">
            <h1>DHL Tracking Form</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'dhl_tracking_settings-group' ); ?>
                <?php do_settings_sections( 'dhl_tracking_settings-group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e("Use Private Methods?","woo-dhl-tracking-form"); ?></th>
                        <td><input name="private_api" type="checkbox" value="1" <?php checked( '1', get_option( 'private_api' ) ); ?> /><?php _e("Yes","woo-dhl-tracking-form")?></td>
                        <td><?php _e("This requires an API Key, but also ensure that only your own consignments are returned from the API.","woo-dhl-tracking-form");?>
                            <strong><?php _e(" Recommended if you use non-unique references","woo-dhl-tracking-form")?></strong>
                            <br/>
                            <?php _e("In order to enable private methods on your account, you must email se.ecom@dhl.com or call SE ECOM 0771 345 345 and request access to The ACT Webservice and specify your myACT account. ","woo-dhl-tracking-form"); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Add to tracking page","woo-dhl-tracking-form"); ?></th>
                        <td><input name="add_to_tracking_page" type="checkbox" value="1" <?php checked( '1', get_option( 'add_to_tracking_page' ) ); ?> /><?php _e("Yes","woo-dhl-tracking-form")?></td>
                        <td><?php _e("Should the customer emails be populated with a link to the tracking page? This requires you to add the tracking-ID to the order before sending the email","woo-dhl-tracking-form");?>
                        </td>

                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Use Order ID for tracking?","woo-dhl-tracking-form"); ?></th>
                        <td><input name="orderid_fallback" type="checkbox" value="1" <?php checked( '1', get_option( 'orderid_fallback' ) ); ?> /><?php _e("Yes","woo-dhl-tracking-form")?></td>
                        <td><?php _e("Should the link use the internal Order ID for tracking instead of shipment ID ? This requires the use of private methods","woo-dhl-tracking-form");?>
                        </td>

                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Tracking page","woo-dhl-tracking-form"); ?></th>
                        <td><select name="tracking_page">
                                <option selected="selected" disabled="disabled" value=""><?php echo esc_attr( __( 'Select page' ) ); ?></option>
                                <?php
                                $selected_page = get_option( 'tracking_page' );
                                $pages = get_pages();
                                foreach ( $pages as $page ) {

                                    $option = '<option value="' . $page->ID . '" ';
                                    $option .= ( $page->ID == $selected_page ) ? 'selected="selected"' : '';
                                    $option .= '>';
                                    $option .= $page->post_title;
                                    $option .= '</option>';
                                    echo $option;
                                }
                                ?>
                            </select></td>
                        <td><?php _e("On what page have you hosted the tracking shortcode? This will be used to link the customer from order emails","woo-dhl-tracking-form"); ?></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e("Api Username","woo-dhl-tracking-form"); ?></th>
                        <td><input type="text" name="api_username" value="<?php echo esc_attr( get_option('api_username') ); ?>" /></td>
                        <td><?php _e("What is your API username. Should be the same as your login to the myACT portal","woo-dhl-tracking-form"); ?> -> <a href="https://activetracing.dhl.com/DatPublic/login.do?"><?php _e("Click here for Portal","woo-dhl-tracking-form");?></a></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e("API Password","woo-dhl-tracking-form"); ?></th>
                        <td><input type="password" name="api_password" value="<?php echo esc_attr( get_option('api_password') ); ?>" /></td>
                        <td><?php _e("What is your API password. Should be the same as your password to the myACT portal","woo-dhl-tracking-form"); ?></td>

                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e("Create Debug log?","woo-dhl-tracking-form"); ?></th>
                        <td><input name="should_log" type="checkbox" value="1" <?php checked( '1', get_option( 'should_log' ) ); ?> /><?php _e("Yes","woo-dhl-tracking-form")?></td>
                    </tr>
                </table>

                <?php submit_button(); ?>

            </form>
        </div>
    <?php }
    public function render_form(){
        $prefillTracking = "";
        if(isset($_GET["trackingid"])){
            $prefillTracking = $_GET["trackingid"];
        }
        $prefillOrderId = "";
        if(isset($_GET["orderid"])){
            $prefillOrderId = $_GET["orderid"];
        }
        $html = '<style>';
        $html .= '#dhl-tracking-form-container {border-bottom: 1px dotted black;float: left;width: 100%; padding: 10px;}';
        $html .= '#dhl-tracking-form-container button { float:right;}';
        $html .= '#dhl-tracking-response-container{ float:left;width:100%;position:relative;}';
        $html .= '@media only screen and (max-width: 600px) { ';
        $html .= '#dhl-tracking-form-container input {width:150px; margin-bottom:20px;}';
        $html .= '#dhl-tracking-form-container button {width:100%; margin-top:20px; }  }';
        $html .= '.loader {border: 16px solid #f3f3f3;border-top: 16px solid #3498db;border-radius: 50%;width: 120px;height: 120px; animation: spin 2s linear infinite; position:absolute;left:45%;}';
        $html .= '@keyframes spin {0% { transform: rotate(0deg); }100% { transform: rotate(360deg); }}';
        $html .= '</style>';
        $html .= "<div id='dhl-tracking-form-container'>";
        $html .= __("Tracking ID","woo-dhl-tracking-form")." <input type='text' value='".$prefillTracking."' name='trackingid' id='trackingid' placeholder='Sändnings ID'>";
        $html .= " ".__("or","woo-dhl-tracking-form")." ".__("Order Id","woo-dhl-tracking-form")." <input type='text' value='".$prefillOrderId."' id='orderid' name='orderid' placeholder='Order ID'>";
        $html .= "<button>".__("Track package","woo-dhl-tracking-form")."</button>";
        $html .= "</div>";
        wp_enqueue_script('woo-dhl-tracking-form');
        $html.="<div id='dhl-tracking-response-container'>";
        if($prefillTracking !== ""){
            $tracking=  $this->GetTrackingInfo($prefillTracking,"");
            $html .= $this->renderTable($tracking);
        }
        else if($prefillOrderId !== ""){
            $tracking=  $this->GetTrackingInfo("",$prefillOrderId);
            $html .= $this->renderTable($tracking);
        }
        $html.= "</div>";
        return $html;
    }
    function register_dhl_scripts()
    {
        wp_register_script( 'woo-dhl-tracking-form', plugins_url('dhl-main.js',__FILE__), array('jquery'), '1.0',true );
    }
    function dhl_tracking_scripts() {
        wp_enqueue_script( 'woo-dhl-tracking-form');
    }

    private function createHtml($html){
        $header = "<div id='dhl-tracking-container'><h2>".__("Your shipment:","woo-dhl-tracking-form")."</h2>";
        $footer = "</div>";
        return $header."".$html."".$footer;
    }

    /***
     * @param $trackingId What ID are we looking for?
     * @param $orderID What order ID are we looking for?
     * @param string $language What language to display the response in?
     * @return array|string The response array. Cleaned and ready for rendering html
     */
    private function GetTrackingInfo($trackingId,$orderID,$language="SV"){

        $resp = "";
        $privateAPI = get_option('private_api');
        $this->dhl = new DhlWebservice(get_option('api_password'),get_option('api_username'),$language,get_option('should_log'));
        if($trackingId != ""){
            if($privateAPI === "1"){
                $resp = $this->dhl->GetByShipmentId($trackingId);
            }else{
                $resp = $this->dhl->GetByShipmentIdPublic($trackingId);
            }
        }
        if($trackingId == ""){ // we only check orderID if there is no tracking. Tracking is alot quicker to find
            $orderid = $orderID;
            //TODO : add logic to check for if the order has trackingID in meta, in that case DONT get by reference, use the trackingID
            if($privateAPI === "1"){
                $resp = $this->dhl->GetShipmentByReference($orderid);
            }else {
                $resp = $this->dhl->GetShipmentByReferencePublic($orderid);
            }
        }

        return $resp;
    }

    /***
     *  AJAX from frontend calls this to get the response from the form
     */
    public function get_dhl_tracking() {
        $lang = get_bloginfo( $show = 'language');
        $lang = substr($lang,0,2);
        $trackingId = $_GET['trackingID'];
        $orderid = urlencode($_GET["orderID"]);
        $resp = $this->GetTrackingInfo($trackingId,$orderid,$lang);
        $html = $this->renderTable($resp);
        echo $html;
        wp_die(); // this is required to terminate immediately and return a proper response
    }

    /***
     * @param $resp The response array from the dhl web service
     * @return string Will return rendable HTML
     */
    private function renderTable($resp){
        $html = "<table>";
        $html .= "<th>".__("Date","woo-dhl-tracking-form")."</th>";
        $html .= "<th>".__("Location","woo-dhl-tracking-form")."</th>";
        $html .= "<th>".__("Event","woo-dhl-tracking-form")."</th>";
        if(!is_array($resp)){
            return $this->createHtml(__("Unable to find your shipment","woo-dhl-tracking-form"));
        }
        foreach($resp as $data){
            $html .= "<tr>";
            $html .= "<td>";
            $html .=   $data["date"]." ".$data["time"];
            $html .= "</td>";
            $html .= "<td>";
            $html .= $data["location"];
            $html .= "</td>";
            $html .= "<td>";
            $html .= $data["descr"];
            $html .= "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
        return $this->createHtml($html);
    }
}
// Gotta make sure we got Woocommerce in the house!
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    $dhl = new DHLTracking();
}

