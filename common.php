<?php

/**
 * User: Hashan Alwis
 * Date: 11/5/15    Time: 3:00 PM
 */
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Common extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->library('upload');
    }

    public function get_key($size = 32, $check_table = NULL, $check_field = NULL) {
        $key = random_string('alnum', $size);
        if ($check_table != NULL) {
            while (TRUE) {
                $res = $this->db->get_where($check_table, [$check_field => $key])->result_array();
                if (count($res) == 0) {
                    break;
                }
                $key = random_string('alnum', $size);
            }
        }
        return $key;
    }

    public function getMsg($id, $p1 = '', $p2 = '', $p3 = '') {
        $text = $this->config->item($id, 'msg');
        $text = str_replace('%1', $p1, $text);
        $text = str_replace('%2', $p2, $text);
        $text = str_replace('%3', $p3, $text);
        return $text;
    }

    public function getNotifyMsg($id, $p1 = '', $p2 = '', $p3 = '') {

        $this->db->select('message');
        $this->db->from('message');
        $this->db->where('msg_id', $id);
        $user = $this->db->get()->row();
        $text = '';
        if ($user) {
            $text = $user->message;
            $text = str_replace('%1', $p1, $text);
            $text = str_replace('%2', $p2, $text);
            $text = str_replace('%3', $p3, $text);
        }
        return $text;
    }

    public function get_allProp() {
        $user_id = $this->session->userdata('user_id');
        $query = $this->db->query("SELECT * FROM user_property INNER JOIN business ON user_property.property_id = business.id WHERE user_id = '$user_id' AND user_property.enabled = '1'");
        $result = $query->result();
        return $result;
    }

    public function getSliderImages() {

        $this->db->select('image_name,b.organization_id');
        $this->db->from('main_slider');
        $this->db->join('business b', 'b.id = main_slider.business_id', 'inner');
        $this->db->where(array('main_slider.enable' => 1, 'organization_id !=' => 15));
        $this->db->order_by('id', 'RANDOM');
        $this->db->limit(5);
        $slider = $this->db->get()->result_array();
        $q = $this->db->last_query();
        if (!empty($slider)) {
            return $slider;
        } else {
            return false;
        }
    }

    public function getSystemInformation() {

        $this->db->select('*');
        $this->db->from('system_preferences');
        $this->db->where('id', 1);
        $system = $this->db->get()->result();
        if (!empty($system)) {
            return $system[0];
        } else {
            return false;
        }
    }

    public function AddAuditTrailEntry($action, $entity, $note) {
        /**
         * Add Items for audit trail
         * table fields
         * id int auto increment
         * user varchar (40)
         * action tinyint
         * entity tinyint
         * note text
         * datetime datetime
         */
        $data = array(
            'user' => $this->session->userdata('user_name') != '' ? $this->session->userdata('user_name') : 'Guest',
            'action' => !empty($action) ? $action : '',
            'entity' => !empty($entity) ? $entity : '',
            'note' => !empty($note) ? $note : '',
            'datetime' => gmdate("Y-m-d H:i:s")
        );

        $this->db->insert('audittrail', $data);
    }

    function randomString() {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }

    function get_widget_by_id($widget_id) {
        $res = $this->db
                        ->from('widget w')
                        ->where('w.id', $widget_id)
                        ->get()->result_array();
        if (count($res) > 0) {
            return $res[0];
        }
        return FALSE;
    }

    /**
     * 
     * @param type $expected_user_type
     * @param type $expected_department
     * @return booleanReturns true if either or both user_type (weight) and user_department conditions are met.
     * Should provide at-lest one parameter. Both cannot be null at the same time.
     * 
     */
    public function has_permission($expected_user_type = NULL, $expected_department = NULL) {
        //get user profile
        $profile = $this->session->all_userdata();
        if ($profile != null && count($profile) > 0 && isset($profile['user_type']) && isset($profile['departments'])) {
            if ($expected_user_type != NULL) {
                if ($profile['user_type'] >= $expected_user_type) {
                    if ($expected_department != NULL) {
                        if (count($profile['departments']) > 0) {
                            foreach ($profile['departments'] as $dp) {
                                if ($dp['department_id'] == $expected_department) {
                                    return TRUE;
                                }
                            }
                        } else {
                            return FALSE;
                        }
                    } else {
                        return TRUE;
                    }
                } else {
                    return FALSE;
                }
            } else {
                if ($expected_department != NULL) {
                    if (count($profile['departments']) > 0) {
                        foreach ($profile['departments'] as $dp) {
                            if ($dp['department_id'] == $expected_department) {
                                return TRUE;
                            }
                        }
                    } else {
                        return FALSE;
                    }
                } else {
                    //both arguments null; raise error
                    trigger_error('Both arguments cannot be null');
                }
            }
        } else {
            trigger_error('User data not found');
        }
    }

    /**
     * Created By: Sampath K Abeysinghe
     * @param type $upload_name
     * @param type $actual_width
     * @param type $actual_height
     * @param type $max_filesize
     * @param type $allowed_types
     * @param type $upload_path
     * @param type $random_filename
     * @return type
     */
    public function upload_image(
    $upload_name, $actual_width, $actual_height, $max_width, $max_height, $max_filesize = 1024, $allowed_types = 'gif|jpg|png', $upload_path = PROPERTY_HOTEL_IMAGE_PATH_UPLOAD, $random_filename = TRUE) {
        $error_no = 0;
        $error_data = NULL;
        $upload_data = NULL;
        $new_path = NULL;
        $this->load->helper('string');
        $upload_id = random_string('alnum', 5);
//        if ($this->session->userdata('user_id') != null) {
        if (isset($upload_name) && isset($actual_width) && isset($actual_height) && isset($allowed_types) && isset($max_filesize) && isset($upload_path)) {
            $upload_name = trim($upload_name);
            if (strlen($upload_name) > 0 && strlen($upload_name) < 255) {
                if (count($_FILES) > 0 && isset($_FILES[$upload_name]["name"])) {
                    $image_name = $_FILES[$upload_name]["name"];
                    list($width, $height) = getimagesize($_FILES[$upload_name]['tmp_name']);
                    if ($width >= floor($actual_width * IMAGE_TOLERANCE) && $height >= floor($actual_height * IMAGE_TOLERANCE)) {
                        $explodedArray = explode(".", $image_name);
                        $extension = end($explodedArray);
                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0775);
                        }
                        if ($new_path == NULL) {
                            $new_path = $upload_path;
                        } else {
                            $new_path = $this->remove_trailing_shash($new_path);
                        }
                        if ($random_filename) {
                            $image_name = $this->get_random_file_name($new_path, $extension);
                        } else if (file_exists($upload_path . $image_name)) {
                            $image_name = $this->get_random_file_name($new_path, $extension);
                        }
                        $config = array();
                        $config['file_name'] = $image_name;
                        $config['allowed_types'] = $allowed_types;
                        $config['min_width'] = floor($actual_width * IMAGE_TOLERANCE);
                        $config['min_height'] = floor($actual_height * IMAGE_TOLERANCE);
                        $config['max_size'] = $max_filesize;
//                            $config['max_width'] = ceil($actual_width * (IMAGE_TOLERANCE + 1));
                        $config['max_width'] = $max_width;
//                            $config['max_height'] = ceil($actual_height * (IMAGE_TOLERANCE + 1));
                        $config['max_height'] = $max_height;
                        $config['encrypt_name'] = TRUE;
                        $config['upload_path'] = $upload_path;
                        $this->upload->initialize($config);
                        $u = $this->upload;
                        if (!$this->upload->do_upload($upload_name)) {
                            $error_no = 1100; //Error while uploading the image.
                            $error_data = strip_tags($this->upload->display_errors());
                        } else {
                            $upload_data = $this->upload->data();
                            if ($upload_data['image_width'] != $actual_width || $upload_data ['image_height'] != $actual_height) { //image should be resized / cropped or both
                                //resize image
                                $resize_data = $this->resize_image($upload_path, $upload_data['file_name'], $extension, $upload_data['image_width'], $upload_data ['image_height'], $actual_width, $actual_height);
                                if ($resize_data) {
                                    $upload_data['file_name'] = $resize_data['resize_image_name'];
                                    if ($resize_data['ratio'] != 1) {
                                        //crop image
                                        $crop_data = $this->crop_image($upload_path, $resize_data['resize_image_name'], $extension, $resize_data['ratio'], $upload_data['image_width'], $upload_data ['image_height'], $actual_width, $actual_height);
                                        if ($crop_data) {
                                            $upload_data['file_name'] = $crop_data['crop_image_name'];
                                        } else {
                                            $error_no = 1106; //error while cropping image
                                            $error_data = strip_tags($crop_data['error_data']);
                                        }
                                    }
                                } else {
                                    $error_no = 1105; //error while resizing image  
                                    $error_data = strip_tags($resize_data['error_data']);
                                }
                            }
                            $upload_data['image_width'] = $actual_width;
                            $upload_data['image_height'] = $actual_height;
                        }
                    } else {
                        $error_no = 1104; //small image
                    }
                } else {
                    $error_no = 1101; //file not found
                }
            } else {
                $error_no = 1102; //invalid upload name
            }
        } else {
            $error_no = 1103; //required parameters not set
        }
