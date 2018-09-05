<?php

/**
 * User: Hashan Alwis
 * Date: 11/5/15    Time: 3:00 PM
 */
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Common extends MY_Model {

    public function __construct() {
        parent::__construct();
        $this->load->library('upload');
        $this->load->library('image_lib');
        $this->load->helper('string');
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

//    function randomString() {
//        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
//        $pass = array(); //remember to declare $pass as an array
//        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
//        for ($i = 0; $i < 8; $i++) {
//            $n = rand(0, $alphaLength);
//            $pass[] = $alphabet[$n];
//        }
//        return implode($pass); //turn the array into a string
//    }

    function randomString($length = 6, $alpha_only = FALSE, $numeric_only = FALSE, $avoid_ambiguous = FALSE) {
        $str = "";
        $avoid_characters = array('0', '1', 'i', 'I', 'o', 'O', 'l', 'L');
        if ($alpha_only) {
            $characters = array_merge(range('A', 'Z'), range('a', 'z'));
        } else if ($numeric_only) {
            $characters = array_merge(range('0', '9'));
        } else {
            $characters = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));
        }
        if ($avoid_ambiguous) {
            $characters = array_diff($characters, $avoid_characters);
        }
        $max = count($characters) - 1;
        $i = 0;
        while ($i < $length) {
            $rand = mt_rand(0, $max);
            if (isset($characters[$rand])) {
                $str .= $characters[$rand];
                $i++;
            }
        }
        return $str;
    }

    /**
     * Returns unique string code that does not exist in the given table colunm
     * @param type $length - length required
     * @param type $table - table name to check
     * @param type $column - column name which contails code
     */
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

    /**
     * Get the tempate row by id
     * @param type $id
     */
    public function get_template_by_id($id) {
        $res = $this->db->get_where('template', array('id' => $id))->result_array();
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
    $upload_name, $actual_width, $actual_height, $max_width, $max_height, $max_filesize = 1024, $allowed_types = 'gif|jpg|png', $upload_path = PROPERTY_HOTEL_IMAGE_PATH_UPLOAD, $random_filename = TRUE, $new_path = NULL) {
        $error_no = 0;
        $error_data = NULL;
        $upload_data = NULL;
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

    private function resize_image($upload_path, $image_name, $extension, $image_width, $image_height, $actual_width, $actual_height, $new_path = NULL) {
        $upload_path = $this->remove_trailing_shash($upload_path);
        $resize_config = array();
        if ($new_path == NULL) {
            $new_path = $upload_path;
        } else {
            $new_path = $this->remove_trailing_shash($new_path);
        }
        $resize_image_name = $this->get_random_file_name($upload_path, $extension);
        $resize_config['image_library'] = 'gd2';
        $resize_config['source_image'] = $upload_path . '/' . $image_name;
        $resize_config['new_image'] = $new_path . '/' . $resize_image_name;
        $resize_config['create_thumb'] = FALSE;
        $resize_config['quality'] = '100';
        $resize_config['maintain_ratio'] = TRUE;
        $resize_config['width'] = $actual_width;
        $resize_config['height'] = $actual_height;
        $dim = (intval($image_width) / intval($image_height)) - ($actual_width / $actual_height);
        $resize_config['master_dim'] = ($dim > 0) ? "height" : "width";
        $this->image_lib->clear();
        $this->image_lib->initialize($resize_config);
        if ($this->image_lib->resize()) {
            unlink($upload_path . '/' . $image_name); //delete souce image
            return array('success' => TRUE, 'resize_image_name' => $resize_image_name, 'ratio' => $dim);
        }
        return array('success' => FALSE, 'error_data' => strip_tags($this->image_lib->display_errors()));
    }

    private function crop_image($upload_path, $image_name, $extension, $ratio, $image_width, $image_height, $actual_width, $actual_height, $new_path = NULL) {
        $upload_path = $this->remove_trailing_shash($upload_path);
        if ($new_path == NULL) {
            $new_path = $upload_path;
        } else {
            $new_path = $this->remove_trailing_shash($new_path);
        }
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
        $crop_config['new_image'] = $new_path . '/' . $crop_image_name;
        $crop_config['quality'] = '100';
        $crop_config['overwrite'] = TRUE;
        $crop_config['maintain_ratio'] = FALSE;
        $crop_config['width'] = $actual_width;
        $crop_config['height'] = $actual_height;
        $crop_config['x_axis'] = $x_axis;
        $crop_config['y_axis'] = $y_axis;
        $this->image_lib->clear();
        $this->image_lib->initialize($crop_config);
        if ($this->image_lib->crop()) {
            unlink($upload_path . '/' . $image_name); //delete souce image
            return array('success' => TRUE, 'crop_image_name' => $crop_image_name);
        }
        return array('success' => FALSE, 'error_data' => strip_tags($this->image_lib->display_errors()));
    }

    public function watermark_image($source_image_path, $text) {
        $config['source_image'] = $source_image_path;
        $config['wm_text'] = $text;
        $config['wm_type'] = 'text';
        $config['wm_font_path'] = './system/fonts/arial.ttf';
        $config['wm_font_size'] = '48';
        $config['wm_font_color'] = 'aaaaaa';
        $config['wm_vrt_alignment'] = 'middle';
        $config['wm_hor_alignment'] = 'center';
        $config['wm_padding'] = '0';
        $config['quality'] = '100';
        $this->image_lib->initialize($config);
        return $this->image_lib->watermark();
    }

    private function remove_trailing_shash($path) {
        return rtrim($path, '/');
    }

    public function upload_model_image($upload_name) {
        $res = $this->upload('model-image', MODEL_MINWIDTH, MODEL_MINHEIGHT, MODEL_MAXWIDTH, MODEL_MAXHEIGHT, MODEL_MAXSIZE, MODEL_ALLOWEDTYPES, MODEL_IMAGE_UPLOAD_PATH);
        $watermark_text = "Copyright - " . APP_NAME;
        $hq_file_name = $zoom_file_name = $thumbnail_file_name = '';
        if ($res['error_no'] == 0) {
            $upload_data = $res['upload_data'];
            $hq_file_name = $zoom_file_name = $thumbnail_file_name = $upload_data['file_name'];
            copy(MODEL_IMAGE_UPLOAD_PATH . $hq_file_name, MODEL_ZOOM_UPLOAD_PATH . $hq_file_name);
            copy(MODEL_IMAGE_UPLOAD_PATH . $hq_file_name, MODEL_THUMBNAIL_UPLOAD_PATH . $hq_file_name);
            $resize_res = $this->resize_image($upload_data['file_path'], $hq_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], MODEL_MINWIDTH, MODEL_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $hq_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image($upload_data['file_path'], $resize_res['resize_image_name'], $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], MODEL_MINWIDTH, MODEL_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $hq_file_name = $crop_res['crop_image_name'];
                    $this->watermark_image(MODEL_IMAGE_UPLOAD_PATH . $hq_file_name, $watermark_text);
                }
            }
            //create zoom image
            $resize_res = $this->resize_image(MODEL_ZOOM_UPLOAD_PATH, $zoom_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], MODEL_ZOOM_MINWIDTH, MODEL_ZOOM_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $zoom_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image(MODEL_ZOOM_UPLOAD_PATH, $resize_res['resize_image_name'], $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], MODEL_ZOOM_MINWIDTH, MODEL_ZOOM_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $zoom_file_name = $crop_res['crop_image_name'];
                    $this->watermark_image(MODEL_ZOOM_UPLOAD_PATH . $zoom_file_name, $watermark_text);
                }
            }
            //create thumbnail image
            $resize_res = $this->resize_image(MODEL_THUMBNAIL_UPLOAD_PATH, $thumbnail_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], MODEL_THUMBNAIL_MINWIDTH, MODEL_THUMBNAIL_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $thumbnail_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image(MODEL_THUMBNAIL_UPLOAD_PATH, $thumbnail_file_name, $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], MODEL_THUMBNAIL_MINWIDTH, MODEL_THUMBNAIL_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $thumbnail_file_name = $crop_res['crop_image_name'];
                }
            }
        }
        $res['hq_image_name'] = $hq_file_name;
        $res['zoom_image_name'] = $zoom_file_name;
        $res['thumbnail_image_name'] = $thumbnail_file_name;
        return $res;
    }

    public function upload_item_image($upload_name) {
        $res = $this->upload('item-image', ITEM_MINWIDTH, ITEM_MINHEIGHT, ITEM_MAXWIDTH, ITEM_MAXHEIGHT, ITEM_MAXSIZE, ITEM_ALLOWEDTYPES, ITEM_IMAGE_UPLOAD_PATH);
        $watermark_text = "Copyright - " . APP_NAME;
        $hq_file_name = $zoom_file_name = $thumbnail_file_name = '';
        if ($res['error_no'] == 0) {
            $upload_data = $res['upload_data'];
            $hq_file_name = $zoom_file_name = $thumbnail_file_name = $upload_data['file_name'];
            copy(ITEM_IMAGE_UPLOAD_PATH . $hq_file_name, ITEM_ZOOM_UPLOAD_PATH . $hq_file_name);
            copy(ITEM_IMAGE_UPLOAD_PATH . $hq_file_name, ITEM_THUMBNAIL_UPLOAD_PATH . $hq_file_name);
            $resize_res = $this->resize_image($upload_data['file_path'], $hq_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], ITEM_MINWIDTH, ITEM_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $hq_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image($upload_data['file_path'], $resize_res['resize_image_name'], $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], ITEM_MINWIDTH, ITEM_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $hq_file_name = $crop_res['crop_image_name'];
                    $this->watermark_image(ITEM_IMAGE_UPLOAD_PATH . $hq_file_name, $watermark_text);
                }
            }
            //create zoom image
            $resize_res = $this->resize_image(ITEM_ZOOM_UPLOAD_PATH, $zoom_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], ITEM_ZOOM_MINWIDTH, ITEM_ZOOM_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $zoom_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image(ITEM_ZOOM_UPLOAD_PATH, $resize_res['resize_image_name'], $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], ITEM_ZOOM_MINWIDTH, ITEM_ZOOM_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $zoom_file_name = $crop_res['crop_image_name'];
                    $this->watermark_image(ITEM_ZOOM_UPLOAD_PATH . $zoom_file_name, $watermark_text);
                }
            }
            //create thumbnail image
            $resize_res = $this->resize_image(ITEM_THUMBNAIL_UPLOAD_PATH, $thumbnail_file_name, $res['extension'], $upload_data['image_width'], $upload_data['image_height'], ITEM_THUMBNAIL_MINWIDTH, ITEM_THUMBNAIL_MINHEIGHT);
            if ($resize_res['success'] == TRUE) {
                $thumbnail_file_name = $resize_res['resize_image_name'];
                $crop_res = $this->crop_image(ITEM_THUMBNAIL_UPLOAD_PATH, $thumbnail_file_name, $res['extension'], $resize_res['ratio'], $upload_data['image_width'], $upload_data['image_height'], ITEM_THUMBNAIL_MINWIDTH, ITEM_THUMBNAIL_MINHEIGHT);
                if ($crop_res['success'] = TRUE) {
                    $thumbnail_file_name = $crop_res['crop_image_name'];
                }
            }
        }
        $res['hq_image_name'] = $hq_file_name;
        $res['zoom_image_name'] = $zoom_file_name;
        $res['thumbnail_image_name'] = $thumbnail_file_name;
        return $res;
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

    public function easy_mail($to_addr, $subject, $email_info, $email_content, $attachments = NULL) {
        $this->load->library('parser');
        $message = $this->parser->parse_string($email_content, $email_info, TRUE);
//        $email_template = $this->load->view('email_template', $data, TRUE);
        if ($this->send_mail($to_addr, CONTACT_EMAIL, APP_NAME, $subject, $message, NULL, NULL, $attachments)) {
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

    public function get_all_rows($table_name, $enabled = NULL) {
        $res = array();
        if ($enabled != NULL && ($enabled == 0 || $enabled == 1)) {
            $this->db->where('enabled', $enabled);
        }
        $res = $this->db->get($table_name)->result_array();
        return $res;
    }

    public function toggle_enable($table_name, $id, $updated_by) {
        if ($table_name != NULL && $id != NULL) {
            //get current status
            $this->db->trans_start();
            $res = $this->db->get_where($table_name, array('id' => $id))->result_array();
            if (count($res) > 0) {
                $res = $res[0];
                $new_status = 1;
                $enable = 'Enable';
                if ($res['enabled'] == "1") {
                    $new_status = 0;
                    $enable = 'Disable';
                }
                $this->db->where('id', $id);
                $status = $this->db->update($table_name, array('enabled' => $new_status, 'lastupdated_on' => date('Y-m-d H:i:s'), 'lastupdated_by' => $updated_by));
                if ($status) {
                    if ($new_status == 0) {
                        Common::AddAuditTrailEntry(AUDITTRAIL_DELETE, $table_name, "ID: " . $id);
                    } else {
                        Common::AddAuditTrailEntry(AUDITTRAIL_UPDATE, $table_name, "ID: " . $id);
                    }
                    $this->db->trans_complete();
                }
                return $status;
            }
            $this->db->trans_rollback();
        }
        return FALSE;
    }

    public function get_messages_script($messages) {
        $script = '' . PHP_EOL;
        $config = $this->config;
        $iter = new RecursiveArrayIterator($messages);
        foreach (new RecursiveIteratorIterator($iter) as $key => $value) {
            $msg = $config->itme($value);
            if ($msg != NULL)
                $script .= "messages[$value] = '" . $msg . "'" . PHP_EOL;
        }
        return $script;
    }

    public function get_entity_by_id($table, $id) {
        if ($table != NULL && $id != NULL) {
            $table = trim($table);
            if (strlen($table) > 0 && strlen($table) < 30) {
                $id = intval(trim($id));
                if ($id > 0 && $id < 99999) {
                    $res = $this->db->get_where($table, array('id' => $id))->result_array();
                    if (count($res) > 0) {
                        return $res[0];
                    }
                }
            }
        }
        return FALSE;
    }

    public function get_all_fields($enabled = NULL, $id = NULL) {
        $this->db
                ->select('fl.*')
                ->from('field fl');
        if ($enabled != NULL && ($enabled == TRUE || $enabled == FALSE)) {
            $this->db->where('enabled', $enabled);
        }
        if ($id != NULL) {
            $this->db->where("fl.id", $id);
        }
        $this->db->group_by('fl.id');
        $res = $this->db->get();
        return $res->result_array();
    }

    public function get_field_values_by_id($field_id, $enabled = NULL) {
        if ($enabled != NULL) {
            $this->db->where('enabled', $enabled);
        }
        $res = $this->db
                ->get_where('field_value', array('field_id' => $field_id))
                ->result_array();
        return $res;
    }

    public function add_field($name, $type, $min_value, $max_value, $unit, $max_characters, $created_by) {
        $date = date('y-m-d h:i:s');
        return $this->db->insert('field', array(
                    'field_name' => $name,
                    'html_id' => $this->create_html_id($name),
                    'field_type' => $type,
                    'min_value' => $min_value,
                    'max_value' => $max_value,
                    'unit' => $unit,
                    'max_nof_characters' => $max_characters,
                    'enabled' => 1,
                    'created_by' => $created_by,
                    'created_on' => $date,
                    'lastupdated_by' => $created_by,
                    'lastupdated_on' => $date
        ));
    }

    public function edit_field($id, $name, $type, $min_value, $max_value, $unit, $max_characters, $updated_by) {
        $date = date('y-m-d h:i:s');
        $this->db->where('id', $id);
        return $this->db->update('field', array(
                    'field_name' => $name,
                    'html_id' => $this->create_html_id($name),
                    'field_type' => $type,
                    'min_value' => $min_value,
                    'max_value' => $max_value,
                    'unit' => $unit,
                    'max_nof_characters' => $max_characters,
                    'lastupdated_by' => $updated_by,
                    'lastupdated_on' => $date
        ));
    }

    public function create_html_id($text) {
        $html_id = strtolower(trim($text));
        $html_id = str_replace(' ', '-', $html_id);
        $html_id = str_replace('_', '-', $html_id);
        $html_id = str_replace('"', '', $html_id);
        $html_id = str_replace("'", '', $html_id);
        return $html_id;
    }

    public function add_sub_field($name, $field_id, $data_type, $unit, $order_id, $min_value, $max_value, $is_mandatory, $created_by) {
        $date = date('y-m-d h:i:s');
        return $this->db->insert('sub_field', array(
                    'sub_field_name' => $name,
                    'field_id' => $field_id,
                    'data_type' => $data_type,
                    'unit' => $unit,
                    'display_order' => $order_id,
                    'min_value' => $min_value,
                    'max_value' => $max_value,
                    'is_mandatory' => ($is_mandatory) ? 1 : 0,
                    'enabled' => 1,
                    'created_by' => $created_by,
                    'created_on' => $date,
                    'lastupdated_by' => $created_by,
                    'lastupdated_on' => $date
        ));
    }

    public function edit_sub_field($id, $name, $data_type, $unit, $order_id, $min_value, $max_value, $is_mandatory, $updated_by) {
        $date = date('y-m-d h:i:s');
        $this->db->where('id', $id);
        return $this->db->update('sub_field', array(
                    'sub_field_name' => $name,
                    'data_type' => $data_type,
                    'unit' => $unit,
                    'display_order' => $order_id,
                    'min_value' => $min_value,
                    'max_value' => $max_value,
                    'is_mandatory' => $is_mandatory,
                    'lastupdated_by' => $updated_by,
                    'lastupdated_on' => $date
        ));
    }

    public function get_field_readable_type($field_type) {
        $name = '';
        switch ($field_type) {
            case 'TEXT':
                $name = 'Text';
                break;
            case 'RICH_TEXT':
                $name = 'Rich Text';
                break;
            case 'MEMO':
                $name = 'Memo';
                break;
            case 'INT':
                $name = 'Integer';
                break;
            case 'FLOAT':
                $name = 'Float';
                break;
            case 'BOOLEAN':
                $name = 'Boolean';
                break;
            case 'DATE':
                $name = 'Date';
                break;
            case 'LIST':
                $name = 'List';
        }
        return $name;
    }

    public function render_field($field_data, $model_id, $value = NULL) {
        $field_name = htmlspecialchars($field_data['field_name']);
        $html_id = $field_data['html_id'];
        $required = ($field_data['is_optional'] == 0) ? 'required="true"' : '';
        $placeholder = 'Enter ' . $field_name;
        $field_html = '';
        $val = '';
        if ($value != NULL) {
            $val = htmlspecialchars($value);
        }
        $step = "1";
        $min = floatval($field_data['min_value']);
        $max = floatval($field_data['max_value']);
        $max_txt = ($max > 0) ? "max='$max'" : '';
        $max_characters_txt = ($field_data['max_nof_characters']) ? "maxlength='" . $field_data['max_nof_characters'] . "'" : '';
        switch ($field_data['field_type']) {
            case 'TEXT':
                $field_html = "<input $max_characters_txt placeholder='$placeholder' class='form-control' type='text' id='$html_id' name='$html_id' $required value='$val'/>";
                break;
            case 'RICH_TEXT':
            case 'MEMO':
                $field_html = "<textarea $max_characters_txt rows='6' placeholder='$placeholder' class='form-control' id='$html_id' name='$html_id' $required>$val</textarea>";
                break;
            case 'FLOAT':
                $step = "0.01";
            case 'INT':
                $field_html = "<input $max_characters_txt step='$step' placeholder='$placeholder' class='form-control' type='number' min='$min' $max_txt value='$min' id='$html_id' name='$html_id' $required value='$val'/>";
                break;
            case 'BOOLEAN':
                $no_selelcted = '';
                if ($val == 0) {
                    $no_selelcted = "selected";
                }
                $field_html = "<select id='$html_id' name='$html_id' class='form-control'><option value='YES'>Yes</option><option value='NO' $no_selelcted>No</option></select>";
                break;
            case 'DATE':
                $field_html = "<input type='text' class='form-control date-picker' id='$html_id' name='$html_id' readonly='true' value='$val'>";
                break;
            case 'LIST':
                $field_options_html = $this->get_field_options_by_field_id($model_id, $field_data['id'], $value);
                $field_html = "<select class='form-control' id='$html_id' name='$html_id' $required>$field_options_html</select>";
        }
        return $field_html;
    }

    public function get_field_options_by_field_id($model_id, $field_id, $value = NULL) {
        $html = '';
        $options = $this->db
                        ->select('fv.*')
                        ->from('field_value fv')
                        ->join('model_field_value mfv', 'mfv.field_value_id=fv.id')
                        ->where('mfv.enabled', 1)
                        ->where('fv.enabled', 1)
                        ->where('fv.field_id', $field_id)
                        ->where('mfv.model_id', $model_id)
                        ->get()->result_array();
        $q = $this->db->last_query();
        if (count($options) > 0) {
            foreach ($options as $option) {
                $selected = '';
                if ($value != NULL && $value == $option['id']) {
                    $selected = 'selected';
                }
                $html .= "<option value='" . $option['id'] . "' " . $selected . ">" . htmlspecialchars($option['value']) . "</option>";
            }
        }
        return $html;
    }

    public function get_preferences() {
        $res = $this->db->get_where('preferences')->result_array();
        $preferences = [];
        foreach ($res as $row) {
            $preferences[$row['field_name']] = $row['value'];
        }
        return $preferences;
    }

    public function add_sent_mail($type, $ref_id, $tracking_code, $sent_by) {
        return $this->db->insert('sent_email', [
                    'type' => $type,
                    'reference_id' => $ref_id,
                    'tracking_code' => $tracking_code,
                    'sent_by' => $sent_by
        ]);
    }

    public function get_email_tracking_code() {
        $this->load->helper('string');
        return random_string('numeric', 18);
    }

    public function get_pdf_buffer($html) {
//        $mpdf = new \Mpdf\Mpdf(['mode' => 's']);
////        $mpdf->setBasePath(base_url('public/images/'));
//        $mpdf->WriteHTML($html);
//        return $mpdf->Output('', 'S');

        $this->load->library('tcpdf');
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetTitle('My Title');
        $pdf->SetHeaderMargin(30);
        $pdf->SetTopMargin(20);
        $pdf->setFooterMargin(20);
        $pdf->SetAutoPageBreak(true);
        $pdf->SetAuthor('Author');
        $pdf->SetDisplayMode('real', 'default');

        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
//        $pdf->Write(5, 'Some sample text');
        return $pdf->Output('My-File-Name.pdf', 'S');
    }

    public function get_customer_creation_data() {
        $created_by = $_SESSION['customer']['id'];
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
    
    public function get_admin_creation_data() {
        $created_by = $_SESSION['admin']['id'];
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

}

?>