//        } else {
//            $error_no = 119; //not logged in
//        }

        if ($error_no != 0 && strlen(trim($error_data)) == 0) {
            $error_data = $this->config->item($error_no, 'msg');
        }
        return array('error_no' => $error_no, 'upload_data' => $upload_data, 'error_data' => $error_data);
    }

    private function remove_trailing_shash($path) {
        return rtrim($path, '/');
    }

    public function upload($upload_name, $actual_width, $actual_height, $max_width, $max_height, $max_filesize, $allowed_types, $upload_path, $random_filename = TRUE) {
        $error_no = 0;
        $error_data = NULL;
        $upload_data = NULL;
        $extension = NULL;
        $upload_name = trim($upload_name);
        if (strlen($upload_name) > 0 && strlen($upload_name) < 255) {
            if (count($_FILES) > 0 && isset($_FILES[$upload_name]["name"])) {
                $image_name = $_FILES[$upload_name]["name"];
                list($width, $height) = getimagesize($_FILES[$upload_name]['tmp_name']);
                if ($width >= floor($actual_width * IMAGE_TOLERANCE) && $height >= floor($actual_height * IMAGE_TOLERANCE)) {
                    $explodedArray = explode(".", $image_name);
                    $extension = end($explodedArray);
                    if (!file_exists($upload_path)) {
                        mkdir($upload_path, 0775);
                    }
                    if ($random_filename) {
                        $image_name = $this->get_random_file_name($upload_path, $extension);
                    } else if (file_exists($upload_path . $image_name)) {
                        $image_name = $this->get_random_file_name($upload_path, $extension);
                    }
                    $config = array();
                    $config['file_name'] = $image_name;
                    $config['allowed_types'] = $allowed_types;
                    $config['min_width'] = floor($actual_width * IMAGE_TOLERANCE);
                    $config['min_height'] = floor($actual_height * IMAGE_TOLERANCE);
                    $config['max_size'] = $max_filesize;
                    $config['max_width'] = $max_width;
                    $config['max_height'] = $max_height;
                    $config['encrypt_name'] = TRUE;
                    $config['upload_path'] = $upload_path;
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload($upload_name)) {
                        $error_no = 1100; //Error while uploading the image.
                        $error_data = strip_tags($this->upload->display_errors());
                    } else {
                        $upload_data = $this->upload->data();
                    }
                } else {
                    $error_no = 1104; //small image
                }
            } else {
                $error_no = 1101; //file not found
            }
        } else {
            $error_no = 1102; //invalid upload name
        }

        if ($error_no != 0 && strlen(trim($error_data)) == 0) {
            $error_data = $this->config->item($error_no, 'msg');
        }
        return array('error_no' => $error_no, 'upload_data' => $upload_data, 'error_data' => $error_data, 'extension' => $extension);
    }

    private function resize_image($upload_path, $image_name, $extension, $image_width, $image_height, $actual_width, $actual_height) {
        $resize_config = array();
        $resize_image_name = $this->get_random_file_name($upload_path, $extension);
        $resize_config['image_library'] = 'gd2';
        $resize_config['source_image'] = $upload_path . '/' . $image_name;
        $resize_config['new_image'] = $upload_path . '/' . $resize_image_name;
        $resize_config['create_thumb'] = FALSE;
        $resize_config['quality'] = '100';
        $resize_config['maintain_ratio'] = TRUE;
        $resize_config['width'] = $actual_width;
        $resize_config['height'] = $actual_height;
        $dim = (intval($image_width) / intval($image_height)) - ($actual_width / $actual_height);
        $resize_config['master_dim'] = ($dim > 0) ? "height" : "width";
        $this->load->library('image_lib', $resize_config);
        if ($this->image_lib->resize()) {
            unlink($upload_path . '/' . $image_name); //delete souce image
            return array('success' => FALSE, 'resize_image_name' => $resize_image_name, 'ratio' => $dim);
        }
        return array('success' => FALSE, 'error_data' => strip_tags($this->image_lib->display_errors()));
    }

    private function crop_image($upload_path, $image_name, $extension, $ratio, $image_width, $image_height, $actual_width, $actual_height) {
        $x_axis = $y_axis = 0;
        if ($ratio > 0) {
            $resize_width = round(($image_width / $image_height), 1, PHP_ROUND_HALF_ODD) * $actual_height;
            $x_axis = ($resize_width - $actual_width) / 2;
            $y_axis = 0;
        } else {
            $resize_height = round(($image_height / $image_width), 1, PHP_ROUND_HALF_ODD) * $actual_width;
            $x_axis = 0;
            $y_axis = ($resize_height - $actual_height) / 2;
        }
        $crop_image_name = $this->get_random_file_name($upload_path, $extension);
        $crop_config = array();
        $crop_config['image_library'] = 'gd2';
        $crop_config['source_image'] = $upload_path . '/' . $image_name;
        $crop_config['new_image'] = $upload_path . '/' . $crop_image_name;
        $crop_config['quality'] = '100';
        $crop_config['overwrite'] = TRUE;
        $crop_config['maintain_ratio'] = FALSE;
        $crop_config['width'] = $actual_width;
        $crop_config['height'] = $actual_height;
        $crop_config['x_axis'] = $x_axis;
        $crop_config['y_axis'] = $y_axis;

        $this->image_lib->initialize($crop_config);
        if ($this->image_lib->crop()) {
            unlink($upload_path . '/' . $image_name); //delete souce image
            return array('success' => TRUE, 'crop_image_name' => $crop_image_name);
        }
        return array('success' => FALSE, 'error_data' => strip_tags($this->image_lib->display_errors()));
    }

    /**
     * 
     * @param type $upload_path
     * @param type $extension
     * @return 15 digit file name with extension 
     */
    private function get_random_file_name($upload_path, $extension) {
        $file_name = '';
        do {
            $file_name = substr(number_format(time() * rand(), 0, '', ''), 0, 15) . '.' . $extension;
        } while (file_exists($upload_path . $file_name));
        return $file_name;
    }

    public function update_progress($total_items, $completed_items, $upload_id, $desc) {
        file_put_contents(
                SERVER_ROOT_DIRECTORY . 'upload_' . $upload_id . '.json', json_encode(array('items_completed' => $completed_items, 'total_items' => $total_items, 'desc' => $desc))
        );
        usleep(1000 * 1000); // 10 seconds
    }

    function escapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
        $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c", "'", "{", "}", "$", "@", "#");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b", "", "", "", "", "", "");
        $result = str_replace($escapers, $replacements, $value);
//        return $result;
        $result = trim(htmlspecialchars($result));
        return $result;
    }

    function reverseEscapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
        $replacements = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
        $escapers = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }

    function get_enum_values($table, $field) {
        $type = $this->db->query("SHOW COLUMNS FROM {$table} WHERE Field = '{$field}'")->row(0)->Type;
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        $enum = explode("','", $matches[1]);
        return $enum;
    }

    function get_icon_by_sales_type($sales_type) {
        switch ($sales_type) {
            case SALES_TYPE_LODGING:
                return ICON_LODGING;
            case SALES_TYPE_FOODS:
                return ICON_FOODS;
            case SALES_TYPE_BEVERAGES:
                return ICON_BEVERAGES;
            case SALES_TYPE_MEAL:
                return ICON_MEAL;
            case SALES_TYPE_TOUR:
                return ICON_TOUR;
        }
    }

    function get_icon_by_item_type($is_addon, $is_composite) {
        $icon = ICON_PRIMARY;
        if ($is_addon == "1") {
            $icon = ICON_ADDON;
        } else if ($is_composite == "1") {
            $icon = ICON_COMPOSITE;
        }
        return $icon;
    }

    private function get_theme_info($business) {
        $system = $this->common->getSystemInformation();
        $data['colortheme'] = $business->colortheme;
        $data['header_font'] = $business->header_font;
        $data['slogan_font'] = $business->slogan_font;
        $data['header_namesize'] = $business->header_namesize;

        $data['header_slogansize'] = $business->header_slogansize;
        $data['hotelname_align'] = $business->hotelname_leftindent;

        $data['hotelname_leftright'] = $business->hotelname_leftright;
        $data['slogan_leftright'] = $business->slogan_leftright;
        $data['hotellogo_leftright'] = $business->hotellogo_leftright;
        $data['searchbox_leftright'] = $business->searchbox_leftright;
        $data['searchbox_topindent'] = $business->searchbox_topindent;
        $data['hotellogo_topindent'] = $business->hotellogo_topindent;
        $data['hotellogo_align'] = $business->hotellogo_align;
        $data['searchbox_align'] = $business->searchbox_align;
        $data['font_content'] = $business->font_content;
        $data['font_title'] = $business->font_title;


        $data['companylogo_enabled'] = $business->companylogo_enabled;
        $data['metadata_title'] = $business->metadata_title;
        $data['metadata_description'] = $business->metadata_description;
        $data['metadata_keywords'] = $business->metadata_keywords;
        $data['googleanalytics_code'] = $business->googleanalytics_code;

        $data['hotelname_topindent'] = $business->hotelname_topindent;
        $data['slogan_topindent'] = $business->slogan_topindent;
        $data['characterspace_hotelname'] = $business->characterspace_hotelname;
        $data['characterspace_slogan'] = $business->characterspace_slogan;
        $data['map_address'] = $business->map_address;

        $data['slogan_align'] = $business->slogan_leftindent;
        $data['googlefont'] = $business->googlefont;
        $data['googlefont_slogan'] = $business->googlefont_slogan;
        $data['slider'] = $this->business->getSliderByBusiness($business->id);
        $data['addons'] = $this->business->getAddonsByBusiness($business->id);
        $data['hotel'] = $this->business->getHotelById($business->business_id);
        $data['thingstodo'] = $this->business->getThingstodoByBusiness($business->id);
        $data['splashwindow'] = $this->business->getSplashWindowDetails($business->id);
        $data['bookingboxenabled'] = $business->bookingboxenabled;
        $data['maintenance_mode'] = $business->maintenance_mode;
        $data['system'] = $system;
        $data['currency'] = $this->business->get_currency_by_id($business->currency_id);
//        $templatefolder = isset($business->templatefolder) ? $business->templatefolder : DEFAULT_TEMPLATE_FOLDER;
        $templatefolder = DEFAULT_TEMPLATE_FOLDER;

        $rooms = $this->itemmodel->get_sales_items_by_property_id($business->id, SALES_TYPE_LODGING, true, true);
        //Modules
        $modules = $this->business->getBusinessModules($business->id);
        $module_arr = array();
        $i = 0;
        if ($modules) {
            foreach ($modules as $module) {
                $module_arr[$i] = $module['module_id'];
            }
            $data['module_arr'] = $module_arr;
        }
        $result_arr = array();
        $i = 0;


        $data['allfacilities'] = $this->business->getAllFacilities();
        $data['footerImagePath'] = $business->footerimage_name;

        $data['header_font'] = '';
        $data['header_font_url'] = '';
        if ($business->googlefont == 1) {
            $data['header_font'] = trim($data['header_font']);
            $data['header_font_url'] = str_replace(" ", "+", trim($data['header_font']));
        }
        $data['header_slogan_font'] = '';
        $data['header_slogan_font_url'] = '';
        if ($business->googlefont_slogan == 1) {
            $data['header_slogan_font'] = trim($data['slogan_font']);
            $data['header_slogan_font_url'] = str_replace(" ", "+", trim($data['slogan_font']));
        }
        $business_facilities = $this->business->getFacilityByBusiness($business->id);
        $facility_list = array();
        for ($i = 0; $i < count($business_facilities); ++$i) {
            array_push($facility_list, $business_facilities[$i]['facility_type']);
        }
        $data['businessfacilities'] = $facility_list;
        return $data;
    }

    public function get_encrypted_text($plain_text) {
        $this->load->library('encryption');
        $this->encryption->initialize(
                array(
                    'cipher' => 'aes-128',
                    'mode' => 'cbc',
                    'key' => ENC_SEND_KEY
                )
        );
        return $this->encryption->encrypt($plain_text);
    }

    public function get_decrypted_text($cipher_text) {
        $this->load->library('encryption');
        $this->encryption->initialize(
                array(
                    'cipher' => 'aes-128',
                    'mode' => 'cbc',
                    'key' => ENC_RECEIVE_KEY
                )
        );
        return $this->encryption->decrypt($cipher_text);
    }

    public function easy_mail($to_addr, $subject, $template_type, $email_info) {
        $this->load->library('parser');
        $email_tempalte = $this->bookingmodel->get_email_template_by_type($template_type);
        $message = $this->parser->parse_string($email_tempalte, $email_info, TRUE);
        if ($this->send_mail($to_addr, CONTACT_EMAIL, 'Smart eBookings', $subject, $message)) {
            $this->AddAuditTrailEntry('Mail', '', $subject);
            return TRUE;
        }
        return FALSE;
    }

    public function easy_mail_with_content($to_addr, $subject, $content) {
        $this->load->library('parser');
        if ($this->send_mail($to_addr, CONTACT_EMAIL, 'Smart eBookings', $subject, $content)) {
            $this->AddAuditTrailEntry('Mail', '', $subject);
            return TRUE;
        }
        return FALSE;
    }

    public function send_mail($to_addr, $from_addr, $from_name, $subject, $message, $cc_addr = NULL, $bcc_addr = NULL, $attachments = NULL) {
        $this->load->library('email');
        $config = array();
        $config['protocol'] = 'sendmail';
        $config['charset'] = 'utf-8';
        $config['mailtype'] = 'html';
        $config['wrapchars'] = 150;
        $this->email->initialize($config);
        $this->email->clear();
        $this->email->from($from_addr, $from_name);
        $this->email->to($to_addr);
        if (isset($cc_addr)) {
            $this->email->cc($cc_addr);
        }
        if (isset($bcc_addr)) {
            $this->email->bcc($bcc_addr);
        }
        $this->email->subject($subject);
        $this->email->message($message);
        if ($attachments != NULL && count($attachments) > 0) {
            foreach ($attachments as $a) {
                $this->email->attach($a['buffer'], 'attachment', $a['name'], $a['mime']);
            }
        }
        return $this->email->send();
    }

    public function file_upload($file_input_name, $upload_path, $max_size, $allowed_types = 'gif|jpg|png', $max_width = NULL, $max_height = NULL) {
        $config['upload_path'] = $upload_path;
        $config['allowed_types'] = $allowed_types;
        $config['max_size'] = $max_size;
        if (count($_FILES) > 0 && isset($_FILES[$file_input_name])) {
            $image_name = $_FILES[$file_input_name]["name"];
            $parts = explode(".", $image_name);
            $extension = end($parts);
            $file_name = $this->get_random_file_name($upload_path, $extension);
            $config['file_name'] = $file_name;
            if ($max_width != NULL) {
                $config['max_width'] = $max_width;
            }
            if ($max_height != NULL) {
                $config['max_height'] = $max_height;
            }
            $this->load->library('upload', $config);
            if (!$this->upload->do_upload($file_input_name)) {
                return array('error' => $this->upload->display_errors());
            } else {
                return array('upload_data' => $this->upload->data());
            }
        } else {
            return array('error' => 'File not found');
        }
    }

    public function get_all_country() {
        $query = $this->db->get('countries');
        $result = $query->result_array();
        return $result;
    }

    public function hide_email($email) {
        $em = explode("@", $email);
        $name = implode(array_slice($em, 0, count($em) - 1), '@');
        if (strlen($name) == 1) {
            return '*' . '@' . end($em);
        }
        $len = floor(strlen($name) / 2);
        return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
    }

    public function get_creation_data() {
        $created_by = $this->session->userdata('user_id');
        if ($created_by == FALSE) {
            $created_by = 0;
        }
        $created_on = date("Y-m-d H:i:s");
        return array(
            'enabled' => 1,
            'created_by' => $created_by,
            'created_on' => $created_on,
            'lastupdated_by' => $created_by,
            'lastupdated_on' => $created_on
        );
    }

    public function get_unique_code($length, $table, $column) {
        $code = NULL;
        do {
            $temp_code = $this->randomString($length, FALSE, FALSE, TRUE);
            $res = $this->db->get_where($table, array($column => $temp_code))->result_array();
            if (count($res) == 0) {
                $code = $temp_code;
            }
        } while ($code == NULL);
        return $code;
    }

    public function get_notification_template_by_id($id) {
        $res = $this->db->get_where('notification_template', array('id' => $id))->result_array();
        $q = $this->db->last_query();
        if (count($res) > 0) {
            return $res[0];
        }
        return FALSE;
    }

    public function convert_servertime_to_business_time($date, $business_timezone) {
        $dateTime = new DateTime($date, new DateTimeZone(SERVER_TIME_ZONE));
        $dateTime->setTimezone(new DateTimeZone($business_timezone));
        return $dateTime->format('Y-m-d h:ia');
    }
    
    public function convert_php_servertime_to_business_time($date, $business_timezone) {
        $dateTime = new DateTime($date, new DateTimeZone(PHP_SERVER_TIME_ZONE));
        $dateTime->setTimezone(new DateTimeZone($business_timezone));
        return $dateTime->format('Y-m-d H:ia');
    }
    
    public function business_time_to_php_servertime($date, $business_timezone) {
        $dateTime = new DateTime($date, new DateTimeZone($business_timezone));
        $dateTime->setTimezone(new DateTimeZone(PHP_SERVER_TIME_ZONE));
        return $dateTime->format('Y-m-d H:ia');
    }

    public function get_pdf_buffer($title, $filename, $html) {
        $this->load->library('Pdf');
        $pdf = new Pdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetTitle($title);
        $pdf->SetHeaderMargin(30);
        $pdf->SetTopMargin(20);
        $pdf->setFooterMargin(20);
        $pdf->SetAutoPageBreak(true);
        $pdf->SetAuthor('Author');
        $pdf->SetDisplayMode('real', 'default');

        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        return $pdf->Output($filename, 'S');
    }
    public function get_country_by_id($id){
        $name = '';
        $res= $this->db->get_where('countries', ['id' => $id])->result_array();
        if(count($res) > 0){
            $name = $res[0]['name'];
        }
        return $name;
            
    }

}
